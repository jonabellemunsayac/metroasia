<?php
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin_menu('admin-reports');
$pageTitle = 'Reports';
$active = 'admin-reports';
$pdo = db();
$reportTimezone = new DateTimeZone('Asia/Manila');
$todayDate = new DateTimeImmutable('now', $reportTimezone);
$today = $todayDate->format('Y-m-d');
$defaultStartDate = $todayDate->modify('-13 days')->format('Y-m-d');
$startDate = $_GET['start'] ?? $defaultStartDate;
$endDate = $_GET['end'] ?? $today;
$breakdown = strtolower((string) ($_GET['breakdown'] ?? 'daily'));
$breakdownOptions = [
    'hourly' => 'Hourly',
    'daily' => 'Daily',
    'weekly' => 'Weekly',
    'monthly' => 'Monthly',
];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $startDate)) {
    $startDate = $defaultStartDate;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $endDate)) {
    $endDate = $today;
}
if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}
if (!array_key_exists($breakdown, $breakdownOptions)) {
    $breakdown = 'daily';
}

$periodExpressions = [
    'hourly' => [
        'select' => "STR_TO_DATE(CONCAT(ef.payment_date, ' ', LPAD(HOUR(ef.payment_time), 2, '0'), ':00:00'), '%Y-%m-%d %H:%i:%s')",
        'order' => "STR_TO_DATE(CONCAT(ef.payment_date, ' ', LPAD(HOUR(ef.payment_time), 2, '0'), ':00:00'), '%Y-%m-%d %H:%i:%s')",
    ],
    'daily' => [
        'select' => 'ef.payment_date',
        'order' => 'ef.payment_date',
    ],
    'weekly' => [
        'select' => 'DATE_SUB(ef.payment_date, INTERVAL WEEKDAY(ef.payment_date) DAY)',
        'order' => 'DATE_SUB(ef.payment_date, INTERVAL WEEKDAY(ef.payment_date) DAY)',
    ],
    'monthly' => [
        'select' => "DATE_FORMAT(ef.payment_date, '%Y-%m-01')",
        'order' => "DATE_FORMAT(ef.payment_date, '%Y-%m-01')",
    ],
];
$periodSelect = $periodExpressions[$breakdown]['select'];
$periodOrder = $periodExpressions[$breakdown]['order'];

$totalBookingsStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM (
        SELECT id FROM court_bookings
        WHERE booking_date BETWEEN ? AND ? AND status IN ('Held','Booked')
        UNION ALL
        SELECT opr.id FROM open_play_reservations opr
        JOIN open_play_sessions ops ON ops.id = opr.session_id
        WHERE ops.session_date BETWEEN ? AND ? AND opr.status IN ('Held','Booked')
    ) range_bookings"
);
$totalBookingsStmt->execute([$startDate, $endDate, $startDate, $endDate]);
$totalBookings = (int) $totalBookingsStmt->fetchColumn();

$paymentSummaryStmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT member_id) AS total_players,
            COALESCE(SUM(amount), 0) AS total_cash_payment
     FROM member_entrance_fee_payments
     WHERE payment_date BETWEEN ? AND ?"
);
$paymentSummaryStmt->execute([$startDate, $endDate]);
$paymentSummary = $paymentSummaryStmt->fetch() ?: ['total_players' => 0, 'total_cash_payment' => 0];

$playerPaymentsStmt = $pdo->prepare(
    "SELECT {$periodSelect} AS period_key,
            m.name AS player_name,
            COALESCE(SUM(ef.amount), 0) AS total_payment
     FROM member_entrance_fee_payments ef
     JOIN members m ON m.id = ef.member_id
     WHERE ef.payment_date BETWEEN ? AND ?
     GROUP BY period_key, ef.member_id, m.name
     ORDER BY {$periodOrder} DESC, total_payment DESC, m.name ASC"
);
$playerPaymentsStmt->execute([$startDate, $endDate]);
$playerPayments = $playerPaymentsStmt->fetchAll();

function report_money(float $amount): string
{
    return 'PHP ' . number_format($amount, 2);
}

function report_period_label(string $value, string $breakdown): string
{
    if ($value === '') {
        return 'N/A';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    if ($breakdown === 'hourly') {
        return date('M j, Y g:00 A', $timestamp);
    }
    if ($breakdown === 'weekly') {
        return date('M j', $timestamp) . ' - ' . date('M j, Y', strtotime('+6 days', $timestamp));
    }
    if ($breakdown === 'monthly') {
        return date('F Y', $timestamp);
    }

    return date('M j, Y', $timestamp);
}

include __DIR__ . '/../includes/header.php';
?>
<main class="app-main admin-compact">
    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="section-kicker">Reports</span>
                <h2 class="mt-1 mb-1 fw-black">Executive summary</h2>
                <p class="mb-0 small text-secondary fw-semibold">Booking count, player count, and cash collection for the selected range.</p>
            </div>
            <form class="d-flex flex-wrap align-items-end gap-2" method="get">
                <label class="small fw-bold">Breakdown
                    <select name="breakdown" class="form-select form-select-sm mt-1">
                        <?php foreach ($breakdownOptions as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $breakdown === $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
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
                <p class="mb-1 small text-secondary fw-bold text-uppercase">Total Bookings</p>
                <p class="mb-0 stat-number"><?php echo number_format($totalBookings); ?></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card h-100">
                <p class="mb-1 small text-secondary fw-bold text-uppercase">Total Players</p>
                <p class="mb-0 stat-number"><?php echo number_format((int) $paymentSummary['total_players']); ?></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card h-100">
                <p class="mb-1 small text-secondary fw-bold text-uppercase">Total Cash Payment</p>
                <p class="mb-0 stat-number"><?php echo htmlspecialchars(report_money((float) $paymentSummary['total_cash_payment'])); ?></p>
            </div>
        </div>
    </section>

    <section class="app-card p-0">
        <div class="card-header bg-white border-bottom p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <span class="section-kicker">Players</span>
                    <h2 class="mt-1 mb-0 fw-black"><?php echo htmlspecialchars($breakdownOptions[$breakdown]); ?> player payment totals</h2>
                </div>
                <span class="badge text-bg-primary"><?php echo htmlspecialchars($startDate); ?> to <?php echo htmlspecialchars($endDate); ?></span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 admin-bookings-table">
                <thead>
                    <tr class="small text-secondary">
                        <th>Period</th>
                        <th>Player Name</th>
                        <th class="text-end">Total Payment</th>
                    </tr>
                </thead>
                <tbody class="small fw-semibold">
                    <?php if ($playerPayments === []): ?>
                        <tr>
                            <td colspan="3" class="text-center text-secondary py-4">No player payments found for the selected date range.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($playerPayments as $row): ?>
                            <tr>
                                <td class="fw-semibold text-secondary"><?php echo htmlspecialchars(report_period_label((string) $row['period_key'], $breakdown)); ?></td>
                                <td class="fw-black text-ink"><?php echo htmlspecialchars((string) $row['player_name']); ?></td>
                                <td class="text-end fw-black"><?php echo htmlspecialchars(report_money((float) $row['total_payment'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
