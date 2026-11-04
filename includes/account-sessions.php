<?php
declare(strict_types=1);
class AccountSessionInUse extends RuntimeException {}
const ACCOUNT_REPLACED_MESSAGE = 'Your account has been logged out because it was accessed from another device.';
function ensure_account_sessions(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;
    // Keep the existing table; migrate its former per-device primary key in place.
    $pdo->exec("CREATE TABLE IF NOT EXISTS account_sessions (
        account_type VARCHAR(10) NOT NULL, account_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL, last_seen BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (account_type, account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $primaryColumns = static function () use ($pdo): array {
        $rows = $pdo->query("SHOW INDEX FROM account_sessions WHERE Key_name = 'PRIMARY'")->fetchAll();
        usort($rows, static fn ($a, $b) => $a['Seq_in_index'] <=> $b['Seq_in_index']);
        return array_column($rows, 'Column_name');
    };
    if ($primaryColumns() !== ['account_type', 'account_id']) {
        $lock = 'auth-schema:' . substr(hash('sha256', DB_NAME), 0, 48);
        $q = $pdo->prepare('SELECT GET_LOCK(?, 15)');
        $q->execute([$lock]);
        if ((int) $q->fetchColumn() !== 1) throw new RuntimeException('Session migration is busy. Please retry.');
        try {
            // Recheck after waiting: another request may already have migrated it.
            if ($primaryColumns() !== ['account_type', 'account_id']) {
                // last_seen is activity, not login order: no safe legacy winner exists.
                // Revoke legacy tokens before installing the unique account key.
                $pdo->exec('DELETE FROM account_sessions');
                $pdo->exec('ALTER TABLE account_sessions DROP PRIMARY KEY, ADD PRIMARY KEY (account_type, account_id)');
            }
        } finally {
            $q = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $q->execute([$lock]);
        }
    }
    $ready = true;
}
function lock_account_session(PDO $pdo, string $type, int $id): void
{
    static $held = [];
    $name = 'auth:' . substr(hash('sha256', DB_NAME . ':' . $type . ':' . $id), 0, 58);
    if (isset($held[$name])) return;
    $q = $pdo->prepare('SELECT GET_LOCK(?, 15)');
    $q->execute([$name]);
    if ((int) $q->fetchColumn() !== 1) throw new RuntimeException('Session validation is busy. Please retry.');
    $held[$name] = true;
    // Serialize login with the entire protected request, including writes/commits.
    // Named locks survive application DDL and independent booking transactions.
    register_shutdown_function(static function () use ($pdo, $name): void {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $q = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $q->execute([$name]);
    });
}
function claim_account_session(PDO $pdo, string $type, int $id, string $token, int $now): bool
{
    ensure_account_sessions($pdo);
    lock_account_session($pdo, $type, $id);
    $q = $pdo->prepare('INSERT INTO account_sessions (account_type,account_id,token_hash,last_seen) VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash), last_seen=VALUES(last_seen)');
    $q->execute([$type,$id,hash('sha256',$token),$now]);
    return true;
}
function release_account_session(): void
{
    if (empty($_SESSION['auth_lease_token']) || empty($_SESSION['auth_lease_owner'])) return;
    [$type,$id] = explode(':',$_SESSION['auth_lease_owner'],2);
    $pdo = db();
    ensure_account_sessions($pdo);
    lock_account_session($pdo,$type,(int)$id);
    $q = $pdo->prepare('DELETE FROM account_sessions WHERE account_type=? AND account_id=? AND token_hash=?');
    $q->execute([$type,(int)$id,hash('sha256',$_SESSION['auth_lease_token'])]);
}
function validate_account_session(string $type, int $id): bool
{
    $pdo = db();
    ensure_account_sessions($pdo);
    lock_account_session($pdo,$type,$id);
    $q=$pdo->prepare('SELECT token_hash,last_seen FROM account_sessions WHERE account_type=? AND account_id=?');
    $q->execute([$type,$id]);
    $row=$q->fetch();
    $token=$_SESSION['auth_lease_token'] ?? '';
    if (!$row || !$token || ($_SESSION['auth_lease_owner'] ?? '') !== $type.':'.$id
        || !hash_equals($row['token_hash'],hash('sha256',$token))) {
        $_SESSION['auth_failure']=$row && $token ? 'session_replaced' : 'session_expired';
        return false;
    }
    $now=time();
    if ($now-(int)$row['last_seen']>ACCESS_SESSION_TIMEOUT_SECONDS) {
        release_account_session();
        $_SESSION['auth_failure']='session_expired';
        return false;
    }
    // Passive polling must not keep an unattended session alive.
    if (!defined('ACCOUNT_SESSION_PROBE')) {
        $q=$pdo->prepare('UPDATE account_sessions SET last_seen=? WHERE account_type=? AND account_id=? AND token_hash=?');
        $q->execute([$now,$type,$id,$row['token_hash']]);
    }
    return true;
}
