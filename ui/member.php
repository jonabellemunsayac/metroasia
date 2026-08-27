<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$member = require_member();
$pdo = db();
$message = null;
$error = null;

function member_save_receipt(): ?string
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

function member_minutes_from_time(?string $time): int
{
    $parts = array_map('intval', explode(':', (string) $time));
    return (($parts[0] ?? 0) * 60) + ($parts[1] ?? 0);
}

function member_slot_end_minutes(?string $time): int
{
    $minutes = member_minutes_from_time($time);
    return $minutes === 0 ? 1440 : $minutes;
}

function member_format_time(?string $time): string
{
    $minutes = member_minutes_from_time($time);
    if ($minutes === 0) {
        return '12 MN';
    }
    $hour = intdiv($minutes, 60);
    $minute = $minutes % 60;
    $suffix = $hour >= 12 ? 'PM' : 'AM';
    $displayHour = $hour % 12 ?: 12;

    return $displayHour . ($minute > 0 ? ':' . str_pad((string) $minute, 2, '0', STR_PAD_LEFT) : '') . ' ' . $suffix;
}

function member_format_date(string $date): string
{
    return date('M j, Y', strtotime($date));
}

function member_format_day_date(string $date): string
{
    return date('D, M j, Y', strtotime($date));
}

function member_format_money(float $amount): string
{
    return '&#8369;' . number_format($amount, 0);
}

function member_status_badge_class(string $status): string
{
    return match ($status) {
        'Booked' => 'status-badge-booked',
        'Cancelled' => 'status-badge-cancelled',
        default => 'status-badge-pending',
    };
}

function member_reference_fallback(array $row): string
{
    $reference = trim((string) ($row['booking_reference'] ?? ''));
    return $reference !== '' ? $reference : 'BOOKING-' . (int) $row['id'];
}

function member_group_key(array $row): string
{
    return member_reference_fallback($row);
}

function member_group_reservations(array $rows): array
{
    $groups = [];

    foreach ($rows as $row) {
        $key = member_group_key($row);
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'reference' => member_reference_fallback($row),
                'ids' => [],
                'date' => (string) $row['date'],
                'sportSet' => [],
                'statusSet' => [],
                'paymentMethod' => (string) ($row['payment_method'] ?? ''),
                'receipt' => '',
                'amount' => 0.0,
                'createdAt' => (string) $row['created_at'],
                'courtGroups' => [],
                'endTimestamp' => 0,
                'startTimestamp' => PHP_INT_MAX,
            ];
        }

        $courtId = (int) $row['court'];
        $courtName = (string) ($row['court_name'] ?: 'Court ' . $courtId);
        $courtKey = (string) $courtId;
        if (!isset($groups[$key]['courtGroups'][$courtKey])) {
            $groups[$key]['courtGroups'][$courtKey] = [
                'courtId' => $courtId,
                'courtName' => $courtName,
                'slots' => [],
            ];
        }

        $startMinutes = member_minutes_from_time((string) $row['starts_at']);
        $endMinutes = member_slot_end_minutes((string) $row['ends_at']);
        $dayStart = strtotime((string) $row['date'] . ' 00:00:00') ?: time();
        $startTimestamp = $dayStart + ($startMinutes * 60);
        $endTimestamp = $dayStart + ($endMinutes * 60);

        $groups[$key]['ids'][] = (int) $row['id'];
        $groups[$key]['sportSet'][(string) $row['sport']] = true;
        $groups[$key]['statusSet'][(string) $row['status']] = true;
        $groups[$key]['amount'] += (float) $row['final_amount'];
        $groups[$key]['receipt'] = $groups[$key]['receipt'] ?: (string) ($row['receipt_path'] ?? '');
        $groups[$key]['createdAt'] = strcmp((string) $row['created_at'], $groups[$key]['createdAt']) < 0
            ? (string) $row['created_at']
            : $groups[$key]['createdAt'];
        $groups[$key]['startTimestamp'] = min($groups[$key]['startTimestamp'], $startTimestamp);
        $groups[$key]['endTimestamp'] = max($groups[$key]['endTimestamp'], $endTimestamp);
        $groups[$key]['courtGroups'][$courtKey]['slots'][] = [
            'start' => (string) $row['starts_at'],
            'end' => (string) $row['ends_at'],
            'startMinutes' => $startMinutes,
            'endMinutes' => $endMinutes,
        ];
    }

    foreach ($groups as &$group) {
        $statuses = array_keys($group['statusSet']);
        $group['status'] = count($statuses) === 1 ? $statuses[0] : implode(', ', $statuses);
        $sports = array_keys($group['sportSet']);
        $group['sport'] = count($sports) === 1 ? $sports[0] : implode(', ', $sports);

        foreach ($group['courtGroups'] as &$courtGroup) {
            usort($courtGroup['slots'], static fn (array $a, array $b): int => $a['startMinutes'] <=> $b['startMinutes']);
            $ranges = [];
            foreach ($courtGroup['slots'] as $slot) {
                if ($ranges === [] || end($ranges)['endMinutes'] !== $slot['startMinutes']) {
                    $ranges[] = $slot;
                    continue;
                }
                $last = array_key_last($ranges);
                $ranges[$last]['end'] = $slot['end'];
                $ranges[$last]['endMinutes'] = $slot['endMinutes'];
            }
            $courtGroup['ranges'] = array_map(static fn (array $range): string => member_format_time($range['start']) . ' - ' . member_format_time($range['end']), $ranges);
            $courtGroup['slotCount'] = count($courtGroup['slots']);
        }
        unset($courtGroup);
    }
    unset($group);

    uasort($groups, static fn (array $a, array $b): int => $b['startTimestamp'] <=> $a['startTimestamp']);
    return array_values($groups);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $reservationIds = array_values(array_filter(array_map('intval', explode(',', (string) ($_POST['reservationIds'] ?? '')))));
        $receipt = member_save_receipt();
        if (!$receipt || $reservationIds === []) {
            throw new RuntimeException('Choose a reservation and upload a valid receipt.');
        }

        $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
        $params = array_merge([$receipt], $reservationIds, [(int) $member['id']]);
        $stmt = $pdo->prepare(
            "UPDATE court_bookings
             SET receipt_path = ?, status = 'Held'
             WHERE id IN ({$placeholders}) AND member_id = ? AND status = 'Held'"
        );
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Reservation was not found or no longer accepts receipt upload.');
        }
        $message = 'Receipt uploaded. Admin will review your payment proof.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$memberStmt = $pdo->prepare(
    'SELECT id, name, nickname, email, phone, birth_month, birth_year, skill_level, member_lookup_token, profile_picture_path
     FROM members
     WHERE id = ? AND is_active = 1'
);
$memberStmt->execute([(int) $member['id']]);
$memberProfile = $memberStmt->fetch() ?: $member;

