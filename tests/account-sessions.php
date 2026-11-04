<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/../includes/auth.php';
$pdo=db();
$pdo->exec('CREATE TEMPORARY TABLE account_sessions (
 account_type VARCHAR(10) NOT NULL, account_id INT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL,last_seen BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(account_type,account_id,token_hash)) ENGINE=InnoDB');
function check(bool $ok): void { if (!$ok) throw new RuntimeException('Session assertion failed'); }
$insert=$pdo->prepare('INSERT INTO account_sessions VALUES (?,?,?,?)');
$insert->execute(['admin',1,hash('sha256','legacy-one'),time()]);
$insert->execute(['admin',1,hash('sha256','legacy-two'),time()]);
ensure_account_sessions($pdo);
check((int)$pdo->query('SELECT COUNT(*) FROM account_sessions')->fetchColumn()===0);
$indexes=$pdo->query("SHOW INDEX FROM account_sessions WHERE Key_name='PRIMARY'")->fetchAll();
check(array_column($indexes,'Column_name')===['account_type','account_id']);
ensure_account_sessions($pdo); // migration is repeatable
foreach (['admin','member'] as $type) {
    claim_account_session($pdo,$type,1,'one',time());
    $_SESSION=['auth_lease_token'=>'one','auth_lease_owner'=>$type.':1'];
    check(validate_account_session($type,1));
    claim_account_session($pdo,$type,1,'two',time());
    check(!validate_account_session($type,1));
    check($_SESSION['auth_failure']==='session_replaced');
    release_account_session(); // stale logout must not delete the winner
    $_SESSION=['auth_lease_token'=>'two','auth_lease_owner'=>$type.':1'];
    check(validate_account_session($type,1));
    check(validate_account_session($type,1)); // refresh within same second
    $pdo->prepare('UPDATE account_sessions SET last_seen=? WHERE account_type=?')->execute([time()-ACCESS_SESSION_TIMEOUT_SECONDS-1,$type]);
    check(!validate_account_session($type,1));
    check($_SESSION['auth_failure']==='session_expired');
    $_SESSION=[];
    check(!validate_account_session($type,1)); // legacy sessions cannot reclaim
}
echo "In-place migration, admin/player takeover, refresh, stale logout, expiration and legacy rejection passed.\n";
