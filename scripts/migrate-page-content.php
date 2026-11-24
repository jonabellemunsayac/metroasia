<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/rules.php';
require_once __DIR__ . '/../includes/terms-conditions.php';
require_once __DIR__ . '/../includes/data-privacy.php';
require_once __DIR__ . '/../includes/faqs.php';
$pdo = db();
rules_ensure_table($pdo);
terms_conditions_ensure_table($pdo);
data_privacy_ensure_table($pdo);
faqs_ensure_table($pdo);
echo "Page content migration complete. Existing versions retained.\n";