$reservationStmt = $pdo->prepare(
    "SELECT cb.id, cb.booking_reference, cb.booking_date AS date, cb.time_slot_id,
            ts.label AS time, ts.starts_at, ts.ends_at, cb.court_id AS court,
            c.name AS court_name, cb.sport, cb.status, cb.payment_method, cb.receipt_path,
            cb.final_amount, cb.created_at
     FROM court_bookings cb
     JOIN time_slots ts ON ts.id = cb.time_slot_id
     LEFT JOIN courts c ON c.id = cb.court_id
     WHERE cb.member_id = ?
     ORDER BY cb.booking_date DESC, ts.starts_at DESC, cb.id DESC"
);
$reservationStmt->execute([(int) $member['id']]);
$reservations = member_group_reservations($reservationStmt->fetchAll());

$now = time();
$upcomingReservations = array_values(array_filter(
    $reservations,
    static fn (array $group): bool => in_array($group['status'], ['Held', 'Booked'], true) && $group['endTimestamp'] >= $now
));
$playedReservations = array_values(array_filter(
    $reservations,
    static fn (array $group): bool => $group['status'] === 'Booked' && $group['endTimestamp'] < $now
));
$cancelledReservations = array_values(array_filter(
    $reservations,
    static fn (array $group): bool => $group['status'] === 'Cancelled'
));

$totalBookings = count($reservations);
$hoursPlayed = 0.0;
$totalSpent = 0.0;
foreach ($reservations as $group) {
    if ($group['status'] !== 'Booked' || $group['endTimestamp'] >= $now) {
        continue;
    }
    $totalSpent += (float) $group['amount'];
    foreach ($group['courtGroups'] as $courtGroup) {
        foreach ($courtGroup['slots'] as $slot) {
            $hoursPlayed += max(0, $slot['endMinutes'] - $slot['startMinutes']) / 60;
        }
    }
}

$tab = (string) ($_GET['tab'] ?? 'upcoming');
if (!in_array($tab, ['upcoming', 'played', 'cancelled'], true)) {
    $tab = 'upcoming';
}
$tabGroups = [
    'upcoming' => $upcomingReservations,
    'played' => $playedReservations,
    'cancelled' => $cancelledReservations,
];
$activeGroups = $tabGroups[$tab];
$nextReservation = $upcomingReservations[0] ?? null;

