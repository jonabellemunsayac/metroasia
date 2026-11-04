<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/../config/database.php';
require __DIR__.'/../includes/account-sessions.php';
ensure_account_sessions(db());
echo "Single-session table ready. Legacy sessions must sign in again.\n";
