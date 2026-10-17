<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../config/database.php';
$application = db();
$connectionId = $application->query('SELECT CONNECTION_ID()')->fetchColumn();
for ($index = 0; $index < 25; $index++) {
    if (db() !== $application || db()->query('SELECT CONNECTION_ID()')->fetchColumn() !== $connectionId) {
        throw new RuntimeException('Repeated calls must reuse the application connection');
    }
}
$server = db(false);
if ($server === $application || db(false) !== $server) {
    throw new RuntimeException('Setup must reuse its own separate connection');
}
if ($application->query('SELECT DATABASE()')->fetchColumn() !== DB_NAME
    || $server->query('SELECT DATABASE()')->fetchColumn() !== null) {
    throw new RuntimeException('Connection database selection changed');
}
echo "Repeated calls reuse one connection; server-only setup remains separate.\n";
