<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$error = null;
$memberRedirect = member_redirect_target('ui/member.php');

if (current_admin() !== null) {
    redirect_to('admin/dashboard.php');
}

if (current_member() !== null) {
    redirect_to($memberRedirect);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Use a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } else {
        try {
            $pdo = db();
            $adminStmt = $pdo->prepare('SELECT id FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1');
            $adminStmt->execute([$email]);
            $adminId = (int) ($adminStmt->fetchColumn() ?: 0);

            $memberId = 0;
            if ($adminId === 0) {
                $memberStmt = $pdo->prepare('SELECT id FROM members WHERE email = ? AND is_active = 1 LIMIT 1');
                $memberStmt->execute([$email]);
                $memberId = (int) ($memberStmt->fetchColumn() ?: 0);
            }

            if ($adminId === 0 && $memberId === 0) {
                $error = 'No active account was found for that email.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                session_regenerate_id(true);
                if ($adminId > 0) {
                    $update = $pdo->prepare('UPDATE admin_users SET password_hash = ?, last_login_at = NOW() WHERE id = ?');
                    $update->execute([$passwordHash, $adminId]);
                    unset($_SESSION['member_id']);
                    $_SESSION['admin_id'] = $adminId;
                    start_access_session('admin', $adminId, access_log_account_role('admin', $adminId), 'password_reset');
                    redirect_to('admin/dashboard.php');
                }

                $update = $pdo->prepare('UPDATE members SET password_hash = ?, last_login_at = NOW() WHERE id = ?');
                $update->execute([$passwordHash, $memberId]);
                unset($_SESSION['admin_id']);
                $_SESSION['member_id'] = $memberId;
                start_access_session('member', $memberId, 'member', 'password_reset');
                redirect_to($memberRedirect);
            }
        } catch (Throwable) {
            $error = 'Database is not ready. Open setup.php first.';
        }
    }
}

$pageTitle = 'Reset Password';
$active = 'member';
$memberAccountStyles = true;
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page">
    <section class="mx-auto grid max-w-[960px] gap-6 lg:grid-cols-[1fr_420px]">
        <div class="public-card p-6">
            <p class="text-sm font-black uppercase tracking-[.14em] text-primary">Account Recovery</p>
            <h1 class="mt-3 font-display text-4xl font-black leading-tight">Reset your password.</h1>
            <p class="mt-4 text-sm font-semibold leading-7 text-muted">
                Enter the email on your MetroAsia account and set a new password. After saving, you will be signed in automatically.
            </p>
            <a
                href="<?php echo htmlspecialchars(app_url('login.php?redirect=' . rawurlencode($memberRedirect))); ?>"
                class="mt-6 inline-flex rounded-full border border-line px-5 py-2.5 text-sm font-black transition hover:border-primary hover:text-primary"
            >
                Back to Sign In
            </a>
        </div>

        <form method="post" action="<?php echo htmlspecialchars(app_url('ui/forgot-password.php')); ?>" class="public-card p-5">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($memberRedirect); ?>">
            <h2 class="text-xl font-black">New Password</h2>
            <?php if ($error): ?>
                <div class="mt-4 rounded-lg bg-rose-50 p-3 text-sm font-bold text-rose-700"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <label class="mt-4 grid gap-2 text-sm font-bold">Email
                <input name="email" type="email" required class="form-input" autocomplete="email" value="<?php echo htmlspecialchars((string) ($_POST['email'] ?? $_GET['email'] ?? '')); ?>">
            </label>
            <label class="mt-4 grid gap-2 text-sm font-bold admin-password-field public-password-field">New password
                <input id="resetPassword" name="password" type="password" required minlength="8" class="form-input" autocomplete="new-password">
                <button type="button" class="admin-password-toggle" data-password-toggle="resetPassword" aria-label="Show password">
                    <i data-lucide="eye" class="icon-sm"></i>
                </button>
            </label>
            <label class="mt-4 grid gap-2 text-sm font-bold admin-password-field public-password-field">Confirm new password
                <input id="resetConfirmPassword" name="confirmPassword" type="password" required minlength="8" class="form-input" autocomplete="new-password">
                <button type="button" class="admin-password-toggle" data-password-toggle="resetConfirmPassword" aria-label="Show password">
                    <i data-lucide="eye" class="icon-sm"></i>
                </button>
            </label>
            <button class="btn btn-primary mt-5 w-full">Reset Password and Sign In</button>
        </form>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
