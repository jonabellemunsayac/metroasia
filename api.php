<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/site-config.php';

header('Access-Control-Allow-Origin: *');

const RESERVATION_STATUSES = [
    'Available',
    'Pending Payment',
    'Held',
    'Booked',
];
const BLOCKING_RESERVATION_STATUS_SQL = "'Pending Payment','Held','Booked'";

$receiptUploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'receipts';
$paymentUploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'payment';
foreach ([$receiptUploadDir, $paymentUploadDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function db_or_error(): PDO
{
    try {
        return db();
    } catch (Throwable $exception) {
        json_response([
            'ok' => false,
            'message' => 'Database is not ready. Open setup.php first, then try again.',
            'detail' => $exception->getMessage(),
        ], 503);
    }
}

function require_admin_json(): array
{
    $admin = current_admin();
    if ($admin === null) {
        json_response(['ok' => false, 'message' => 'Admin login required.'], 401);
    }
    return $admin;
}

function require_field(string $key): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($value === '') {
        json_response(['ok' => false, 'message' => ucfirst($key) . ' is required.'], 422);
    }
    return $value;
}

function slot_is_past(string $date, array $slot): bool
{
    $startsAt = (string) ($slot['starts_at'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}/', $startsAt)) {
        return true;
    }

    $slotStart = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $startsAt);
    if (!$slotStart) {
        return true;
    }

    return $slotStart <= new DateTimeImmutable('now');
}

function save_receipt(string $uploadDir): ?string
{
    if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        json_response(['ok' => false, 'message' => 'Receipt upload failed.'], 422);
    }

    if ($_FILES['receipt']['size'] > 5 * 1024 * 1024) {
        json_response(['ok' => false, 'message' => 'Receipt must be 5MB or smaller.'], 422);
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
        json_response(['ok' => false, 'message' => 'Use a JPG, PNG, WEBP, or PDF receipt.'], 422);
    }

    $filename = 'receipt-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $target)) {
        json_response(['ok' => false, 'message' => 'Could not save receipt.'], 500);
    }

    return 'uploads/receipts/' . $filename;
}

function save_payment_qr(string $uploadDir): ?string
{
    if (!isset($_FILES['qrFile']) || $_FILES['qrFile']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES['qrFile']['error'] !== UPLOAD_ERR_OK) {
        json_response(['ok' => false, 'message' => 'QR upload failed.'], 422);
    }

    if ($_FILES['qrFile']['size'] > 5 * 1024 * 1024) {
        json_response(['ok' => false, 'message' => 'QR image must be 5MB or smaller.'], 422);
    }

    $tmp = $_FILES['qrFile']['tmp_name'];
    $mime = mime_content_type($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    if (!isset($allowed[$mime])) {
        json_response(['ok' => false, 'message' => 'Use a JPG, PNG, WEBP, or SVG QR image.'], 422);
    }

    $filename = 'payment-qr-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $target)) {
        json_response(['ok' => false, 'message' => 'Could not save QR image.'], 500);
    }

    return 'uploads/payment/' . $filename;
}

function payment_channels(PDO $pdo, bool $includeInactive = false): array
{
    $where = $includeInactive
        ? "WHERE code IN ('GCash', 'BDO')"
        : "WHERE is_active = 1 AND code IN ('GCash', 'BDO')";
    $stmt = $pdo->query(
        "SELECT id, code, name, channel_type, account_name, account_number, bank_name,
                instructions, qr_path, is_active, sort_order
         FROM payment_channels
         {$where}
         ORDER BY sort_order, name"
    );

    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'code' => $row['code'],
        'name' => $row['name'],
        'type' => $row['channel_type'],
        'accountName' => $row['account_name'] ?? '',
        'accountNumber' => $row['account_number'] ?? '',
        'bankName' => $row['bank_name'] ?? '',
        'instructions' => $row['instructions'] ?? '',
        'qrPath' => $row['qr_path'] ?? '',
        'isActive' => (bool) $row['is_active'],
        'sortOrder' => (int) $row['sort_order'],
    ], $stmt->fetchAll());
}

function require_active_payment_channel(PDO $pdo, string $code): void
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM payment_channels WHERE code = ? AND is_active = 1');
    $stmt->execute([$code]);
    if ((int) $stmt->fetchColumn() === 0) {
        json_response(['ok' => false, 'message' => 'Invalid or inactive payment channel.'], 422);
    }
}

function active_status_sql(): string
{
    return BLOCKING_RESERVATION_STATUS_SQL;
}

function booking_status_for_receipt(?string $receipt): string
{
    return $receipt ? 'Held' : 'Pending Payment';
}

function rate_snapshot(array $source, float $amount, string $kind = 'court'): string
{
    return json_encode([
        'kind' => $kind,
        'baseRate' => $amount,
        'finalAmount' => $amount,
        'durationHours' => 1,
        'discount' => 0,
        'manualOverride' => null,
        'appliedAt' => date(DATE_ATOM),
        'source' => $source,
    ], JSON_THROW_ON_ERROR);
}

function day_type_for_date(string $date): string
{
    $day = (int) (new DateTimeImmutable($date))->format('N');
    return $day >= 6 ? 'Weekend' : 'Weekday';
}

function day_name_for_date(string $date): string
{
    return (new DateTimeImmutable($date))->format('l');
}

function day_type_from_pattern(string $pattern): string
{
    return match ($pattern) {
        'Weekday', 'Monday-Friday' => 'Weekday',
        'Weekend', 'Saturday-Sunday' => 'Weekend',
        default => 'Any',
    };
}

function day_pattern_matches_date(?string $pattern, string $date): bool
{
    $pattern = trim((string) ($pattern ?: 'Any'));
    if ($pattern === '' || strcasecmp($pattern, 'Any') === 0) {
        return true;
    }

    $dayName = day_name_for_date($date);
    $dayType = day_type_for_date($date);
    if (strcasecmp($pattern, $dayType) === 0) {
        return true;
    }

    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $dayIndex = array_search($dayName, $days, true);
    foreach (array_map('trim', explode(',', $pattern)) as $part) {
        if ($part === '') {
            continue;
        }
        if (strcasecmp($part, $dayName) === 0 || strcasecmp($part, $dayType) === 0) {
            return true;
        }
        if (str_contains($part, '-')) {
            [$start, $end] = array_map('trim', explode('-', $part, 2));
            $startIndex = array_search(ucfirst(strtolower($start)), $days, true);
            $endIndex = array_search(ucfirst(strtolower($end)), $days, true);
            if ($startIndex !== false && $endIndex !== false) {
                if ($startIndex <= $endIndex && $dayIndex >= $startIndex && $dayIndex <= $endIndex) {
                    return true;
                }
                if ($startIndex > $endIndex && ($dayIndex >= $startIndex || $dayIndex <= $endIndex)) {
                    return true;
                }
            }
        }
    }

    return false;
}

function duration_hours(string $startsAt, string $endsAt): float
{
    $start = strtotime('2000-01-01 ' . $startsAt);
    $end = strtotime('2000-01-01 ' . $endsAt);
    if ($end <= $start) {
        $end += 86400;
    }
    return max(0.25, ($end - $start) / 3600);
}

function rate_rules(PDO $pdo, bool $includeInactive = false): array
{
    $stmt = $pdo->query(
        "SELECT r.id, r.court_id, c.name AS court_name, r.sport, r.time_slot_id,
                ts.label AS time_label, ts.starts_at, ts.ends_at,
                r.rate_per_hour, r.updated_at
         FROM rates r
         JOIN courts c ON c.id = r.court_id
         JOIN time_slots ts ON ts.id = r.time_slot_id
         ORDER BY c.display_number, c.id, r.sport, ts.sort_order, ts.id"
    );

    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'name' => trim(($row['court_name'] ?? 'Court') . ' ' . $row['sport'] . ' ' . $row['time_label']),
        'courtId' => (int) $row['court_id'],
        'courtName' => $row['court_name'] ?? 'Court ' . $row['court_id'],
        'sport' => $row['sport'],
        'timeSlotId' => (int) $row['time_slot_id'],
        'timeLabel' => $row['time_label'],
        'dayType' => 'Any',
        'dayPattern' => 'Any',
        'startsAt' => substr((string) $row['starts_at'], 0, 5),
        'endsAt' => substr((string) $row['ends_at'], 0, 5),
        'durationMinutes' => null,
        'durationLabel' => 'Time slot',
        'pricePerHour' => (float) $row['rate_per_hour'],
        'memberPricePerHour' => null,
        'effectiveFrom' => null,
        'effectiveTo' => null,
        'priority' => 0,
        'isActive' => true,
        'changeReason' => '',
        'updatedAt' => date(DATE_ATOM, strtotime($row['updated_at'])),
    ], $stmt->fetchAll());
}

