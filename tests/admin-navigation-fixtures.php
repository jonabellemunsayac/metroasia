<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../includes/auth.php';
$pdo = db();
$input = json_decode(stream_get_contents(STDIN), true) ?: [];
if (($argv[1] ?? '') === 'init') {
    $email = 'navigation-' . bin2hex(random_bytes(8)) . '@example.test';
    $password = bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO admin_users (name,email,password_hash,role) VALUES ('Navigation test',?,?, 'admin')")
        ->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
    echo json_encode(['id' => (int) $pdo->lastInsertId(), 'email' => $email, 'password' => $password]);
} elseif (($argv[1] ?? '') === 'cleanup') {
    $q = $pdo->prepare('SELECT id FROM admin_users WHERE id=? AND email=?');
    $q->execute([$input['id'], $input['email']]);
    if (!$q->fetchColumn()) exit;
    $pdo->prepare("DELETE FROM account_sessions WHERE account_type='admin' AND account_id=?")->execute([$input['id']]);
    $pdo->prepare("DELETE FROM access_logs WHERE account_type='admin' AND account_id=?")->execute([$input['id']]);
    $pdo->prepare('DELETE FROM admin_users WHERE id=? AND email=?')->execute([$input['id'], $input['email']]);
}
