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

/** Write API failures directly, even when hosting locks PHP's error_log setting. */
function app_log_error(string $message): string
{
    $line = '[' . date(DATE_ATOM) . '] ' . $message . PHP_EOL;
    $directory = dirname(__DIR__) . '/logs';
    if (is_file($directory . '/.htaccess')
        && @file_put_contents($directory . '/php-error.log', $line, FILE_APPEND | LOCK_EX) !== false) {
        return 'application';
    }

    // A private per-installation directory outside the public application tree.
    $directory = rtrim(sys_get_temp_dir(), '/\\') . '/multisportspass-' . substr(hash('sha256', dirname(__DIR__)), 0, 16);
    if (!is_dir($directory)) @mkdir($directory, 0700, true);
    if (is_dir($directory)
        && @file_put_contents($directory . '/php-error.log', $line, FILE_APPEND | LOCK_EX) !== false) {
        @chmod($directory . '/php-error.log', 0600);
        return 'temporary';
    }

    error_log($message);
    return 'hosting-fallback';
}
