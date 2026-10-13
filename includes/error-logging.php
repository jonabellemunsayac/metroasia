<?php
declare(strict_types=1);

// Keep warnings and fatal errors out of HTML and JSON responses.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

$phpLogDirectory = dirname(__DIR__) . '/logs';
if (!is_dir($phpLogDirectory)) {
    @mkdir($phpLogDirectory, 0750, true);
}
$phpLogProtection = $phpLogDirectory . '/.htaccess';
if (is_dir($phpLogDirectory) && !is_file($phpLogProtection)) {
    @file_put_contents($phpLogProtection, "Require all denied\n", LOCK_EX);
}
if (is_file($phpLogProtection) && is_writable($phpLogDirectory)
    && (!file_exists($phpLogDirectory . '/php-error.log') || is_writable($phpLogDirectory . '/php-error.log'))) {
    ini_set('error_log', $phpLogDirectory . '/php-error.log');
} else {
    // Retain the host's error log when the application log is not writable.
    error_log('Application PHP log directory is missing or not writable: ' . $phpLogDirectory);
}
unset($phpLogDirectory, $phpLogProtection);
