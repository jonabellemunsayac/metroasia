<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

$admin = require_admin_menu('admin-site-config');
if (!admin_can_manage_operations($admin)) {
    http_response_code(403);
    exit('Admin or Super Admin permission required.');
}
$pageTitle = 'Test Email';
$active = 'admin-site-config';
$_SESSION['test_email_csrf'] ??= bin2hex(random_bytes(32));
$result = $_SESSION['test_email_result'] ?? null;
unset($_SESSION['test_email_result']);
$mailConfig = app_mail_config();
$sender = $mailConfig['username'];
$recipient = (string) ($_SESSION['test_email_recipient'] ?? $admin['email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['test_email_csrf'], (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Your session expired. Reload this page and try again.');
        }
        $recipient = trim((string) ($_POST['recipient'] ?? ''));
        foreach ([$sender, $recipient] as $address) {
            if (strlen($address) > 254 || preg_match('/[\r\n]/', $address) || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter valid sender and recipient email addresses.');
            }
        }
        $_SESSION['test_email_recipient'] = $recipient;
        if (time() - (int) ($_SESSION['test_email_last_attempt'] ?? 0) < 30) {
            throw new RuntimeException('Please wait 30 seconds between email tests.');
        }
        if (!app_mail_ready($mailConfig)) throw new RuntimeException('Set the mailbox password in config/mail.local.php on the production server.');
        $_SESSION['test_email_last_attempt'] = time();
        $reference = bin2hex(random_bytes(6));
        $body = "Metro Asia email delivery test\r\n\r\nIf you received this message, this server delivered a test email to your mailbox.\r\n\r\nReference: {$reference}\r\nTime: " . date(DATE_ATOM) . "\r\n";
        app_send_email($recipient, 'Metro Asia - Email test ' . $reference, $body);
        $_SESSION['test_email_result'] = ['success' => true, 'message' => 'The mail service accepted the test email for ' . $recipient . '. Check the inbox and spam folder to confirm delivery. Reference: ' . $reference];
    } catch (Throwable $exception) {
        $_SESSION['test_email_result'] = ['success' => false, 'message' => $exception instanceof RuntimeException ? $exception->getMessage() : 'The email test failed. Check the server mail configuration.'];
    }
    redirect_to('admin/test-email.php');
}

include __DIR__ . '/../includes/header.php';
?>
<main class="app-main admin-compact">
    <section class="app-card">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
            <div><span class="section-kicker">Site Config</span><h2>Test email delivery</h2></div>
            <a class="btn btn-outline-secondary align-self-start" href="<?php echo htmlspecialchars(app_url('admin/site-config.php')); ?>">Back to Site Config</a>
        </div>
        <p>Send a test message through the configured Hostinger mailbox using authenticated SMTP over SSL.</p>
        <?php if ($result): ?>
            <div class="alert <?php echo $result['success'] ? 'alert-success' : 'alert-danger'; ?>" role="status"><?php echo htmlspecialchars($result['message']); ?></div>
        <?php endif; ?>
        <div class="row g-4">
            <form method="post" class="col-lg-6">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['test_email_csrf']); ?>">
                <label class="d-block fw-bold mb-3">Sender email
                    <input class="form-input mt-1" type="email" readonly aria-readonly="true" value="<?php echo htmlspecialchars($sender); ?>">
                </label>
                <label class="d-block fw-bold mb-3">Recipient email
                    <input class="form-input mt-1" type="email" name="recipient" maxlength="254" required value="<?php echo htmlspecialchars($recipient); ?>">
                </label>
                <button class="btn btn-primary" type="submit">Send test email</button>
            </form>
            <div class="col-lg-6">
                <h3>Current mail configuration</h3>
                <dl>
                    <dt>SMTP host</dt><dd><?php echo htmlspecialchars($mailConfig['host']); ?></dd>
                    <dt>Port / security</dt><dd>465 / SSL (implicit TLS)</dd>
                    <dt>Mailbox credentials</dt><dd><?php echo app_mail_ready($mailConfig) ? 'Configured (not yet verified)' : 'Mailbox password required'; ?></dd>
                </dl>
                <p class="small text-secondary">Set the mailbox password in config/mail.local.php on the server, or use the SMTP_PASSWORD environment variable. No php.ini mail settings are required.</p>
                <p class="small text-secondary">An accepted message does not guarantee inbox delivery. Confirm receipt at the recipient address.</p>
            </div>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
