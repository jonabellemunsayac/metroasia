<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$error = null;

if (current_member() !== null) {
    redirect_to('ui/member.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $phone === '' || strlen($password) < 8) {
        $error = 'Name, email, phone, and an 8-character password are required.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare('INSERT INTO members (name, email, phone, password_hash) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            unset($_SESSION['admin_id']);
            $_SESSION['member_id'] = (int) $pdo->lastInsertId();
            redirect_to('ui/member.php');
        } catch (PDOException $exception) {
            $error = str_contains($exception->getMessage(), 'Duplicate') ? 'That email is already registered.' : 'Could not create member account.';
        } catch (Throwable) {
            $error = 'Database is not ready. Open setup.php first.';
        }
    }
}

$pageTitle = 'Become a Member | Multi-Sport Court Scheduling & Reservation';
$active = 'member';
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page">
    <section class="mx-auto grid max-w-[960px] gap-6 lg:grid-cols-[1fr_440px]">
        <div class="public-card p-6">
            <p class="text-sm font-black uppercase tracking-[.14em] text-primary">Become a Member</p>
            <h1 class="mt-3 font-display text-4xl font-black leading-tight">Book faster and upload payment proof.</h1>
            <div class="mt-5 grid gap-3 text-sm font-semibold leading-6 text-muted">
                <p>Registered members can upload receipts directly to the website.</p>
                <p>Reservations are linked to your account for booking history and admin payment review.</p>
                <p>Non-members can still book, but payment proof is handled outside the member portal.</p>
            </div>
        </div>

        <form method="post" class="public-card p-5">
            <h2 class="text-xl font-black">Create Account</h2>
            <?php if ($error): ?>
                <div class="mt-4 rounded-lg bg-rose-50 p-3 text-sm font-bold text-rose-700"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <label class="mt-4 grid gap-2 text-sm font-bold">Full name
                <input name="name" required class="form-input" autocomplete="name" value="<?php echo htmlspecialchars((string) ($_POST['name'] ?? '')); ?>">
            </label>
            <label class="mt-4 grid gap-2 text-sm font-bold">Email
                <input name="email" type="email" required class="form-input" autocomplete="email" value="<?php echo htmlspecialchars((string) ($_POST['email'] ?? '')); ?>">
            </label>
            <label class="mt-4 grid gap-2 text-sm font-bold">Phone
                <input name="phone" required class="form-input" autocomplete="tel" value="<?php echo htmlspecialchars((string) ($_POST['phone'] ?? '')); ?>">
            </label>
            <label class="mt-4 grid gap-2 text-sm font-bold">Password
                <input name="password" type="password" required minlength="8" class="form-input" autocomplete="new-password">
            </label>
            <button class="btn btn-primary mt-5 w-full">Create Member Account</button>
            <a href="<?php echo htmlspecialchars(app_url('login.php')); ?>" class="mt-4 block text-center text-xs font-bold text-muted hover:text-primary">Already a member? Sign in</a>
        </form>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
