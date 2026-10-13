<?php
declare(strict_types=1);

class AccountSessionInUse extends RuntimeException {}

function ensure_account_sessions(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS account_sessions (
        account_type VARCHAR(10) NOT NULL,
        account_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        last_seen BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (account_type, account_id, token_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $primaryColumns = static function () use ($pdo): array {
        $rows = $pdo->query("SHOW INDEX FROM account_sessions WHERE Key_name = 'PRIMARY'")->fetchAll();
        usort($rows, static fn ($a, $b) => $a['Seq_in_index'] <=> $b['Seq_in_index']);
        return array_column($rows, 'Column_name');
    };
    if ($primaryColumns() === ['account_type', 'account_id']) {
        try {
            // Preserve existing sessions while allowing a row for each device.
            $pdo->exec('ALTER TABLE account_sessions DROP PRIMARY KEY, ADD PRIMARY KEY (account_type, account_id, token_hash)');
        } catch (PDOException $exception) {
            // Another login request may have completed the migration first.
            if ($primaryColumns() !== ['account_type', 'account_id', 'token_hash']) throw $exception;
        }
    }
    $ready = true;
}

function claim_account_session(PDO $pdo, string $type, int $id, string $token, int $now): bool
{
    ensure_account_sessions($pdo);
    $hash = hash('sha256', $token);
    // Each browser session is tracked independently; other logins remain valid.
    $q = $pdo->prepare('INSERT INTO account_sessions (account_type,account_id,token_hash,last_seen) VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE last_seen=VALUES(last_seen)');
    $q->execute([$type,$id,$hash,$now]);
    return true;
}

function release_account_session(): void
{
    if (empty($_SESSION['auth_lease_token']) || empty($_SESSION['auth_lease_owner'])) return;
    [$type,$id] = explode(':',$_SESSION['auth_lease_owner'],2);
    $pdo = db();
    ensure_account_sessions($pdo);
    $q = $pdo->prepare('DELETE FROM account_sessions WHERE account_type=? AND account_id=? AND token_hash=?');
    $q->execute([$type,(int)$id,hash('sha256',$_SESSION['auth_lease_token'])]);
}

function validate_account_session(string $type, int $id): bool
{
    $pdo = db();
    ensure_account_sessions($pdo);
    $now = time();
    if (!empty($_SESSION['auth_last_activity_at']) && $now-(int)$_SESSION['auth_last_activity_at']>ACCESS_SESSION_TIMEOUT_SECONDS) {
        release_account_session();
        return false;
    }
    $owner = $type.':'.$id;
    if (empty($_SESSION['auth_lease_token'])) {
        // Register pre-deployment sessions without displacing other devices.
        $token = bin2hex(random_bytes(32));
        if (!claim_account_session($pdo,$type,$id,$token,$now)) return false;
        $_SESSION['auth_lease_token']=$token;
        $_SESSION['auth_lease_owner']=$owner;
        return true;
    }
    if (($_SESSION['auth_lease_owner'] ?? '') !== $owner) return false;
    $q=$pdo->prepare('UPDATE account_sessions SET last_seen=? WHERE account_type=? AND account_id=? AND token_hash=? AND last_seen>=?');
    $hash=hash('sha256',$_SESSION['auth_lease_token']);
    $q->execute([$now,$type,$id,$hash,$now-ACCESS_SESSION_TIMEOUT_SECONDS]);
    // UPDATE may report zero changed rows for multiple checks during the same second.
    $q=$pdo->prepare('SELECT 1 FROM account_sessions WHERE account_type=? AND account_id=? AND token_hash=? AND last_seen>=?');
    $q->execute([$type,$id,$hash,$now-ACCESS_SESSION_TIMEOUT_SECONDS]);
    return (bool)$q->fetchColumn();
}