function calculate_booking_rate(PDO $pdo, int $courtId, string $sport, string $date, array $slot, bool $isMember): array
{
    $duration = duration_hours((string) $slot['starts_at'], (string) $slot['ends_at']);
    $stmt = $pdo->prepare(
        "SELECT r.id, r.rate_per_hour, ts.label AS time_label
         FROM rates r
         JOIN time_slots ts ON ts.id = r.time_slot_id
         WHERE r.court_id = ? AND r.sport = ? AND r.time_slot_id = ?
         LIMIT 1"
    );
    $stmt->execute([$courtId, $sport, (int) $slot['id']]);
    $rate = $stmt->fetch();

    if ($rate) {
        $baseRate = (float) $rate['rate_per_hour'];
        return [
            'baseRate' => $baseRate,
            'finalAmount' => $baseRate * $duration,
            'durationHours' => $duration,
            'ruleId' => (int) $rate['id'],
            'ruleName' => 'Rate for ' . $rate['time_label'],
            'memberApplied' => false,
        ];
    }

    $baseRate = (float) $slot['price'];
    return [
        'baseRate' => $baseRate,
        'finalAmount' => $baseRate * $duration,
        'durationHours' => $duration,
        'ruleId' => null,
        'ruleName' => 'Time slot fallback',
        'memberApplied' => false,
    ];
}

function booking_rate_snapshot(array $source, array $rate, string $kind = 'court'): string
{
    return json_encode([
        'kind' => $kind,
        'baseRate' => $rate['baseRate'],
        'finalAmount' => $rate['finalAmount'],
        'durationHours' => $rate['durationHours'],
        'ruleId' => $rate['ruleId'],
        'ruleName' => $rate['ruleName'],
        'memberApplied' => $rate['memberApplied'],
        'discount' => max(0, ($rate['baseRate'] * $rate['durationHours']) - $rate['finalAmount']),
        'manualOverride' => null,
        'appliedAt' => date(DATE_ATOM),
        'source' => $source,
    ], JSON_THROW_ON_ERROR);
}

function public_court_name(int $courtId, string $sport): string
{
    return match ($courtId) {
        1 => 'Lakers',
        2 => 'Miami',
        3 => 'Pickleball Pro Court 1',
        4 => 'Pickleball Pro Court 2',
        5 => 'Pickleball Pro Court 3',
        6 => 'Pickleball Pro Court 4',
        7 => 'Wooden Court 5',
        8 => 'Wooden Court 6',
        9 => 'Wooden Court 7',
        default => 'Court ' . $courtId,
    };
}

function block_scope_court_name(?int $courtId, ?string $sport): string
{
    if ($courtId === null) {
        return 'All courts';
    }

    if ($sport === null) {
        return match ($courtId) {
            1 => 'Lakers',
            2 => 'Miami',
            default => public_court_name($courtId, 'Pickleball'),
        };
    }

    return public_court_name($courtId, $sport);
}

function court_blocks(PDO $pdo, bool $includeCancelled = false): array
{
    $where = $includeCancelled ? '' : "WHERE cb.status = 'Active'";
    $stmt = $pdo->query(
        "SELECT cb.id, cb.block_date, cb.time_slot_id, ts.label AS time_label, cb.court_id,
                cb.sport, cb.reason, cb.notes, cb.status, cb.created_at, cb.cancelled_at,
                creator.name AS created_by_name, canceller.name AS cancelled_by_name
         FROM court_blocks cb
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         LEFT JOIN admin_users creator ON creator.id = cb.created_by
         LEFT JOIN admin_users canceller ON canceller.id = cb.cancelled_by
         {$where}
         ORDER BY cb.block_date DESC, ts.sort_order, cb.id DESC
         LIMIT 80"
    );

    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'date' => $row['block_date'],
        'timeSlotId' => (int) $row['time_slot_id'],
        'time' => $row['time_label'],
        'courtId' => $row['court_id'] !== null ? (int) $row['court_id'] : null,
        'courtName' => block_scope_court_name($row['court_id'] !== null ? (int) $row['court_id'] : null, $row['sport'] !== null ? (string) $row['sport'] : null),
        'sport' => $row['sport'],
        'reason' => $row['reason'],
        'notes' => $row['notes'] ?? '',
        'status' => $row['status'],
        'createdByName' => $row['created_by_name'] ?? 'System',
        'cancelledByName' => $row['cancelled_by_name'],
        'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
        'cancelledAt' => $row['cancelled_at'] ? date(DATE_ATOM, strtotime($row['cancelled_at'])) : null,
    ], $stmt->fetchAll());
}

function court_block_applies(?int $blockCourtId, ?string $blockSport, int $courtId, string $sport): bool
{
    if ($blockCourtId === null) {
        return true;
    }

    if ($blockCourtId === $courtId) {
        return $blockSport === null || $blockSport === $sport || in_array($courtId, [1, 2], true);
    }

    return false;
}

function active_block_conflict(PDO $pdo, string $date, int $slotId, int $courtId, string $sport): ?array
{
    $stmt = $pdo->prepare(
        "SELECT cb.id, cb.court_id, cb.sport, cb.reason, cb.notes, ts.label AS time_label
         FROM court_blocks cb
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         WHERE cb.block_date = ? AND cb.time_slot_id = ? AND cb.status = 'Active'"
    );
    $stmt->execute([$date, $slotId]);

    foreach ($stmt->fetchAll() as $row) {
        $blockCourtId = $row['court_id'] !== null ? (int) $row['court_id'] : null;
        $blockSport = $row['sport'] !== null ? (string) $row['sport'] : null;
        if (!court_block_applies($blockCourtId, $blockSport, $courtId, $sport)) {
            continue;
        }

        $courtName = block_scope_court_name($blockCourtId, $blockSport);
        $scope = $blockSport !== null ? "{$courtName} {$blockSport}" : $courtName;
        $notes = trim((string) ($row['notes'] ?? ''));
        return [
            'message' => "{$scope} is blocked for {$row['reason']} during {$row['time_label']}." . ($notes !== '' ? " {$notes}" : ''),
            'blockingCourt' => $courtName,
            'blockingSport' => $blockSport,
            'status' => 'Blocked',
            'blockId' => (int) $row['id'],
        ];
    }

    return null;
}

function active_court_conflict(PDO $pdo, string $date, int $slotId, int $courtId, string $sport, ?int $excludeBookingId = null): ?array
{
    $excludeSql = $excludeBookingId !== null ? 'AND cb.id <> ?' : '';

    $direct = $pdo->prepare(
        "SELECT cb.id, cb.court_id, cb.sport, cb.status, c.name AS court_name, ts.label AS time_label
         FROM court_bookings cb
         JOIN courts c ON c.id = cb.court_id
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         WHERE cb.booking_date = ? AND cb.time_slot_id = ? AND cb.court_id = ?
           AND cb.status IN (" . BLOCKING_RESERVATION_STATUS_SQL . ") {$excludeSql}
         LIMIT 1"
    );
    $params = [$date, $slotId, $courtId];
    if ($excludeBookingId !== null) {
        $params[] = $excludeBookingId;
    }
    $direct->execute($params);
    $row = $direct->fetch();
    if ($row) {
        $name = public_court_name((int) $row['court_id'], (string) $row['sport']);
        return [
            'message' => "{$name} is already {$row['status']} for {$row['sport']} during {$row['time_label']}.",
            'blockingCourt' => $name,
            'blockingSport' => $row['sport'],
            'status' => $row['status'],
        ];
    }

    $block = active_block_conflict($pdo, $date, $slotId, $courtId, $sport);
    if ($block !== null) {
        return $block;
    }

    return null;
}

