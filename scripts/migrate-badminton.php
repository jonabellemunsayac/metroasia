<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/badminton-migration.php';
$successMessage = 'Pickleball and Badminton enabled on Wooden Courts 5–10.';
if (PHP_SAPI === 'cli') {
    migrate_badminton(db());
    echo $successMessage . "\n";
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
header('Cache-Control: no-store');
$admin = current_admin();
if ($admin === null) {
    header('Location: ../admin/login.php');
    exit;
}
if (($admin['role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    exit('Super Admin permission required.');
}

$_SESSION['badminton_migration_token'] ??= bin2hex(random_bytes(32));
$success = false;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['badminton_migration_token'], $token)) {
        http_response_code(403);
        $error = 'The request could not be verified. Reload this page and try again.';
    } else {
        try {
            migrate_badminton(db());
            $success = true;
            $_SESSION['badminton_migration_token'] = bin2hex(random_bytes(32));
        } catch (Throwable $exception) {
            http_response_code(500);
            error_log('Badminton migration failed: ' . $exception->getMessage());
            $error = 'The update could not be completed. Check the server error log for details.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wooden Court Sports Update</title>
    <style>
        body { font:16px/1.6 system-ui,sans-serif; background:#f5f4f8; color:#241638; margin:0; padding:32px 16px; }
        main { max-width:640px; margin:auto; padding:32px; background:white; border-radius:16px; }
        h1 { line-height:1.2; }
        button { padding:12px 20px; background:#492578; color:white; border:0; border-radius:8px; font:inherit; cursor:pointer; }
        .message { padding:16px; border:1px solid currentColor; border-radius:8px; }
        .success { color:#17643b; } .error { color:#a12222; }
    </style>
</head>
<body>
<main>
    <h1>Wooden Court Sports Update</h1>
    <p>Enable Pickleball and Badminton on Wooden Courts 5–10 and add missing rates. Existing rates and court activation settings are retained.</p>
    <p>Wooden Courts 5–7 belong to Miami. Wooden Courts 8–10 belong to Lakers.</p>
    <?php if ($success): ?>
        <p class="message success" role="status"><?php echo htmlspecialchars($successMessage); ?></p>
    <?php else: ?>
        <?php if ($error !== null): ?>
            <p class="message error" role="alert"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['badminton_migration_token']); ?>">
            <button type="submit">Run Update</button>
        </form>
    <?php endif; ?>
    <p><a href="../admin/courts.php">Go to Admin Courts</a></p>
</main>
</body>
</html>
