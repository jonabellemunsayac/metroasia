<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

function app_mail_ready(array $config): bool
{
    return filter_var($config['username'], FILTER_VALIDATE_EMAIL) !== false && $config['password'] !== '';
}

function app_mail_client(): PHPMailer\PHPMailer\PHPMailer
{
    $config = app_mail_config();
    if (!app_mail_ready($config)) throw new RuntimeException('Set the Hostinger mailbox address and password in config/mail.local.php or the SMTP_USERNAME and SMTP_PASSWORD environment variables.');
    if (!extension_loaded('openssl')) throw new RuntimeException('Enable the PHP OpenSSL extension for secure SMTP.');
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->Port = $config['port'];
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->SMTPAuth = true;
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    $mail->SMTPDebug = 0;
    $mail->Timeout = 20;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($config['username'], $config['from_name']);
    return $mail;
}

function app_send_email(string $recipient, string $subject, string $body): void
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $recipient)) {
        throw new RuntimeException('Enter a valid recipient email address.');
    }
    $mail = app_mail_client();
    try {
        $mail->addAddress($recipient);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
    } catch (PHPMailer\PHPMailer\Exception $exception) {
        // Avoid logging SMTP transcripts or credentials.
        throw new RuntimeException('Hostinger SMTP did not accept the message. Check the mailbox credentials, server access to port 465, and PHP TLS certificates.');
    } finally {
        $mail->smtpClose();
    }
}