function active_bookings_for_block(PDO $pdo, string $date, int $slotId, ?int $courtId, ?string $sport): array
{
    $stmt = $pdo->prepare(
        "SELECT cb.id, cb.court_id, cb.sport, cb.status, cb.customer_name, ts.label AS time_label
         FROM court_bookings cb
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         WHERE cb.booking_date = ? AND cb.time_slot_id = ?
           AND cb.status IN (" . BLOCKING_RESERVATION_STATUS_SQL . ")"
    );
    $stmt->execute([$date, $slotId]);

    $matches = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!court_block_applies($courtId, $sport, (int) $row['court_id'], (string) $row['sport'])) {
            continue;
        }

        $courtName = public_court_name((int) $row['court_id'], (string) $row['sport']);
        $matches[] = [
            'id' => (int) $row['id'],
            'summary' => "#{$row['id']} {$courtName} {$row['sport']} {$row['status']} for {$row['customer_name']} ({$row['time_label']})",
        ];
    }

    return $matches;
}

function active_bookings_for_booking(PDO $pdo, string $date, int $slotId, int $courtId, string $sport): array
{
    $stmt = $pdo->prepare(
        "SELECT cb.id, cb.court_id, cb.sport, cb.status, cb.customer_name, ts.label AS time_label
         FROM court_bookings cb
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         WHERE cb.booking_date = ? AND cb.time_slot_id = ?
           AND cb.status IN (" . BLOCKING_RESERVATION_STATUS_SQL . ")"
    );
    $stmt->execute([$date, $slotId]);

    $matches = [];
    foreach ($stmt->fetchAll() as $row) {
        $existingCourt = (int) $row['court_id'];
        $existingSport = (string) $row['sport'];
        if ($existingCourt !== $courtId) {
            continue;
        }

        $courtName = public_court_name($existingCourt, $existingSport);
        $matches[] = [
            'id' => (int) $row['id'],
            'courtId' => $existingCourt,
            'courtName' => $courtName,
            'sport' => $existingSport,
            'status' => $row['status'],
            'customerName' => $row['customer_name'],
            'time' => $row['time_label'],
            'summary' => "{$courtName} is currently reserved for {$existingSport} from {$row['time_label']} ({$row['status']}, {$row['customer_name']}).",
        ];
    }

    return $matches;
}

function active_blocks_for_booking(PDO $pdo, string $date, int $slotId, int $courtId, string $sport): array
{
    $stmt = $pdo->prepare(
        "SELECT cb.id, cb.court_id, cb.sport, cb.reason, cb.notes, ts.label AS time_label
         FROM court_blocks cb
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         WHERE cb.block_date = ? AND cb.time_slot_id = ? AND cb.status = 'Active'"
    );
    $stmt->execute([$date, $slotId]);

    $matches = [];
    foreach ($stmt->fetchAll() as $row) {
        $blockCourtId = $row['court_id'] !== null ? (int) $row['court_id'] : null;
        $blockSport = $row['sport'] !== null ? (string) $row['sport'] : null;
        if (!court_block_applies($blockCourtId, $blockSport, $courtId, $sport)) {
            continue;
        }

        $courtName = block_scope_court_name($blockCourtId, $blockSport);
        $scope = $blockSport ? "{$courtName} {$blockSport}" : $courtName;
        $matches[] = [
            'id' => (int) $row['id'],
            'summary' => "{$scope} is blocked for {$row['reason']} from {$row['time_label']}.",
        ];
    }

    return $matches;
}

function get_state(PDO $pdo, bool $includeAdmin = false): array
{
    $courts = $pdo->query('SELECT id, display_number, name, court_type, surface_label, supported_sports FROM courts WHERE is_active = 1 ORDER BY display_number, id')->fetchAll();
    $rates = $pdo->query(
        "SELECT CAST(r.rate_per_hour AS UNSIGNED) AS price,
                CONCAT(DATE_FORMAT(MIN(ts.starts_at), '%l %p'), ' - ', DATE_FORMAT(MAX(ts.ends_at), '%l %p')) AS time
         FROM rates r
         JOIN time_slots ts ON ts.id = r.time_slot_id
         GROUP BY r.rate_per_hour
         ORDER BY MIN(ts.sort_order), r.rate_per_hour"
    )->fetchAll();
    $slotRows = $pdo->query('SELECT id, period, label, starts_at, ends_at, CAST(price AS UNSIGNED) AS price FROM time_slots ORDER BY sort_order, id')->fetchAll();

    $timeSlots = [];
    $slotDetails = [];
    foreach ($slotRows as $slot) {
        $timeSlots[$slot['period']][] = $slot['label'];
        $slotDetails[$slot['label']] = [
            'id' => (int) $slot['id'],
            'period' => $slot['period'],
            'label' => $slot['label'],
            'startsAt' => substr((string) $slot['starts_at'], 0, 5),
            'endsAt' => substr((string) $slot['ends_at'], 0, 5),
            'price' => (float) $slot['price'],
        ];
    }

    $bookings = [];
    $stmt = $pdo->query(
        "SELECT cb.id, cb.member_id, cb.booking_date, ts.label AS time_label, cb.court_id, cb.sport, cb.status,
                cb.customer_name, cb.customer_email, cb.customer_phone, cb.payment_method,
                cb.receipt_path, cb.base_rate, cb.final_amount, cb.created_at
         FROM court_bookings cb
         JOIN courts c ON c.id = cb.court_id AND c.is_active = 1
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         WHERE cb.status IN (" . BLOCKING_RESERVATION_STATUS_SQL . ")"
    );
    foreach ($stmt->fetchAll() as $row) {
        $bookings['court-' . $row['id']] = [
            'id' => 'court:' . $row['id'],
            'type' => 'court',
            'memberId' => $row['member_id'] !== null ? (int) $row['member_id'] : null,
            'date' => $row['booking_date'],
            'time' => $row['time_label'],
            'court' => (int) $row['court_id'],
            'sport' => $row['sport'],
            'status' => $row['status'],
            'baseRate' => (float) $row['base_rate'],
            'finalAmount' => (float) $row['final_amount'],
            'customerName' => $row['customer_name'],
            'customerEmail' => $row['customer_email'] ?? '',
            'customerPhone' => $row['customer_phone'] ?? '',
            'paymentMethod' => $row['payment_method'],
            'receipt' => $row['receipt_path'],
            'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
        ];
    }

    $openPlays = [];
    $stmt = $pdo->query(
        'SELECT id, title, session_date, session_time, CAST(price AS UNSIGNED) AS price, capacity, level_label, description
         FROM open_play_sessions
         WHERE is_active = 1
         ORDER BY session_date, id'
    );
    foreach ($stmt->fetchAll() as $row) {
        $openPlays[] = [
            'id' => (string) $row['id'],
            'title' => $row['title'],
            'date' => $row['session_date'],
            'time' => $row['session_time'],
            'price' => (int) $row['price'],
            'capacity' => (int) $row['capacity'],
            'level' => $row['level_label'],
            'description' => $row['description'],
        ];
    }

    $openPlayReservations = [];
    $stmt = $pdo->query(
        "SELECT opr.id, opr.member_id, opr.session_id, ops.title, ops.session_date, ops.session_time, opr.status,
                opr.customer_name, opr.customer_email, opr.customer_phone, opr.payment_method,
                opr.receipt_path, opr.final_amount, opr.created_at
         FROM open_play_reservations opr
         JOIN open_play_sessions ops ON ops.id = opr.session_id
         WHERE opr.status IN (" . BLOCKING_RESERVATION_STATUS_SQL . ")"
    );
    foreach ($stmt->fetchAll() as $row) {
        $openPlayReservations[] = [
            'id' => 'openplay:' . $row['id'],
            'type' => 'openplay',
            'memberId' => $row['member_id'] !== null ? (int) $row['member_id'] : null,
            'sessionId' => (string) $row['session_id'],
            'sessionTitle' => $row['title'],
            'date' => $row['session_date'],
            'time' => $row['session_time'],
            'status' => $row['status'],
            'finalAmount' => (float) $row['final_amount'],
            'customerName' => $row['customer_name'],
            'customerEmail' => $row['customer_email'] ?? '',
            'customerPhone' => $row['customer_phone'] ?? '',
            'paymentMethod' => $row['payment_method'],
            'receipt' => $row['receipt_path'],
            'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
        ];
    }

    $siteConfig = site_config($pdo);
    $state = [
        'venue' => [
            'name' => $siteConfig['venue_name'] ?? 'Metro Asia',
            'location' => $siteConfig['address'] ?? '',
            'courts' => 7,
            'currency' => 'PHP',
        ],
        'courts' => array_map(static fn (array $court): array => [
            'id' => (int) $court['id'],
            'number' => (int) $court['display_number'],
            'name' => $court['name'],
            'type' => $court['court_type'],
            'surface' => $court['surface_label'],
            'sports' => array_values(array_filter(array_map('trim', explode(',', $court['supported_sports'])))),
            'labels' => [
                'Pickleball' => public_court_name((int) $court['id'], 'Pickleball'),
                'Basketball' => public_court_name((int) $court['id'], 'Basketball'),
                'Volleyball' => public_court_name((int) $court['id'], 'Volleyball'),
            ],
        ], $courts),
        'rates' => $rates,
        'rateRules' => rate_rules($pdo, false),
        'timeSlots' => $timeSlots,
        'slotDetails' => $slotDetails,
        'reservationStatuses' => RESERVATION_STATUSES,
        'blockingStatuses' => ['Pending Payment', 'Held', 'Booked'],
        'permanentOccupancyStatus' => 'Booked',
        'bookings' => $bookings,
        'courtBlocks' => court_blocks($pdo, false),
        'openPlays' => $openPlays,
        'openPlayReservations' => $openPlayReservations,
        'paymentChannels' => payment_channels($pdo, false),
        'siteConfig' => $siteConfig,
    ];

    if ($includeAdmin) {
        $state['adminReservations'] = admin_reservations($pdo);
        $state['adminPaymentChannels'] = payment_channels($pdo, true);
        $state['adminRateRules'] = rate_rules($pdo, true);
        $state['adminRateAudit'] = rate_audit_logs($pdo);
        $state['adminCourtBlocks'] = court_blocks($pdo, true);
        $state['adminOverrideLogs'] = override_logs($pdo);
        $state['adminMembers'] = admin_members($pdo);
        $state['adminUsers'] = admin_users_list($pdo);
    }

    $member = current_member();
    if ($member !== null) {
        $state['member'] = [
            'id' => (int) $member['id'],
            'name' => $member['name'],
            'email' => $member['email'],
            'phone' => $member['phone'],
        ];
        $state['memberReservations'] = member_reservations($pdo, (int) $member['id']);
    }

    return $state;
}

