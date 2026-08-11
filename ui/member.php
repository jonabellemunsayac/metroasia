<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$member = require_member();
$message = null;
$error = null;

function member_court_name(int $courtId, string $sport): string
{
    if ($sport === 'Pickleball') {
        return match ($courtId) {
            1 => 'Pickleball Pro Court 1',
            2 => 'Pickleball Pro Court 2',
            3 => 'Pickleball Pro Court 3',
            4 => 'Pickleball Pro Court 4',
            5 => 'Wooden Court 5',
            6 => 'Wooden Court 6',
            7 => 'Wooden Court 7',
            default => 'Court ' . $courtId,
        };
    }

    return match ($courtId) {
        1 => 'Lakers',
        2 => 'Miami',
        default => 'Court ' . $courtId,
    };
}

function save_member_receipt(): ?string
{
    if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES['receipt']['error'] !== UPLOAD_ERR_OK || $_FILES['receipt']['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Receipt upload failed or exceeded 5MB.');
    }

    $tmp = $_FILES['receipt']['tmp_name'];
    $mime = mime_content_type($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Use a JPG, PNG, WEBP, or PDF receipt.');
    }

    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'receipts';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $filename = 'receipt-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, $dir . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Could not save receipt.');
    }
    return 'uploads/receipts/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $reservation = (string) ($_POST['reservation'] ?? '');
        [$type, $rawId] = array_pad(explode(':', $reservation, 2), 2, '');
        $id = (int) $rawId;
        $receipt = save_member_receipt();
        if (!$receipt || $id <= 0 || !in_array($type, ['court', 'openplay'], true)) {
            throw new RuntimeException('Choose a reservation and upload a valid receipt.');
        }

        $table = $type === 'court' ? 'court_bookings' : 'open_play_reservations';
        $stmt = db()->prepare("UPDATE {$table} SET receipt_path = ?, status = 'Payment Submitted' WHERE id = ? AND member_id = ? AND status IN ('Payment Pending','Held')");
        $stmt->execute([$receipt, $id, (int) $member['id']]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Reservation was not found or no longer accepts receipt upload.');
        }
        $message = 'Receipt uploaded. Admin will review your payment proof.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$pdo = db();
$courtStmt = $pdo->prepare(
    "SELECT CONCAT('court:', cb.id) AS id, 'court' AS type, cb.booking_date AS date,
            ts.label AS time, cb.court_id AS court, cb.sport, NULL AS title, cb.status,
            cb.payment_method, cb.receipt_path, cb.final_amount, cb.created_at
     FROM court_bookings cb
     JOIN time_slots ts ON ts.id = cb.time_slot_id
     WHERE cb.member_id = ?"
);
$courtStmt->execute([(int) $member['id']]);
$openStmt = $pdo->prepare(
    "SELECT CONCAT('openplay:', opr.id) AS id, 'openplay' AS type, ops.session_date AS date,
            ops.session_time AS time, NULL AS court, 'Open Play' AS sport, ops.title, opr.status,
            opr.payment_method, opr.receipt_path, opr.final_amount, opr.created_at
     FROM open_play_reservations opr
     JOIN open_play_sessions ops ON ops.id = opr.session_id
     WHERE opr.member_id = ?"
);
$openStmt->execute([(int) $member['id']]);
$reservations = array_merge($courtStmt->fetchAll(), $openStmt->fetchAll());
usort($reservations, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));

$pageTitle = 'My Bookings | Multi-Sport Court Scheduling & Reservation';
$active = 'member';
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page">
    <section class="grid gap-6 lg:grid-cols-[340px_minmax(0,1fr)]">
        <aside class="grid content-start gap-5">
            <article class="public-card p-5">
                <p class="text-sm font-black uppercase tracking-[.14em] text-primary">Member Profile</p>
                <h1 class="mt-3 text-2xl font-black"><?php echo htmlspecialchars($member['name']); ?></h1>
                <p class="mt-2 text-sm font-semibold text-muted"><?php echo htmlspecialchars($member['email']); ?></p>
                <p class="mt-1 text-sm font-semibold text-muted"><?php echo htmlspecialchars($member['phone']); ?></p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="rounded-full bg-limevolt px-5 py-2.5 text-sm font-black text-ink">Book Court</a>
                    <a href="<?php echo htmlspecialchars(app_url('admin/logout.php?as=member')); ?>" class="rounded-full border border-line px-5 py-2.5 text-sm font-black text-ink">Logout</a>
                </div>
            </article>

            <article class="public-card p-5">
                <h2 class="text-sm font-black">Receipt Upload</h2>
                <p class="mt-2 text-sm font-semibold leading-6 text-muted">Upload proof for reservations that are still waiting for payment verification.</p>
                <?php if ($message): ?>
                    <div class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm font-bold text-emerald-700"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="mt-4 rounded-lg bg-rose-50 p-3 text-sm font-bold text-rose-700"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
            </article>
        </aside>

        <section class="booking-panel">
            <div class="border-b border-line px-5 py-4">
                <p class="text-sm font-black uppercase tracking-[.14em] text-primary">Reservations</p>
                <h2 class="mt-1 text-2xl font-black">Booking history</h2>
            </div>
            <div class="grid gap-3 p-4">
                <?php if (!$reservations): ?>
                    <div class="rounded-lg border border-dashed border-line p-5 text-sm font-bold text-muted">No reservations yet.</div>
                <?php endif; ?>
                <?php foreach ($reservations as $reservation): ?>
                    <?php
                    $title = $reservation['type'] === 'court'
                        ? member_court_name((int) $reservation['court'], (string) $reservation['sport']) . ' - ' . $reservation['sport']
                        : (string) $reservation['title'];
                    $canUpload = in_array($reservation['status'], ['Payment Pending', 'Held'], true);
                    ?>
                    <article class="rounded-lg border border-line bg-white p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-black"><?php echo htmlspecialchars($title); ?></h3>
                                <p class="mt-1 text-sm font-semibold text-muted"><?php echo htmlspecialchars($reservation['date'] . ' - ' . $reservation['time']); ?></p>
                                <p class="mt-1 text-sm font-semibold text-muted"><?php echo htmlspecialchars($reservation['payment_method']); ?> · PHP <?php echo number_format((float) $reservation['final_amount'], 0); ?></p>
                                <?php if ($reservation['receipt_path']): ?>
                                    <a href="<?php echo htmlspecialchars($reservation['receipt_path']); ?>" target="_blank" class="mt-3 inline-flex text-sm font-black text-primary">View receipt</a>
                                <?php endif; ?>
                            </div>
                            <?php if ($canUpload): ?>
                                <form method="post" enctype="multipart/form-data" class="grid min-w-[240px] gap-2">
                                    <input type="hidden" name="reservation" value="<?php echo htmlspecialchars($reservation['id']); ?>">
                                    <input required type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" class="rounded-md border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-xs">
                                    <button class="btn btn-primary !py-2">Upload Proof</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
