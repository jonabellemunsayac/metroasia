<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/mailer.php';
function mail_check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
// Dummy credentials are limited to this CLI process; no connection or email is sent.
putenv('SMTP_USERNAME=sender@example.test');
putenv('SMTP_PASSWORD=dummy-test-password');
$mail = app_mail_client();
mail_check($mail->Host === 'smtp.hostinger.com' && $mail->Port === 465, 'Incorrect SMTP endpoint.');
mail_check($mail->SMTPSecure === 'ssl' && $mail->SMTPAuth, 'Secure authentication required.');
mail_check($mail->From === 'sender@example.test' && $mail->Username === $mail->From, 'Sender must match mailbox.');
mail_check($mail->SMTPDebug === 0, 'SMTP debugging must be disabled.');
mail_check(!app_mail_ready(['username' => 'sender@example.test', 'password' => '']), 'Empty password accepted.');
try {
    app_send_email("recipient@example.test\r\nBcc: other@example.test", 'Test', 'Test');
    throw new LogicException('Header injection accepted.');
} catch (RuntimeException $error) {
    mail_check(str_contains($error->getMessage(), 'recipient'), 'Unexpected validation error.');
}
echo "PASS: SMTP configuration, implicit TLS, sender identity, missing credentials, recipient validation. No email sent.\n";