function member_reservations(PDO $pdo, int $memberId): array
{
    $courtRows = $pdo->prepare(
        "SELECT CONCAT('court:', cb.id) AS id, 'court' AS type, cb.booking_date AS date,
                ts.label AS time, cb.court_id AS court, cb.sport, NULL AS session_title,
                cb.status, cb.payment_method, cb.receipt_path, cb.final_amount, cb.created_at
         FROM court_bookings cb
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         WHERE cb.member_id = ?"
    );
    $courtRows->execute([$memberId]);

    $openRows = $pdo->prepare(
        "SELECT CONCAT('openplay:', opr.id) AS id, 'openplay' AS type, ops.session_date AS date,
                ops.session_time AS time, NULL AS court, 'Open Play' AS sport, ops.title AS session_title,
                opr.status, opr.payment_method, opr.receipt_path, opr.final_amount, opr.created_at
         FROM open_play_reservations opr
         JOIN open_play_sessions ops ON ops.id = opr.session_id
         WHERE opr.member_id = ?"
    );
    $openRows->execute([$memberId]);

    $rows = array_merge($courtRows->fetchAll(), $openRows->fetchAll());
    usort($rows, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));

    return array_map(static fn (array $row): array => [
        'id' => $row['id'],
        'type' => $row['type'],
        'date' => $row['date'],
        'time' => $row['time'],
        'court' => $row['court'] !== null ? (int) $row['court'] : null,
        'courtName' => $row['court'] !== null ? public_court_name((int) $row['court'], (string) $row['sport']) : null,
        'sport' => $row['sport'],
        'sessionTitle' => $row['session_title'],
        'status' => $row['status'],
        'paymentMethod' => $row['payment_method'],
        'receipt' => $row['receipt_path'],
        'finalAmount' => (float) $row['final_amount'],
        'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
    ], $rows);
}

function rate_audit_logs(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT ral.id, ral.rate_id,
                CONCAT(COALESCE(c.name, 'Deleted rate'), ' ', COALESCE(r.sport, ''), ' ', COALESCE(ts.label, '')) AS rate_name,
                au.name AS admin_name,
                ral.action, ral.reason, ral.created_at
         FROM rate_audit_logs ral
         LEFT JOIN rates r ON r.id = ral.rate_id
         LEFT JOIN courts c ON c.id = r.court_id
         LEFT JOIN time_slots ts ON ts.id = r.time_slot_id
         LEFT JOIN admin_users au ON au.id = ral.admin_id
         ORDER BY ral.created_at DESC, ral.id DESC
         LIMIT 12"
    );

    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'ruleId' => $row['rate_id'] !== null ? (int) $row['rate_id'] : null,
        'ruleName' => trim($row['rate_name']) ?: 'Deleted rate',
        'adminName' => $row['admin_name'] ?? 'System',
        'action' => $row['action'],
        'reason' => $row['reason'] ?? '',
        'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
    ], $stmt->fetchAll());
}

function override_logs(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT ol.id, ol.action, ol.target_type, ol.target_id, ol.conflict_summary,
                ol.created_at, au.name AS admin_name
         FROM override_logs ol
         LEFT JOIN admin_users au ON au.id = ol.admin_id
         ORDER BY ol.created_at DESC, ol.id DESC
         LIMIT 12"
    );

    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'action' => $row['action'],
        'targetType' => $row['target_type'],
        'targetId' => $row['target_id'],
        'conflictSummary' => $row['conflict_summary'] ?? '',
        'adminName' => $row['admin_name'] ?? 'System',
        'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
    ], $stmt->fetchAll());
}

function admin_members(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT m.id, m.name, m.email, m.phone, m.is_active, m.last_login_at, m.created_at,
                COUNT(DISTINCT cb.id) AS court_bookings_count,
                COUNT(DISTINCT CASE WHEN cb.status = 'Booked' THEN cb.id END) AS confirmed_court_count
         FROM members m
         LEFT JOIN court_bookings cb ON cb.member_id = m.id
         GROUP BY m.id, m.name, m.email, m.phone, m.is_active, m.last_login_at, m.created_at
         ORDER BY m.created_at DESC, m.id DESC"
    );

    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'isActive' => (bool) $row['is_active'],
        'lastLoginAt' => $row['last_login_at'] ? date(DATE_ATOM, strtotime($row['last_login_at'])) : null,
        'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
        'courtBookingsCount' => (int) $row['court_bookings_count'],
        'confirmedCount' => (int) $row['confirmed_court_count'],
    ], $stmt->fetchAll());
}

function admin_users_list(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, name, email, role, is_active, last_login_at, created_at
         FROM admin_users
         ORDER BY is_active DESC, created_at DESC, id DESC"
    );

    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'role' => $row['role'],
        'isActive' => (bool) $row['is_active'],
        'lastLoginAt' => $row['last_login_at'] ? date(DATE_ATOM, strtotime($row['last_login_at'])) : null,
        'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
    ], $stmt->fetchAll());
}

