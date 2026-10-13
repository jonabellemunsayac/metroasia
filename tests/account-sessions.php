<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/account-sessions.php';

$pdo = db();
// A connection-local table shadows the real table; existing sessions are untouched.
$pdo->exec('CREATE TEMPORARY TABLE account_sessions (
    account_type VARCHAR(10) NOT NULL, account_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL, last_seen BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (account_type, account_id)
)');
$insert = $pdo->prepare('INSERT INTO account_sessions VALUES (?,?,?,?)');
$insert->execute(['admin', 1, hash('sha256', 'existing'), 100]);
ensure_account_sessions($pdo);
foreach (['admin', 'member'] as $type) {
    claim_account_session($pdo, $type, 1, 'browser-one', 200);
    claim_account_session($pdo, $type, 1, 'browser-two', 300);
    claim_account_session($pdo, $type, 1, 'browser-one', 400);
    $query = $pdo->prepare('SELECT token_hash, last_seen FROM account_sessions WHERE account_type=?');
    $query->execute([$type]);
    $sessions = $query->fetchAll(PDO::FETCH_KEY_PAIR);
    if ((int) $sessions[hash('sha256', 'browser-one')] !== 400
        || (int) $sessions[hash('sha256', 'browser-two')] !== 300) {
        throw new RuntimeException('Sessions must be stored and refreshed independently');
    }
    if ($type === 'admin' && (int) $sessions[hash('sha256', 'existing')] !== 100) {
        throw new RuntimeException('Migration must preserve existing sessions');
    }
}
echo "Session migration preserves existing rows; admin and member concurrent sessions remain independent.\n";