$pageTitle = 'My Bookings';
$active = 'member';
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page member-dashboard-page">
    <?php if ($message): ?>
        <div class="member-dashboard-alert is-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="member-dashboard-alert is-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section class="member-dashboard-summary public-card">
        <div>
            <p class="member-kicker">Member Dashboard</p>
            <h1>My Bookings</h1>
            <p>
                Hi, <?php echo htmlspecialchars(strtok((string) $memberProfile['name'], ' ') ?: (string) $memberProfile['name']); ?>.
                <?php if ($nextReservation): ?>
                    Next: <?php echo htmlspecialchars($nextReservation['sport']); ?> on <?php echo htmlspecialchars(member_format_day_date($nextReservation['date'])); ?>.
                <?php else: ?>
                    No upcoming bookings yet.
                <?php endif; ?>
            </p>
        </div>
        <a href="<?php echo htmlspecialchars(app_url('ui/member-profile.php')); ?>" class="member-summary-link">Edit profile</a>
    </section>

    <section class="member-stat-grid">
        <article class="member-stat-card public-card">
            <span>Upcoming</span>
            <strong><?php echo number_format(count($upcomingReservations)); ?></strong>
            <small>Active future reservations</small>
        </article>
        <article class="member-stat-card public-card">
            <span>Total Bookings</span>
            <strong><?php echo number_format($totalBookings); ?></strong>
            <small>Grouped by reference number</small>
        </article>
        <article class="member-stat-card public-card">
            <span>Hours Played</span>
            <strong><?php echo rtrim(rtrim(number_format($hoursPlayed, 1), '0'), '.'); ?></strong>
            <small>Past booked court hours</small>
        </article>
        <article class="member-stat-card public-card">
            <span>Total Spent</span>
            <strong><?php echo member_format_money($totalSpent); ?></strong>
            <small>Past booked reservations</small>
        </article>
    </section>

    <section class="member-dashboard-grid member-dashboard-grid-single">
        <section class="member-booking-history public-card">
            <div class="member-section-head">
                <div>
                    <p class="member-kicker">Reservations</p>
                    <h2>Booking History</h2>
                </div>
            </div>

            <nav class="member-booking-tabs" aria-label="Booking history tabs">
                <a class="<?php echo $tab === 'upcoming' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(app_url('ui/member.php?tab=upcoming')); ?>">
                    Upcoming <span><?php echo count($upcomingReservations); ?></span>
                </a>
                <a class="<?php echo $tab === 'played' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(app_url('ui/member.php?tab=played')); ?>">
                    Played <span><?php echo count($playedReservations); ?></span>
                </a>
                <a class="<?php echo $tab === 'cancelled' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(app_url('ui/member.php?tab=cancelled')); ?>">
                    Cancelled <span><?php echo count($cancelledReservations); ?></span>
                </a>
            </nav>

            <div class="member-booking-list">
                <?php if ($activeGroups === []): ?>
                    <article class="member-empty-card">
                        <h3>
                            <?php echo $tab === 'upcoming' ? 'No upcoming games' : ($tab === 'played' ? 'No completed bookings yet.' : 'No cancelled bookings.'); ?>
                        </h3>
                        <p><?php echo $tab === 'upcoming' ? 'Find a court and book your next session.' : 'Your booking history will appear here once available.'; ?></p>
                    </article>
                <?php endif; ?>

                <?php foreach ($activeGroups as $group): ?>
                    <article class="member-booking-card">
                        <div class="member-booking-main">
                            <div>
                                <span class="member-reference"><?php echo htmlspecialchars($group['reference']); ?></span>
                                <h3><?php echo htmlspecialchars($group['sport']); ?></h3>
                                <p><?php echo htmlspecialchars(member_format_day_date($group['date'])); ?></p>
                            </div>
                            <span class="status-badge <?php echo member_status_badge_class((string) $group['status']); ?>">
                                <?php echo htmlspecialchars((string) $group['status']); ?>
                            </span>
                        </div>

                        <div class="member-booking-details">
                            <?php foreach ($group['courtGroups'] as $courtGroup): ?>
                                <div>
                                    <dt><?php echo htmlspecialchars($courtGroup['courtName']); ?></dt>
                                    <dd><?php echo htmlspecialchars(implode(', ', $courtGroup['ranges'])); ?></dd>
                                </div>
                            <?php endforeach; ?>
                            <div>
                                <dt>Payment</dt>
                                <dd><?php echo htmlspecialchars($group['paymentMethod'] ?: 'N/A'); ?></dd>
                            </div>
                            <div>
                                <dt>Amount</dt>
                                <dd><?php echo member_format_money((float) $group['amount']); ?></dd>
                            </div>
                        </div>

                        <div class="member-booking-actions">
                            <?php if ($group['receipt']): ?>
                                <a href="<?php echo htmlspecialchars(app_url((string) $group['receipt'])); ?>" target="_blank" rel="noopener">View receipt</a>
                            <?php endif; ?>
                            <?php if ($group['status'] === 'Held'): ?>
                                <form method="post" enctype="multipart/form-data" class="member-receipt-form">
                                    <input type="hidden" name="reservationIds" value="<?php echo htmlspecialchars(implode(',', $group['ids'])); ?>">
                                    <input required type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                    <button class="btn btn-primary btn-sm">Upload Proof</button>
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
