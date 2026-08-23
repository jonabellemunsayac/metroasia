<?php
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin_menu('admin-reports');
$pageTitle = 'Reports';
$active = 'admin-reports';
$pdo = db();
$today = date('Y-m-d');
$startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-13 days'));
$endDate = $_GET['end'] ?? $today;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $startDate)) {
    $startDate = date('Y-m-d', strtotime('-13 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $endDate)) {
    $endDate = $today;
}
if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$memberStats = $pdo->query(
    'SELECT COUNT(*) AS total_members,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_members
     FROM members'
)->fetch() ?: ['total_members' => 0, 'active_members' => 0];

$todayBookingsStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM (
        SELECT id FROM court_bookings WHERE booking_date = ? AND status IN ('Held','Booked')
        UNION ALL
        SELECT opr.id FROM open_play_reservations opr
        JOIN open_play_sessions ops ON ops.id = opr.session_id
        WHERE ops.session_date = ? AND opr.status IN ('Held','Booked')
    ) daily_bookings"
);
$todayBookingsStmt->execute([$today, $today]);
$todayBookings = (int) $todayBookingsStmt->fetchColumn();

$todayFeesStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM member_entrance_fee_payments WHERE payment_date = ?');
$todayFeesStmt->execute([$today]);
$todayFees = (float) $todayFeesStmt->fetchColumn();

$rangeFeesStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM member_entrance_fee_payments WHERE payment_date BETWEEN ? AND ?');
$rangeFeesStmt->execute([$startDate, $endDate]);
$rangeFees = (float) $rangeFeesStmt->fetchColumn();

$bookingRowsStmt = $pdo->prepare(
    "SELECT report_date,
            SUM(bookings_count) AS bookings_count,
            SUM(held_count) AS held_count,
            SUM(booked_count) AS booked_count,
            SUM(cancelled_count) AS cancelled_count
     FROM (
        SELECT booking_date AS report_date,
               COUNT(*) AS bookings_count,
               SUM(CASE WHEN status = 'Held' THEN 1 ELSE 0 END) AS held_count,
               SUM(CASE WHEN status = 'Booked' THEN 1 ELSE 0 END) AS booked_count,
               SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_count
        FROM court_bookings
        WHERE booking_date BETWEEN ? AND ?
        GROUP BY booking_date
        UNION ALL
        SELECT ops.session_date AS report_date,
               COUNT(*) AS bookings_count,
               SUM(CASE WHEN opr.status = 'Held' THEN 1 ELSE 0 END) AS held_count,
               SUM(CASE WHEN opr.status = 'Booked' THEN 1 ELSE 0 END) AS booked_count,
               SUM(CASE WHEN opr.status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_count
        FROM open_play_reservations opr
        JOIN open_play_sessions ops ON ops.id = opr.session_id
        WHERE ops.session_date BETWEEN ? AND ?
        GROUP BY ops.session_date
     ) daily
     GROUP BY report_date
     ORDER BY report_date DESC"
);
$bookingRowsStmt->execute([$startDate, $endDate, $startDate, $endDate]);
$bookingRows = $bookingRowsStmt->fetchAll();

$feeRowsStmt = $pdo->prepare(
    'SELECT payment_date, COUNT(*) AS fee_count, COALESCE(SUM(amount), 0) AS fee_total
     FROM member_entrance_fee_payments
     WHERE payment_date BETWEEN ? AND ?
     GROUP BY payment_date'
);
$feeRowsStmt->execute([$startDate, $endDate]);
$feeRows = [];
foreach ($feeRowsStmt->fetchAll() as $row) {
    $feeRows[(string) $row['payment_date']] = $row;
}

$dailyRows = [];
$cursor = new DateTimeImmutable($endDate);
$stop = new DateTimeImmutable($startDate);
$bookingByDate = [];
foreach ($bookingRows as $row) {
    $bookingByDate[(string) $row['report_date']] = $row;
}
while ($cursor >= $stop) {
    $date = $cursor->format('Y-m-d');
    $dailyRows[] = [
        'date' => $date,
        'bookings' => (int) ($bookingByDate[$date]['bookings_count'] ?? 0),
        'held' => (int) ($bookingByDate[$date]['held_count'] ?? 0),
        'booked' => (int) ($bookingByDate[$date]['booked_count'] ?? 0),
        'cancelled' => (int) ($bookingByDate[$date]['cancelled_count'] ?? 0),
        'feeCount' => (int) ($feeRows[$date]['fee_count'] ?? 0),
        'feeTotal' => (float) ($feeRows[$date]['fee_total'] ?? 0),
    ];
    $cursor = $cursor->modify('-1 day');
}

function report_money(float $amount): string
{
    return 'PHP ' . number_format($amount, 2);
}

include __DIR__ . '/../includes/header.php';
?>
<main class="app-main admin-compact">
    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="section-kicker">Reports</span>
                <h2 class="mt-1 mb-1 fw-black">Executive summary</h2>
                <p class="mb-0 small text-secondary fw-semibold">Daily booking counts, entrance-fee collection, and member totals.</p>
            </div>
            <form class="d-flex flex-wrap align-items-end gap-2" method="get">
                <label class="small fw-bold">Start
                    <input type="date" name="start" class="form-input form-input-sm mt-1" value="<?php echo htmlspecialchars($startDate); ?>">
                </label>
                <label class="small fw-bold">End
                    <input type="date" name="end" class="form-input form-input-sm mt-1" value="<?php echo htmlspecialchars($endDate); ?>">
                </label>
                <button class="btn btn-primary btn-sm" type="submit">Apply</button>
            </form>
        </div>
    </section>

    <section class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="stat-card h-100">
                <p class="mb-1 small text-secondary fw-bold text-uppercase">Bookings Today</p>
                <p class="mb-0 stat-number"><?php echo number_format($todayBookings); ?></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card h-100">
                <p class="mb-1 small text-secondary fw-bold text-uppercase">Entrance Fees Today</p>
                <p class="mb-0 stat-number"><?php echo htmlspecialchars(report_money($todayFees)); ?></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card h-100">
                <p class="mb-1 small text-secondary fw-bold text-uppercase">Members</p>
                <p class="mb-0 stat-number"><?php echo number_format((int) $memberStats['active_members']); ?></p>
                <p class="mb-0 small text-secondary fw-semibold"><?php echo number_format((int) $memberStats['total_members']); ?> total records</p>
            </div>
        </div>
    </section>

    <section class="app-card p-0">
        <div class="card-header bg-white border-bottom p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <span class="section-kicker">Daily Report</span>
                    <h2 class="mt-1 mb-0 fw-black">Bookings and entrance fees</h2>
                </div>
                <span class="badge text-bg-primary"><?php echo htmlspecialchars($startDate); ?> to <?php echo htmlspecialchars($endDate); ?></span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 admin-bookings-table">
                <thead>
                    <tr class="small text-secondary">
                        <th>Date</th>
                        <th class="text-center">Bookings</th>
                        <th class="text-center">Held</th>
                        <th class="text-center">Booked</th>
                        <th class="text-center">Cancelled</th>
                        <th class="text-center">Entrance Fee Count</th>
                        <th class="text-end">Entrance Fees Collected</th>
                    </tr>
                </thead>
                <tbody class="small fw-semibold">
                    <?php foreach ($dailyRows as $row): ?>
                        <tr>
                            <td class="fw-black text-ink"><?php echo htmlspecialchars(date('D, M j, Y', strtotime($row['date']))); ?></td>
                            <td class="text-center"><?php echo number_format($row['bookings']); ?></td>
                            <td class="text-center"><?php echo number_format($row['held']); ?></td>
                            <td class="text-center"><?php echo number_format($row['booked']); ?></td>
                            <td class="text-center"><?php echo number_format($row['cancelled']); ?></td>
                            <td class="text-center"><?php echo number_format($row['feeCount']); ?></td>
                            <td class="text-end fw-black"><?php echo htmlspecialchars(report_money($row['feeTotal'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-black">
                        <td colspan="6" class="text-end">Range entrance-fee total</td>
                        <td class="text-end"><?php echo htmlspecialchars(report_money($rangeFees)); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
