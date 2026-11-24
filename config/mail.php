<?php
declare(strict_types=1);

function app_mail_config(): array
{
    $local = [];
    if (is_file(__DIR__ . '/mail.local.php')) {
        $local = require __DIR__ . '/mail.local.php';
        if (!is_array($local)) throw new RuntimeException('Invalid mail configuration.');
    }
    return [
        'host' => 'smtp.hostinger.com',
        'port' => 465,
        'username' => trim((string) (getenv('SMTP_USERNAME') ?: ($local['username'] ?? 'contactus@metroasiaarena.com'))),
        'password' => (string) (getenv('SMTP_PASSWORD') ?: ($local['password'] ?? '')),
        'from_name' => (string) (getenv('SMTP_FROM_NAME') ?: ($local['from_name'] ?? 'Metro Asia Sports Center')),
    ];
}
