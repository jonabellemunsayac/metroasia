<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$error = null;

if (current_admin() !== null) {
    redirect_to('admin/dashboard.php');
}

if (current_member() !== null) {
    redirect_to('ui/member.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    try {
        $accountType = sign_in_account($email, $password);

        if ($accountType === 'admin') {
            redirect_to('admin/dashboard.php');
        }

        if ($accountType === 'member') {
            redirect_to('ui/member.php');
        }

        $error = 'Invalid email or password.';
    } catch (Throwable) {
        $error = 'Database is not ready. Open setup.php first.';
    }
}

$pageTitle = 'Sign In';
$active = 'member';
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page">
    <section class="mx-auto grid max-w-[960px] gap-6 lg:grid-cols-[1fr_420px]">
        <div class="public-card p-6">
            <p class="text-sm font-black uppercase tracking-[.14em] text-primary">Account Access</p>
            <h1 class="mt-3 font-display text-4xl font-black leading-tight">Sign in to Metro Asia.</h1>
            <p class="mt-4 text-sm font-semibold leading-7 text-muted">Use your account email and password to continue.</p>
            <a href="<?php echo htmlspecialchars(app_url('ui/register.php')); ?>" class="mt-6 inline-flex rounded-full border border-line px-5 py-2.5 text-sm font-black transition hover:border-primary hover:text-primary">Create Member Account</a>
        </div>

        <form method="post" action="<?php echo htmlspecialchars(app_url('login.php')); ?>" class="public-card p-5">
            <h2 class="text-xl font-black">Sign In</h2>
            <?php if ($error): ?>
                <div class="mt-4 rounded-lg bg-rose-50 p-3 text-sm font-bold text-rose-700"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <label class="mt-4 grid gap-2 text-sm font-bold">Email
                <input name="email" type="email" required class="form-input" autocomplete="email" value="<?php echo htmlspecialchars((string) ($_POST['email'] ?? '')); ?>">
            </label>
            <label class="mt-4 grid gap-2 text-sm font-bold">Password
                <input name="password" type="password" required class="form-input" autocomplete="current-password">
            </label>
            <button class="btn btn-primary mt-5 w-full">Sign In</button>
            <p class="mt-4 text-center text-xs font-semibold text-muted">Admins continue to the dashboard. Members continue to My Bookings.</p>
        </form>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