function admin_reservations(PDO $pdo): array
{
    $courtRows = $pdo->query(
        "SELECT CONCAT('court:', cb.id) AS id, 'court' AS type, cb.booking_date AS date,
                ts.label AS time, cb.court_id AS court, cb.sport, NULL AS session_id, NULL AS session_title,
                cb.status, cb.customer_name, cb.customer_email, cb.customer_phone, cb.payment_method,
                cb.receipt_path, cb.final_amount, m.name AS member_name, cb.cancel_reason, cb.created_at, cb.reviewed_at, cb.cancelled_at,
                reviewer.name AS reviewed_by_name, canceller.name AS cancelled_by_name
         FROM court_bookings cb
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         LEFT JOIN members m ON m.id = cb.member_id
         LEFT JOIN admin_users reviewer ON reviewer.id = cb.reviewed_by
         LEFT JOIN admin_users canceller ON canceller.id = cb.cancelled_by"
    )->fetchAll();

    $openRows = $pdo->query(
        "SELECT CONCAT('openplay:', opr.id) AS id, 'openplay' AS type, ops.session_date AS date,
                ops.session_time AS time, NULL AS court, 'Open Play' AS sport, opr.session_id, ops.title AS session_title,
                opr.status, opr.customer_name, opr.customer_email, opr.customer_phone, opr.payment_method,
                opr.receipt_path, opr.final_amount, m.name AS member_name, opr.cancel_reason, opr.created_at, opr.reviewed_at, opr.cancelled_at,
                reviewer.name AS reviewed_by_name, canceller.name AS cancelled_by_name
         FROM open_play_reservations opr
         JOIN open_play_sessions ops ON ops.id = opr.session_id
         LEFT JOIN members m ON m.id = opr.member_id
         LEFT JOIN admin_users reviewer ON reviewer.id = opr.reviewed_by
         LEFT JOIN admin_users canceller ON canceller.id = opr.cancelled_by"
    )->fetchAll();

    $rows = array_merge($courtRows, $openRows);
    usort($rows, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));

    return array_map(static fn (array $row): array => [
        'id' => $row['id'],
        'type' => $row['type'],
        'date' => $row['date'],
        'time' => $row['time'],
        'court' => $row['court'] !== null ? (int) $row['court'] : null,
        'sport' => $row['sport'],
        'sessionId' => $row['session_id'] !== null ? (string) $row['session_id'] : null,
        'sessionTitle' => $row['session_title'],
        'status' => $row['status'],
        'customerName' => $row['customer_name'],
        'customerEmail' => $row['customer_email'] ?? '',
        'customerPhone' => $row['customer_phone'] ?? '',
        'paymentMethod' => $row['payment_method'],
        'receipt' => $row['receipt_path'],
        'finalAmount' => (float) $row['final_amount'],
        'memberName' => $row['member_name'],
        'cancelReason' => $row['cancel_reason'],
        'reviewedByName' => $row['reviewed_by_name'],
        'cancelledByName' => $row['cancelled_by_name'],
        'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
        'reviewedAt' => $row['reviewed_at'] ? date(DATE_ATOM, strtotime($row['reviewed_at'])) : null,
        'cancelledAt' => $row['cancelled_at'] ? date(DATE_ATOM, strtotime($row['cancelled_at'])) : null,
    ], $rows);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'state';
$pdo = db_or_error();

