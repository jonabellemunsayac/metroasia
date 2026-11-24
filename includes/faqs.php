<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/page-content.php';

function faqs_ensure_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS faqs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        question VARCHAR(500) NOT NULL,
        answer_html MEDIUMTEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function faqs_all(PDO $pdo): array
{
    faqs_ensure_table($pdo);
    return $pdo->query('SELECT * FROM faqs ORDER BY sort_order, id')->fetchAll();
}