if ($action === 'state') {
    json_response(['ok' => true, 'state' => get_state($pdo, current_admin() !== null)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Unsupported request.'], 405);
}

if ($action === 'book') {
    $date = require_field('date');
    $time = require_field('time');
    $courtId = (int) require_field('court');
    $sport = $_POST['sport'] ?? 'Pickleball';
    $sport = trim((string) $sport) ?: 'Pickleball';
    $name = require_field('name');
    $phone = require_field('phone');
    $email = trim((string) ($_POST['email'] ?? ''));
    $bookingReference = trim((string) ($_POST['bookingReference'] ?? ''));
    if ($bookingReference === '' || preg_match('/^MA\d{6}-\d{6}-\d{4}$/', $bookingReference) !== 1) {
        $bookingReference = 'MA' . date('Ym-His') . '-0001';
    }
    $paymentMethod = require_field('paymentMethod');
    require_active_payment_channel($pdo, $paymentMethod);
    $receipt = save_receipt($receiptUploadDir);
    $member = current_member();
    if ($member !== null) {
        $name = (string) $member['name'];
        $phone = (string) $member['phone'];
        $email = (string) $member['email'];
    }

    $slotStmt = $pdo->prepare('SELECT id, label, starts_at, ends_at, price FROM time_slots WHERE label = ?');
    $slotStmt->execute([$time]);
    $slot = $slotStmt->fetch();
    $slotId = (int) ($slot['id'] ?? 0);
    if ($slotId === 0) {
        json_response(['ok' => false, 'message' => 'Invalid time slot.'], 422);
    }

    if (slot_is_past($date, $slot)) {
        json_response(['ok' => false, 'message' => 'Past dates and time slots cannot be booked.'], 422);
    }

    if (!in_array($sport, ['Pickleball', 'Basketball', 'Volleyball'], true)) {
        json_response(['ok' => false, 'message' => 'Invalid sport.'], 422);
    }

    $courtStmt = $pdo->prepare('SELECT supported_sports FROM courts WHERE id = ? AND is_active = 1');
    $courtStmt->execute([$courtId]);
    $supportedSports = (string) $courtStmt->fetchColumn();
    if ($supportedSports === '') {
        json_response(['ok' => false, 'message' => 'Invalid court.'], 422);
    }
    $supported = array_values(array_filter(array_map('trim', explode(',', $supportedSports))));
    if (!in_array($sport, $supported, true)) {
        json_response(['ok' => false, 'message' => "This court does not support {$sport} bookings."], 422);
    }

    $conflict = active_court_conflict($pdo, $date, $slotId, $courtId, $sport);
    if ($conflict !== null) {
        json_response(['ok' => false, 'message' => $conflict['message']], 409);
    }

    $rate = calculate_booking_rate($pdo, $courtId, $sport, $date, $slot, $member !== null);

    $stmt = $pdo->prepare(
        'INSERT INTO court_bookings
         (booking_reference, member_id, booking_date, time_slot_id, court_id, sport, status, customer_name, customer_email, customer_phone, payment_method, receipt_path, base_rate, final_amount, rate_snapshot)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $bookingReference,
        $member['id'] ?? null,
        $date,
        $slotId,
        $courtId,
        $sport,
        booking_status_for_receipt($receipt),
        $name,
        $email,
        $phone,
        $paymentMethod,
        $receipt,
        $rate['baseRate'],
        $rate['finalAmount'],
        booking_rate_snapshot(['timeSlot' => $time, 'sport' => $sport, 'courtId' => $courtId, 'date' => $date], $rate),
    ]);
    $bookingId = (int) $pdo->lastInsertId();

    json_response([
        'ok' => true,
        'message' => $receipt ? 'Booking is Held while admin reviews the receipt.' : 'Booking is Pending Payment. Send proof through Messenger or upload from your member account.',
        'bookingId' => $bookingId,
        'bookingReference' => $bookingReference,
        'state' => get_state($pdo, current_admin() !== null),
    ]);
}

if ($action === 'openplay') {
    $sessionId = (int) require_field('sessionId');
    $name = require_field('name');
    $phone = require_field('phone');
    $email = trim((string) ($_POST['email'] ?? ''));
    $paymentMethod = require_field('paymentMethod');
    require_active_payment_channel($pdo, $paymentMethod);
    $receipt = save_receipt($receiptUploadDir);
    $member = current_member();
    if ($member !== null) {
        $name = (string) $member['name'];
        $phone = (string) $member['phone'];
        $email = (string) $member['email'];
    }

    $stmt = $pdo->prepare('SELECT capacity, price, title, session_date, session_time FROM open_play_sessions WHERE id = ? AND is_active = 1');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    $capacity = (int) ($session['capacity'] ?? 0);
    if ($capacity === 0) {
        json_response(['ok' => false, 'message' => 'Open play session not found.'], 404);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM open_play_reservations WHERE session_id = ? AND status IN (" . BLOCKING_RESERVATION_STATUS_SQL . ")");
    $stmt->execute([$sessionId]);
    if ((int) $stmt->fetchColumn() >= $capacity) {
        json_response(['ok' => false, 'message' => 'This open play is full.'], 409);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO open_play_reservations
         (member_id, session_id, status, customer_name, customer_email, customer_phone, payment_method, receipt_path, final_amount, rate_snapshot)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $amount = (float) $session['price'];
    $stmt->execute([
        $member['id'] ?? null,
        $sessionId,
        booking_status_for_receipt($receipt),
        $name,
        $email,
        $phone,
        $paymentMethod,
        $receipt,
        $amount,
        rate_snapshot([
            'sessionId' => $sessionId,
            'title' => $session['title'],
            'date' => $session['session_date'],
            'time' => $session['session_time'],
        ], $amount, 'openplay'),
    ]);

    json_response([
        'ok' => true,
        'message' => $receipt ? 'Open play spot is Held while admin reviews the receipt.' : 'Open play spot is Pending Payment until proof is sent or uploaded.',
        'state' => get_state($pdo, current_admin() !== null),
    ]);
}

if ($action === 'admin-status') {
    $admin = require_admin_json();
    $id = require_field('id');
    $status = require_field('status');
    $allowed = RESERVATION_STATUSES;

    if (!in_array($status, $allowed, true)) {
        json_response(['ok' => false, 'message' => 'Invalid status.'], 422);
    }

    [$type, $rawId] = array_pad(explode(':', $id, 2), 2, '');
    $reservationId = (int) $rawId;
    if ($reservationId <= 0 || !in_array($type, ['court', 'openplay'], true)) {
        json_response(['ok' => false, 'message' => 'Invalid reservation id.'], 422);
    }

    $table = $type === 'court' ? 'court_bookings' : 'open_play_reservations';
    $nextStatus = $status;

    $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id = ?");
    $existsStmt->execute([$reservationId]);
    if ((int) $existsStmt->fetchColumn() === 0) {
        json_response(['ok' => false, 'message' => 'Reservation not found.'], 404);
    }

    if (in_array($nextStatus, ['Pending Payment', 'Held', 'Booked'], true)) {
        if ($type === 'court') {
            $stmt = $pdo->prepare('SELECT booking_date, time_slot_id, court_id, sport FROM court_bookings WHERE id = ?');
            $stmt->execute([$reservationId]);
            $booking = $stmt->fetch();
            $conflict = active_court_conflict(
                $pdo,
                (string) $booking['booking_date'],
                (int) $booking['time_slot_id'],
                (int) $booking['court_id'],
                (string) $booking['sport'],
                $reservationId
            );
            if ($conflict !== null) {
                json_response(['ok' => false, 'message' => $conflict['message']], 409);
            }
        } else {
            $stmt = $pdo->prepare(
                'SELECT opr.session_id, ops.capacity
                 FROM open_play_reservations opr
                 JOIN open_play_sessions ops ON ops.id = opr.session_id
                 WHERE opr.id = ?'
            );
            $stmt->execute([$reservationId]);
            $reservation = $stmt->fetch();
            $active = $pdo->prepare("SELECT COUNT(*) FROM open_play_reservations WHERE session_id = ? AND status IN (" . BLOCKING_RESERVATION_STATUS_SQL . ") AND id <> ?");
            $active->execute([$reservation['session_id'], $reservationId]);
            if ((int) $active->fetchColumn() >= (int) $reservation['capacity']) {
                json_response(['ok' => false, 'message' => 'That open play session is already full.'], 409);
            }
        }
    }

    if ($nextStatus === 'Booked') {
        $stmt = $pdo->prepare("UPDATE {$table} SET status = 'Booked', reviewed_by = ?, reviewed_at = NOW(), cancelled_by = NULL, cancelled_at = NULL, cancel_reason = NULL WHERE id = ?");
        $stmt->execute([(int) $admin['id'], $reservationId]);
    } elseif ($nextStatus === 'Available') {
        $reason = trim((string) ($_POST['reason'] ?? 'Marked available by admin'));
        $stmt = $pdo->prepare("UPDATE {$table} SET status = ?, cancelled_by = ?, cancelled_at = NOW(), cancel_reason = ? WHERE id = ?");
        $stmt->execute([$nextStatus, (int) $admin['id'], $reason, $reservationId]);
    } else {
        $stmt = $pdo->prepare("UPDATE {$table} SET status = ?, reviewed_by = NULL, reviewed_at = NULL, cancelled_by = NULL, cancelled_at = NULL, cancel_reason = NULL WHERE id = ?");
        $stmt->execute([$nextStatus, $reservationId]);
    }

    json_response([
        'ok' => true,
        'message' => $nextStatus === 'Booked' ? 'Reservation marked Booked.' : "Reservation marked {$nextStatus}.",
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-override-booking') {
    $admin = require_admin_json();

    $date = require_field('date');
    $timeSlotId = (int) require_field('timeSlotId');
    $courtId = (int) require_field('courtId');
    $sport = require_field('sport');
    $name = require_field('name');
    $phone = require_field('phone');
    $email = trim((string) ($_POST['email'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? 'Booked')) ?: 'Booked';
    $paymentMethod = trim((string) ($_POST['paymentMethod'] ?? 'Admin Override')) ?: 'Admin Override';
    $reason = trim((string) ($_POST['overrideReason'] ?? 'Admin override'));
    $overrideConfirm = isset($_POST['overrideConfirm']) && $_POST['overrideConfirm'] === '1';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok' => false, 'message' => 'Use a valid booking date.'], 422);
    }
    if (!in_array($sport, ['Pickleball', 'Basketball', 'Volleyball'], true)) {
        json_response(['ok' => false, 'message' => 'Invalid sport.'], 422);
    }
    if (!in_array($status, ['Pending Payment', 'Held', 'Booked'], true)) {
        json_response(['ok' => false, 'message' => 'Invalid override booking status.'], 422);
    }

    $slotStmt = $pdo->prepare('SELECT id, label, starts_at, ends_at, price FROM time_slots WHERE id = ?');
    $slotStmt->execute([$timeSlotId]);
    $slot = $slotStmt->fetch();
    if (!$slot) {
        json_response(['ok' => false, 'message' => 'Invalid time slot.'], 422);
    }

    if (slot_is_past($date, $slot)) {
        json_response(['ok' => false, 'message' => 'Past dates and time slots cannot be booked.'], 422);
    }

    $courtStmt = $pdo->prepare('SELECT supported_sports FROM courts WHERE id = ? AND is_active = 1');
    $courtStmt->execute([$courtId]);
    $supportedSports = (string) $courtStmt->fetchColumn();
    if ($supportedSports === '') {
        json_response(['ok' => false, 'message' => 'Invalid court.'], 422);
    }
    $supported = array_values(array_filter(array_map('trim', explode(',', $supportedSports))));
    if (!in_array($sport, $supported, true)) {
        json_response(['ok' => false, 'message' => "This court does not support {$sport} bookings."], 422);
    }

    $bookingConflicts = active_bookings_for_booking($pdo, $date, $timeSlotId, $courtId, $sport);
    $blockConflicts = active_blocks_for_booking($pdo, $date, $timeSlotId, $courtId, $sport);
    $conflictSummaries = array_merge(array_column($bookingConflicts, 'summary'), array_column($blockConflicts, 'summary'));

    if ($conflictSummaries !== [] && !$overrideConfirm) {
        json_response([
            'ok' => false,
            'requiresOverride' => true,
            'message' => "Resource Conflict\n\n" . implode("\n\n", $conflictSummaries) . "\n\nBooking " . public_court_name($courtId, $sport) . " for {$sport} will conflict with this reservation.\n\nCancel conflicting reservation and continue?",
            'conflicts' => array_merge($bookingConflicts, $blockConflicts),
        ], 409);
    }

    $rate = calculate_booking_rate($pdo, $courtId, $sport, $date, $slot, false);
    $pdo->beginTransaction();
    try {
        if ($bookingConflicts !== []) {
            $cancel = $pdo->prepare(
                "UPDATE court_bookings
                 SET status = 'Available', cancelled_by = ?, cancelled_at = NOW(), cancel_reason = ?
                 WHERE id = ?"
            );
            foreach ($bookingConflicts as $conflict) {
                $cancel->execute([(int) $admin['id'], 'Released by admin override: ' . $reason, (int) $conflict['id']]);
            }
        }

        if ($blockConflicts !== []) {
            $cancelBlock = $pdo->prepare(
                "UPDATE court_blocks
                 SET status = 'Cancelled', cancelled_by = ?, cancelled_at = NOW()
                 WHERE id = ?"
            );
            foreach ($blockConflicts as $conflict) {
                $cancelBlock->execute([(int) $admin['id'], (int) $conflict['id']]);
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO court_bookings
             (booking_date, time_slot_id, court_id, sport, status, customer_name, customer_email, customer_phone, payment_method, base_rate, final_amount, rate_snapshot, reviewed_by, reviewed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = "Booked" THEN NOW() ELSE NULL END)'
        );
        $stmt->execute([
            $date,
            $timeSlotId,
            $courtId,
            $sport,
            $status,
            $name,
            $email,
            $phone,
            $paymentMethod,
            $rate['baseRate'],
            $rate['finalAmount'],
            booking_rate_snapshot(['timeSlot' => $slot['label'], 'sport' => $sport, 'courtId' => $courtId, 'date' => $date, 'override' => true], $rate),
            $status === 'Booked' ? (int) $admin['id'] : null,
            $status,
        ]);
        $bookingId = (int) $pdo->lastInsertId();

        if ($conflictSummaries !== [] || $overrideConfirm) {
            $audit = $pdo->prepare(
                'INSERT INTO override_logs (admin_id, action, target_type, target_id, conflict_summary, payload)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $audit->execute([
                (int) $admin['id'],
                'admin-booking-override',
                'court_booking',
                (string) $bookingId,
                implode('; ', $conflictSummaries),
                json_encode([
                    'bookingId' => $bookingId,
                    'date' => $date,
                    'timeSlotId' => $timeSlotId,
                    'time' => $slot['label'],
                    'courtId' => $courtId,
                    'sport' => $sport,
                    'status' => $status,
                    'reason' => $reason,
                    'cancelledBookings' => $bookingConflicts,
                    'cancelledBlocks' => $blockConflicts,
                ], JSON_THROW_ON_ERROR),
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        json_response(['ok' => false, 'message' => 'Override booking failed: ' . $exception->getMessage()], 500);
    }

    json_response([
        'ok' => true,
        'message' => $conflictSummaries !== [] ? 'Override booking saved. Conflicts were cancelled and logged.' : 'Admin booking saved.',
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-rate-rule') {
    $admin = require_admin_json();

    $id = (int) ($_POST['id'] ?? 0);
    $courtId = (int) require_field('courtId');
    $sport = require_field('sport');
    $timeSlotId = (int) require_field('timeSlotId');
    $pricePerHour = (float) require_field('pricePerHour');
    $reason = trim((string) ($_POST['reason'] ?? 'Regular rate'));
    if ($reason === '') {
        $reason = 'Regular rate';
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM courts WHERE id = ? AND is_active = 1');
    $stmt->execute([$courtId]);
    if ((int) $stmt->fetchColumn() === 0) {
        json_response(['ok' => false, 'message' => 'Invalid court.'], 422);
    }
    if (!in_array($sport, ['Pickleball', 'Basketball', 'Volleyball'], true)) {
        json_response(['ok' => false, 'message' => 'Invalid sport.'], 422);
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM time_slots WHERE id = ?');
    $stmt->execute([$timeSlotId]);
    if ((int) $stmt->fetchColumn() === 0) {
        json_response(['ok' => false, 'message' => 'Invalid time slot.'], 422);
    }
    if ($pricePerHour <= 0) {
        json_response(['ok' => false, 'message' => 'Rate per hour must be greater than zero.'], 422);
    }

    $duplicate = $pdo->prepare(
        'SELECT id FROM rates
         WHERE court_id = ?
           AND sport = ?
           AND time_slot_id = ?
           AND id <> ?
         LIMIT 1'
    );
    $duplicate->execute([$courtId, $sport, $timeSlotId, $id]);
    if ($duplicate->fetch()) {
        json_response(['ok' => false, 'message' => 'Duplicate rate found for the same court, sport, and time slot. Delete or edit the existing rate first.'], 409);
    }

    $previous = null;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM rates WHERE id = ?');
        $stmt->execute([$id]);
        $previous = $stmt->fetch();
        if (!$previous) {
            json_response(['ok' => false, 'message' => 'Rate not found.'], 404);
        }

        $stmt = $pdo->prepare(
            'UPDATE rates
             SET court_id = ?, sport = ?, time_slot_id = ?, rate_per_hour = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $courtId, $sport, $timeSlotId, $pricePerHour, $id,
        ]);
        $actionName = 'updated';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO rates
             (court_id, sport, time_slot_id, rate_per_hour)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $courtId, $sport, $timeSlotId, $pricePerHour,
        ]);
        $id = (int) $pdo->lastInsertId();
        $actionName = 'created';
    }

    $stmt = $pdo->prepare('SELECT * FROM rates WHERE id = ?');
    $stmt->execute([$id]);
    $current = $stmt->fetch();

    $audit = $pdo->prepare(
        'INSERT INTO rate_audit_logs (rate_id, admin_id, action, previous_payload, new_payload, reason)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $audit->execute([
        $id,
        (int) $admin['id'],
        $actionName,
        $previous ? json_encode($previous, JSON_THROW_ON_ERROR) : null,
        json_encode($current, JSON_THROW_ON_ERROR),
        $reason,
    ]);

    json_response([
        'ok' => true,
        'message' => 'Rate saved.',
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-rate-delete') {
    $admin = require_admin_json();
    $id = (int) require_field('id');

    $stmt = $pdo->prepare('SELECT * FROM rates WHERE id = ?');
    $stmt->execute([$id]);
    $previous = $stmt->fetch();
    if (!$previous) {
        json_response(['ok' => false, 'message' => 'Rate not found.'], 404);
    }

    $delete = $pdo->prepare('DELETE FROM rates WHERE id = ?');
    $delete->execute([$id]);

    $audit = $pdo->prepare(
        'INSERT INTO rate_audit_logs (rate_id, admin_id, action, previous_payload, new_payload, reason)
         VALUES (NULL, ?, ?, ?, NULL, ?)'
    );
    $audit->execute([
        (int) $admin['id'],
        'deleted',
        json_encode($previous, JSON_THROW_ON_ERROR),
        'Rate deleted from admin rate management.',
    ]);

    json_response([
        'ok' => true,
        'message' => 'Rate deleted.',
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-court-block') {
    $admin = require_admin_json();

    $id = (int) ($_POST['id'] ?? 0);
    $blockDate = require_field('blockDate');
    $timeSlotId = (int) require_field('timeSlotId');
    $courtIdRaw = trim((string) ($_POST['courtId'] ?? ''));
    $courtId = $courtIdRaw === '' ? null : (int) $courtIdRaw;
    $sportRaw = trim((string) ($_POST['sport'] ?? ''));
    $sport = $sportRaw === '' ? null : $sportRaw;
    $reason = require_field('reason');
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $isActive = isset($_POST['isActive']) && $_POST['isActive'] === '1';
    $overrideConfirm = isset($_POST['overrideConfirm']) && $_POST['overrideConfirm'] === '1';
    $allowedReasons = ['Maintenance', 'Private event', 'Tournament', 'Cleaning', 'Construction', 'Club activity'];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $blockDate)) {
        json_response(['ok' => false, 'message' => 'Use a valid block date.'], 422);
    }
    if (!in_array($reason, $allowedReasons, true)) {
        json_response(['ok' => false, 'message' => 'Invalid block reason.'], 422);
    }
    if ($sport !== null && !in_array($sport, ['Pickleball', 'Basketball', 'Volleyball'], true)) {
        json_response(['ok' => false, 'message' => 'Invalid sport.'], 422);
    }
    $allowedScopes = [
        '1|' => 'Lakers',
        '2|' => 'Miami',
        '3|Pickleball' => 'Pickleball Pro Court 1',
        '4|Pickleball' => 'Pickleball Pro Court 2',
        '5|Pickleball' => 'Pickleball Pro Court 3',
        '6|Pickleball' => 'Pickleball Pro Court 4',
        '7|Pickleball' => 'Wooden Court 5',
        '8|Pickleball' => 'Wooden Court 6',
        '9|Pickleball' => 'Wooden Court 7',
    ];
    $scopeKey = ($courtId ?? '') . '|' . ($sport ?? '');
    if (!isset($allowedScopes[$scopeKey])) {
        json_response(['ok' => false, 'message' => 'Invalid block scope. Choose Lakers, Miami, a Pickleball Pro Court, or a Wooden Court.'], 422);
    }
    if ($courtId !== null) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM courts WHERE id = ? AND is_active = 1');
        $stmt->execute([$courtId]);
        if ((int) $stmt->fetchColumn() === 0) {
            json_response(['ok' => false, 'message' => 'Invalid court.'], 422);
        }
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM time_slots WHERE id = ?');
    $stmt->execute([$timeSlotId]);
    if ((int) $stmt->fetchColumn() === 0) {
        json_response(['ok' => false, 'message' => 'Invalid time slot.'], 422);
    }

    $conflicts = $isActive ? active_bookings_for_block($pdo, $blockDate, $timeSlotId, $courtId, $sport) : [];
    if ($conflicts !== [] && !$overrideConfirm) {
        json_response([
            'ok' => false,
            'requiresOverride' => true,
            'message' => 'This block overlaps active reservations: ' . implode('; ', array_column($conflicts, 'summary')),
            'conflicts' => $conflicts,
        ], 409);
    }

    $status = $isActive ? 'Active' : 'Cancelled';
    $cancelledBy = $isActive ? null : (int) $admin['id'];

    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM court_blocks WHERE id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() === 0) {
            json_response(['ok' => false, 'message' => 'Court block not found.'], 404);
        }

        $stmt = $pdo->prepare(
            'UPDATE court_blocks
             SET block_date = ?, time_slot_id = ?, court_id = ?, sport = ?, reason = ?, notes = ?,
                 status = ?, cancelled_by = ?, cancelled_at = CASE WHEN ? = \'Cancelled\' THEN NOW() ELSE NULL END
             WHERE id = ?'
        );
        $stmt->execute([$blockDate, $timeSlotId, $courtId, $sport, $reason, $notes, $status, $cancelledBy, $status, $id]);
        $message = $isActive ? 'Court block updated.' : 'Court block cancelled.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO court_blocks
             (block_date, time_slot_id, court_id, sport, reason, notes, status, created_by, cancelled_by, cancelled_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = \'Cancelled\' THEN NOW() ELSE NULL END)'
        );
        $stmt->execute([$blockDate, $timeSlotId, $courtId, $sport, $reason, $notes, $status, (int) $admin['id'], $cancelledBy, $status]);
        $id = (int) $pdo->lastInsertId();
        $message = $isActive ? 'Court block created.' : 'Cancelled block record saved.';
    }

    if ($conflicts !== [] && $overrideConfirm) {
        $summary = implode('; ', array_column($conflicts, 'summary'));
        $stmt = $pdo->prepare(
            'INSERT INTO override_logs (admin_id, action, target_type, target_id, conflict_summary, payload)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $admin['id'],
            'court-block-override',
            'court_block',
            (string) $id,
            $summary,
            json_encode([
                'blockDate' => $blockDate,
                'timeSlotId' => $timeSlotId,
                'courtId' => $courtId,
                'sport' => $sport,
                'reason' => $reason,
                'notes' => $notes,
                'conflicts' => $conflicts,
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    json_response([
        'ok' => true,
        'message' => $message,
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-payment-channel') {
    require_admin_json();

    $id = (int) ($_POST['id'] ?? 0);
    $code = preg_replace('/[^A-Za-z0-9_-]/', '', require_field('code'));
    $name = require_field('name');
    $accountName = trim((string) ($_POST['accountName'] ?? ''));
    $accountNumber = trim((string) ($_POST['accountNumber'] ?? ''));
    $bankName = trim((string) ($_POST['bankName'] ?? ''));
    $instructions = trim((string) ($_POST['instructions'] ?? ''));
    $sortOrder = (int) ($_POST['sortOrder'] ?? 0);

    if ($code === '') {
        json_response(['ok' => false, 'message' => 'Channel code is required.'], 422);
    }
    if (!in_array($code, ['GCash', 'BDO'], true)) {
        json_response(['ok' => false, 'message' => 'Only GCash and BDO payment channels can be configured.'], 422);
    }
    $type = $code === 'GCash' ? 'qr' : 'bank';

    $qrPath = save_payment_qr($paymentUploadDir);
    $isActive = 1;

    if ($id > 0) {
        $existing = $pdo->prepare("SELECT code, qr_path FROM payment_channels WHERE id = ? AND code IN ('GCash', 'BDO')");
        $existing->execute([$id]);
        $existingChannel = $existing->fetch();
        if (!$existingChannel) {
            json_response(['ok' => false, 'message' => 'Payment channel not found.'], 404);
        }
        if ($code !== $existingChannel['code']) {
            json_response(['ok' => false, 'message' => 'Payment channel code cannot be changed.'], 422);
        }

        $stmt = $pdo->prepare(
            'UPDATE payment_channels
             SET code = ?, name = ?, channel_type = ?, account_name = ?, account_number = ?,
                 bank_name = ?, instructions = ?, qr_path = ?, is_active = ?, sort_order = ?
             WHERE id = ?'
        );
        $stmt->execute([$code, $name, $type, $accountName, $accountNumber, $bankName, $instructions, $qrPath ?? $existingChannel['qr_path'] ?: null, $isActive, $sortOrder, $id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO payment_channels
             (code, name, channel_type, account_name, account_number, bank_name, instructions, qr_path, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$code, $name, $type, $accountName, $accountNumber, $bankName, $instructions, $qrPath, $isActive, $sortOrder]);
    }

    json_response([
        'ok' => true,
        'message' => 'Payment channel saved.',
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-member-status') {
    require_admin_json();

    $id = (int) require_field('id');
    $isActive = isset($_POST['isActive']) && $_POST['isActive'] === '1' ? 1 : 0;
    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Invalid member id.'], 422);
    }

    $stmt = $pdo->prepare('UPDATE members SET is_active = ? WHERE id = ?');
    $stmt->execute([$isActive, $id]);
    if ($stmt->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM members WHERE id = ?');
        $exists->execute([$id]);
        if ((int) $exists->fetchColumn() === 0) {
            json_response(['ok' => false, 'message' => 'Member not found.'], 404);
        }
    }

    json_response([
        'ok' => true,
        'message' => $isActive ? 'Member activated.' : 'Member deactivated.',
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-user-save') {
    $admin = require_admin_json();

    $id = (int) ($_POST['id'] ?? 0);
    $name = require_field('name');
    $email = strtolower(require_field('email'));
    $role = trim((string) ($_POST['role'] ?? 'staff')) ?: 'staff';
    $password = trim((string) ($_POST['password'] ?? ''));
    $isActive = isset($_POST['isActive']) && $_POST['isActive'] === '1' ? 1 : 0;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['ok' => false, 'message' => 'Use a valid admin email.'], 422);
    }
    if (!in_array($role, ['admin', 'staff'], true)) {
        json_response(['ok' => false, 'message' => 'Invalid admin role.'], 422);
    }
    if ($id === 0 && strlen($password) < 8) {
        json_response(['ok' => false, 'message' => 'New admin users need a password with at least 8 characters.'], 422);
    }
    if ($password !== '' && strlen($password) < 8) {
        json_response(['ok' => false, 'message' => 'Password must be at least 8 characters.'], 422);
    }
    if ($id > 0 && $id === (int) $admin['id'] && $isActive === 0) {
        json_response(['ok' => false, 'message' => 'You cannot deactivate your own admin account.'], 422);
    }

    $duplicate = $pdo->prepare('SELECT id FROM admin_users WHERE email = ? AND id <> ?');
    $duplicate->execute([$email, $id]);
    if ($duplicate->fetch()) {
        json_response(['ok' => false, 'message' => 'Another admin user already uses this email.'], 422);
    }

    if ($id > 0) {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE id = ?');
        $exists->execute([$id]);
        if ((int) $exists->fetchColumn() === 0) {
            json_response(['ok' => false, 'message' => 'Admin user not found.'], 404);
        }

        if ($password !== '') {
            $stmt = $pdo->prepare('UPDATE admin_users SET name = ?, email = ?, role = ?, is_active = ?, password_hash = ? WHERE id = ?');
            $stmt->execute([$name, $email, $role, $isActive, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE admin_users SET name = ?, email = ?, role = ?, is_active = ? WHERE id = ?');
            $stmt->execute([$name, $email, $role, $isActive, $id]);
        }
        $message = 'Admin user updated.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO admin_users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $isActive]);
        $message = 'Admin user created.';
    }

    json_response([
        'ok' => true,
        'message' => $message,
        'state' => get_state($pdo, true),
    ]);
}

json_response(['ok' => false, 'message' => 'Unknown action.'], 404);
