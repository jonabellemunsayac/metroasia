<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/site-config.php';
require_once __DIR__ . '/includes/data-privacy.php';

header('Access-Control-Allow-Origin: *');

const RESERVATION_STATUSES = [
    'Held',
    'Booked',
    'Cancelled',
];
const BLOCKING_RESERVATION_STATUS_SQL = "'Held','Booked'";

$receiptUploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'receipts';
$paymentUploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'payment';
$memberUploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'members';
foreach ([$receiptUploadDir, $paymentUploadDir, $memberUploadDir] as $dir) {
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

function db_datetime_to_ph_atom(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    try {
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        if (!$date) {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        }
    } catch (Throwable) {
        return null;
    }

    return $date->setTimezone(new DateTimeZone('Asia/Manila'))->format(DATE_ATOM);
}

function write_override_log(PDO $pdo, int $adminId, string $action, string $targetType, ?string $targetId, string $conflictSummary, array $payload): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO override_logs (admin_id, action, target_type, target_id, conflict_summary, payload)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $adminId,
        $action,
        $targetType,
        $targetId,
        $conflictSummary,
        json_encode($payload, JSON_THROW_ON_ERROR),
    ]);
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

function api_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function ensure_member_terms_columns(PDO $pdo): void
{
    if (!api_column_exists($pdo, 'members', 'terms_conditions_agree')) {
        $pdo->exec('ALTER TABLE members ADD terms_conditions_agree TINYINT(1) NOT NULL DEFAULT 0');
    }

    if (!api_column_exists($pdo, 'members', 'terms_agreed_at')) {
        $pdo->exec('ALTER TABLE members ADD terms_agreed_at DATETIME NULL');
    }
}

function validate_phone_field(string $phone, bool $required = false, string $label = 'Phone'): string
{
    $phone = trim($phone);
    if ($phone === '') {
        if ($required) {
            json_response(['ok' => false, 'message' => "{$label} is required."], 422);
        }
        return '';
    }

    if (!is_valid_phone_number($phone)) {
        json_response(['ok' => false, 'message' => phone_validation_message()], 422);
    }

    return normalize_phone_number($phone);
}

function reservation_reference(string $submitted = ''): string
{
    $submitted = strtoupper(trim($submitted));
    if ($submitted !== '' && preg_match('/^MA\d{6}-\d{6}-[A-Z0-9]{4}$/', $submitted) === 1) {
        return $submitted;
    }

    return 'MA' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function member_lookup_token_value(): string
{
    return 'mem_' . bin2hex(random_bytes(16));
}

function configured_booking_max_date(PDO $pdo): string
{
    $maxDate = trim((string) (site_config($pdo)['booking_max_date'] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $maxDate) === 1 ? $maxDate : '';
}

function require_booking_date_enabled(PDO $pdo, string $date): void
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok' => false, 'message' => 'Use a valid booking date.'], 422);
    }

    $maxDate = configured_booking_max_date($pdo);
    if ($maxDate !== '' && $date > $maxDate) {
        json_response(['ok' => false, 'message' => "Bookings are only enabled through {$maxDate}."], 422);
    }
}

function skill_level_label(?string $level): string
{
    return [
        '2.0' => '2.0 - Just starting out',
        '2.5' => '2.5 - Learning basic shots & rules',
        '3.0' => '3.0 - Consistent rallies, knows strategy',
        '3.5' => '3.5 - Solid all-court game',
        '4.0' => '4.0 - Advanced placement & strategy',
        '4.5' => '4.5 - Competitive tournament player',
        '5.0' => '5.0+ - Elite / pro level',
    ][$level ?? ''] ?? '';
}

function normalize_member_qr_payload(string $payload): string
{
    $payload = trim($payload);
    if ($payload === '') {
        return '';
    }

    if (str_starts_with($payload, 'member=')) {
        return trim(substr($payload, 7));
    }

    $json = json_decode($payload, true);
    if (is_array($json) && isset($json['member'])) {
        return trim((string) $json['member']);
    }

    parse_str($payload, $parsed);
    if (isset($parsed['member'])) {
        return trim((string) $parsed['member']);
    }

    return $payload;
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

function save_member_profile_picture(string $uploadDir): ?string
{
    if (!isset($_FILES['profilePicture']) || $_FILES['profilePicture']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES['profilePicture']['error'] !== UPLOAD_ERR_OK) {
        json_response(['ok' => false, 'message' => 'Profile picture upload failed.'], 422);
    }

    if ($_FILES['profilePicture']['size'] > 4 * 1024 * 1024) {
        json_response(['ok' => false, 'message' => 'Profile picture must be 4MB or smaller.'], 422);
    }

    $tmp = $_FILES['profilePicture']['tmp_name'];
    $mime = mime_content_type($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        json_response(['ok' => false, 'message' => 'Use a JPG, PNG, or WEBP profile picture.'], 422);
    }

    $filename = 'member-profile-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, $uploadDir . DIRECTORY_SEPARATOR . $filename)) {
        json_response(['ok' => false, 'message' => 'Could not save profile picture.'], 500);
    }

    return 'uploads/members/' . $filename;
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

function display_player_nickname(array $row): string
{
    $nickname = trim((string) ($row['player_nickname'] ?? ''));
    if ($nickname !== '') {
        return $nickname;
    }

    $memberNickname = trim((string) ($row['member_nickname'] ?? ''));
    if ($memberNickname !== '') {
        return $memberNickname;
    }

    $name = trim((string) ($row['customer_name'] ?? ''));
    if ($name !== '') {
        return strtok($name, ' ') ?: $name;
    }

    return '';
}

function booking_status_for_receipt(?string $receipt): string
{
    return 'Held';
}

function is_database_write_conflict(Throwable $exception): bool
{
    if (!$exception instanceof PDOException) {
        return false;
    }

    $sqlState = (string) $exception->getCode();
    $driverCode = (int) ($exception->errorInfo[1] ?? 0);

    return $sqlState === '40001' || in_array($driverCode, [1205, 1213], true);
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

function time_minutes_for_range(string $time, bool $isEnd = false): int
{
    $parts = explode(':', $time);
    $minutes = ((int) ($parts[0] ?? 0) * 60) + (int) ($parts[1] ?? 0);
    return $isEnd && $minutes === 0 ? 1440 : $minutes;
}

function display_time_label(string $time): string
{
    $time = substr($time, 0, 5);
    if ($time === '00:00') {
        return '12 MN';
    }

    return date('g A', strtotime('2000-01-01 ' . $time));
}

function valid_rate_days(): array
{
    return ['Any', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
}

function valid_rate_day_selections(): array
{
    return ['Any', 'Weekday', 'Weekend', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
}

function expand_rate_day_selection(string $selection): array
{
    if ($selection === 'Weekday') {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    }
    if ($selection === 'Weekend') {
        return ['Saturday', 'Sunday'];
    }

    return [$selection];
}

function rate_rules(PDO $pdo, bool $includeInactive = false): array
{
    $stmt = $pdo->query(
        "SELECT r.id, r.court_id, c.name AS court_name, r.sport, r.day_of_week, r.time_slot_id,
                ts.label AS time_label, ts.starts_at, ts.ends_at,
                r.rate_per_hour, r.updated_at
         FROM rates r
         JOIN courts c ON c.id = r.court_id
         JOIN time_slots ts ON ts.id = r.time_slot_id
         ORDER BY c.display_number, c.id, r.sport, FIELD(r.day_of_week, 'Any', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), ts.sort_order, ts.id"
    );

    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'name' => trim(($row['court_name'] ?? 'Court') . ' ' . $row['sport'] . ' ' . $row['time_label']),
        'courtId' => (int) $row['court_id'],
        'courtName' => $row['court_name'] ?? 'Court ' . $row['court_id'],
        'sport' => $row['sport'],
        'dayOfWeek' => $row['day_of_week'] ?: 'Any',
        'timeSlotId' => (int) $row['time_slot_id'],
        'timeLabel' => $row['time_label'],
        'dayType' => 'Any',
        'dayPattern' => $row['day_of_week'] ?: 'Any',
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
    $dayOfWeek = date('l', strtotime($date));
    $stmt = $pdo->prepare(
        "SELECT r.id, r.rate_per_hour, r.day_of_week, ts.label AS time_label
         FROM rates r
         JOIN time_slots ts ON ts.id = r.time_slot_id
         WHERE r.court_id = ? AND r.sport = ? AND r.time_slot_id = ?
           AND r.day_of_week IN ('Any', ?)
         ORDER BY CASE WHEN r.day_of_week = ? THEN 0 ELSE 1 END
         LIMIT 1"
    );
    $stmt->execute([$courtId, $sport, (int) $slot['id'], $dayOfWeek, $dayOfWeek]);
    $rate = $stmt->fetch();

    if ($rate) {
        $baseRate = (float) $rate['rate_per_hour'];
        return [
            'baseRate' => $baseRate,
            'finalAmount' => $baseRate * $duration,
            'durationHours' => $duration,
            'ruleId' => (int) $rate['id'],
            'ruleName' => ($rate['day_of_week'] === 'Any' ? 'Rate' : $rate['day_of_week'] . ' rate') . ' for ' . $rate['time_label'],
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

function normalize_supported_sports(array|string|null $value): array
{
    $raw = is_array($value) ? $value : explode(',', (string) ($value ?? ''));
    $valid = ['Pickleball', 'Basketball', 'Volleyball'];
    $sports = [];
    foreach ($raw as $sport) {
        $sport = trim((string) $sport);
        if (in_array($sport, $valid, true) && !in_array($sport, $sports, true)) {
            $sports[] = $sport;
        }
    }

    return $sports;
}

function valid_booking_sports(): array
{
    return ['Pickleball', 'Basketball', 'Volleyball'];
}

function ensure_core_booking_time_slots(PDO $pdo): void
{
    $fallbackPrice = (float) ($pdo->query('SELECT price FROM time_slots ORDER BY sort_order, id LIMIT 1')->fetchColumn() ?: 265);
    $slots = [
        ['Early morning', '05:00 AM - 06:00 AM', '05:00:00', '06:00:00', -2],
        ['Early morning', '06:00 AM - 07:00 AM', '06:00:00', '07:00:00', -1],
        ['Early morning', '07:00 AM - 08:00 AM', '07:00:00', '08:00:00', 0],
    ];
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO time_slots (period, label, starts_at, ends_at, price, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($slots as $slot) {
        $stmt->execute([$slot[0], $slot[1], $slot[2], $slot[3], $fallbackPrice, $slot[4]]);
    }
}

function ensure_sport_time_slot_availability(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sport_time_slot_availability (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sport ENUM('Pickleball','Basketball','Volleyball') NOT NULL,
            time_slot_id INT UNSIGNED NOT NULL,
            is_available TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_sport_time_slot (sport, time_slot_id),
            INDEX idx_sport_time_slot_lookup (sport, is_available, time_slot_id),
            CONSTRAINT fk_sport_slot_time_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id) ON DELETE CASCADE,
            CONSTRAINT fk_sport_slot_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
            CONSTRAINT fk_sport_slot_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $slotRows = $pdo->query('SELECT id, starts_at FROM time_slots ORDER BY sort_order, id')->fetchAll();
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO sport_time_slot_availability (sport, time_slot_id, is_available)
         VALUES (?, ?, ?)'
    );
    foreach (valid_booking_sports() as $sport) {
        $startThreshold = $sport === 'Pickleball' ? '07:00:00' : '05:00:00';
        foreach ($slotRows as $slot) {
            $insert->execute([
                $sport,
                (int) $slot['id'],
                strcmp((string) $slot['starts_at'], $startThreshold) >= 0 ? 1 : 0,
            ]);
        }
    }
}

function sport_time_slot_availability_payload(PDO $pdo): array
{
    ensure_core_booking_time_slots($pdo);
    ensure_sport_time_slot_availability($pdo);

    $rows = $pdo->query(
        "SELECT sta.id, sta.sport, sta.time_slot_id, sta.is_available,
                ts.label, ts.starts_at, ts.ends_at, ts.sort_order
         FROM sport_time_slot_availability sta
         JOIN time_slots ts ON ts.id = sta.time_slot_id
         ORDER BY ts.sort_order, ts.id, FIELD(sta.sport, 'Pickleball', 'Basketball', 'Volleyball')"
    )->fetchAll();

    $availableSlotIds = array_fill_keys(valid_booking_sports(), []);
    $availableLabels = array_fill_keys(valid_booking_sports(), []);
    $items = [];
    foreach ($rows as $row) {
        $sport = (string) $row['sport'];
        $item = [
            'id' => (int) $row['id'],
            'sport' => $sport,
            'timeSlotId' => (int) $row['time_slot_id'],
            'label' => $row['label'],
            'startsAt' => substr((string) $row['starts_at'], 0, 5),
            'endsAt' => substr((string) $row['ends_at'], 0, 5),
            'sortOrder' => (int) $row['sort_order'],
            'isAvailable' => (bool) $row['is_available'],
        ];
        $items[] = $item;
        if ($item['isAvailable']) {
            $availableSlotIds[$sport][] = $item['timeSlotId'];
            $availableLabels[$sport][] = $item['label'];
        }
    }

    return [
        'sports' => valid_booking_sports(),
        'items' => $items,
        'availableSlotIds' => $availableSlotIds,
        'availableLabels' => $availableLabels,
    ];
}

function sport_time_slot_is_available(PDO $pdo, string $sport, int $slotId): bool
{
    if (!in_array($sport, valid_booking_sports(), true) || $slotId <= 0) {
        return false;
    }

    ensure_core_booking_time_slots($pdo);
    ensure_sport_time_slot_availability($pdo);

    $stmt = $pdo->prepare(
        'SELECT is_available
         FROM sport_time_slot_availability
         WHERE sport = ? AND time_slot_id = ?
         LIMIT 1'
    );
    $stmt->execute([$sport, $slotId]);
    return (int) $stmt->fetchColumn() === 1;
}

function court_payload(array $court): array
{
    $sports = normalize_supported_sports($court['supported_sports'] ?? '');
    return [
        'id' => (int) $court['id'],
        'number' => (int) $court['display_number'],
        'name' => $court['name'],
        'type' => $court['court_type'],
        'surface' => $court['surface_label'],
        'sports' => $sports,
        'isActive' => isset($court['is_active']) ? (bool) $court['is_active'] : true,
        'labels' => [
            'Pickleball' => public_court_name((int) $court['id'], 'Pickleball'),
            'Basketball' => public_court_name((int) $court['id'], 'Basketball'),
            'Volleyball' => public_court_name((int) $court['id'], 'Volleyball'),
        ],
    ];
}

function backfill_default_rates_for_court(PDO $pdo, int $courtId, array $sports): void
{
    $slots = $pdo->query('SELECT id, price FROM time_slots ORDER BY sort_order, id')->fetchAll();
    $insert = $pdo->prepare(
        "INSERT IGNORE INTO rates (court_id, sport, day_of_week, time_slot_id, rate_per_hour)
         VALUES (?, ?, 'Any', ?, ?)"
    );
    foreach ($sports as $sport) {
        foreach ($slots as $slot) {
            $insert->execute([$courtId, $sport, (int) $slot['id'], (float) $slot['price']]);
        }
    }
}

function public_court_name(int $courtId, string $sport): string
{
    static $courtNames = null;
    if ($courtNames === null) {
        try {
            $stmt = db()->query('SELECT id, name FROM courts');
            $courtNames = [];
            foreach ($stmt->fetchAll() as $row) {
                $courtNames[(int) $row['id']] = (string) $row['name'];
            }
        } catch (Throwable) {
            $courtNames = [];
        }
    }

    $databaseName = trim((string) ($courtNames[$courtId] ?? ''));
    if ($databaseName !== '') {
        return $databaseName;
    }

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
        default => $courtNames[$courtId] ?? 'Court ' . $courtId,
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
        "SELECT cb.id, cb.block_date, cb.time_slot_id, ts.label AS time_label, ts.starts_at, ts.ends_at, cb.court_id,
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
        'startsAt' => substr((string) $row['starts_at'], 0, 5),
        'endsAt' => substr((string) $row['ends_at'], 0, 5),
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

function active_court_conflict(PDO $pdo, string $date, int $slotId, int $courtId, string $sport, ?int $excludeBookingId = null, bool $forUpdate = false): ?array
{
    $excludeSql = $excludeBookingId !== null ? 'AND cb.id <> ?' : '';
    $lockSql = $forUpdate ? ' FOR UPDATE' : '';

    $direct = $pdo->prepare(
        "SELECT cb.id, cb.court_id, cb.sport, cb.status, c.name AS court_name, ts.label AS time_label
         FROM court_bookings cb
         LEFT JOIN courts c ON c.id = cb.court_id
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         WHERE cb.booking_date = ? AND cb.time_slot_id = ? AND cb.court_id = ?
           AND cb.status IN (" . BLOCKING_RESERVATION_STATUS_SQL . ") {$excludeSql}
         LIMIT 1{$lockSql}"
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
            'courtId' => (int) $row['court_id'],
            'courtName' => $courtName,
            'sport' => $row['sport'],
            'status' => $row['status'],
            'customerName' => $row['customer_name'],
            'time' => $row['time_label'],
            'summary' => "#{$row['id']} {$courtName} {$row['sport']} {$row['status']} for {$row['customer_name']} ({$row['time_label']})",
        ];
    }

    return $matches;
}

function court_block_slot_ids_for_request(PDO $pdo, int $fallbackSlotId, string $startTime, string $endTime): array
{
    $startTime = substr(trim($startTime), 0, 5);
    $endTime = substr(trim($endTime), 0, 5);
    if ($startTime === '' || $endTime === '') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM time_slots WHERE id = ?');
        $stmt->execute([$fallbackSlotId]);
        if ((int) $stmt->fetchColumn() === 0) {
            json_response(['ok' => false, 'message' => 'Invalid time slot.'], 422);
        }
        return [$fallbackSlotId];
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        json_response(['ok' => false, 'message' => 'Use a valid blocking time range.'], 422);
    }

    $rangeStart = time_minutes_for_range($startTime);
    $rangeEnd = time_minutes_for_range($endTime, true);
    if ($rangeEnd <= $rangeStart) {
        json_response(['ok' => false, 'message' => 'End time must be after start time.'], 422);
    }

    $slotRows = $pdo->query('SELECT id, starts_at, ends_at FROM time_slots ORDER BY sort_order, id')->fetchAll();
    $slotIds = [];
    foreach ($slotRows as $slot) {
        $slotStart = time_minutes_for_range((string) $slot['starts_at']);
        $slotEnd = time_minutes_for_range((string) $slot['ends_at'], true);
        if ($slotStart >= $rangeStart && $slotEnd <= $rangeEnd) {
            $slotIds[] = (int) $slot['id'];
        }
    }

    if ($slotIds === []) {
        json_response(['ok' => false, 'message' => 'Choose a time range that matches available booking slots.'], 422);
    }

    return $slotIds;
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
    ensure_core_booking_time_slots($pdo);
    $sportSlotAvailability = sport_time_slot_availability_payload($pdo);
    $courts = $pdo->query('SELECT id, display_number, name, court_type, surface_label, supported_sports FROM courts WHERE is_active = 1 ORDER BY display_number, id')->fetchAll();
    $rateRows = $pdo->query(
        "SELECT r.rate_per_hour, ts.starts_at, ts.ends_at, ts.sort_order
         FROM rates r
         JOIN time_slots ts ON ts.id = r.time_slot_id
         WHERE r.day_of_week = 'Any'
         ORDER BY r.rate_per_hour, ts.sort_order"
    )->fetchAll();
    $rateGroups = [];
    foreach ($rateRows as $row) {
        $key = (string) $row['rate_per_hour'];
        if (!isset($rateGroups[$key])) {
            $rateGroups[$key] = [
                'price' => (float) $row['rate_per_hour'],
                'start' => (string) $row['starts_at'],
                'end' => (string) $row['ends_at'],
                'sort' => (int) $row['sort_order'],
            ];
            continue;
        }

        if ((int) $row['sort_order'] < $rateGroups[$key]['sort']) {
            $rateGroups[$key]['sort'] = (int) $row['sort_order'];
            $rateGroups[$key]['start'] = (string) $row['starts_at'];
        }
        if (time_minutes_for_range((string) $row['ends_at'], true) > time_minutes_for_range((string) $rateGroups[$key]['end'], true)) {
            $rateGroups[$key]['end'] = (string) $row['ends_at'];
        }
    }
    usort($rateGroups, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort'] ?: $a['price'] <=> $b['price']);
    $rates = array_map(static fn (array $group): array => [
        'price' => (int) $group['price'],
        'time' => display_time_label($group['start']) . ' - ' . display_time_label($group['end']),
    ], $rateGroups);
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
        "SELECT cb.id, cb.booking_reference, cb.member_id, cb.booking_date, cb.time_slot_id, ts.label AS time_label, cb.court_id, cb.sport, cb.status,
                cb.customer_name, cb.player_nickname, m.nickname AS member_nickname, cb.customer_email, cb.customer_phone, cb.payment_method,
                cb.receipt_path, cb.base_rate, cb.final_amount, cb.created_at
         FROM court_bookings cb
         JOIN courts c ON c.id = cb.court_id AND c.is_active = 1
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         LEFT JOIN members m ON m.id = cb.member_id
         WHERE cb.status IN (" . BLOCKING_RESERVATION_STATUS_SQL . ")"
    );
    foreach ($stmt->fetchAll() as $row) {
        $bookings['court-' . $row['id']] = [
            'id' => 'court:' . $row['id'],
            'type' => 'court',
            'bookingReference' => $includeAdmin ? ($row['booking_reference'] ?? '') : '',
            'memberId' => $row['member_id'] !== null ? (int) $row['member_id'] : null,
            'date' => $row['booking_date'],
            'timeSlotId' => (int) $row['time_slot_id'],
            'time' => $row['time_label'],
            'court' => (int) $row['court_id'],
            'sport' => $row['sport'],
            'status' => $row['status'],
            'baseRate' => (float) $row['base_rate'],
            'finalAmount' => (float) $row['final_amount'],
            'customerName' => $includeAdmin ? $row['customer_name'] : '',
            'playerNickname' => display_player_nickname($row),
            'customerEmail' => $includeAdmin ? ($row['customer_email'] ?? '') : '',
            'customerPhone' => $includeAdmin ? ($row['customer_phone'] ?? '') : '',
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
        'courts' => array_map(static fn (array $court): array => court_payload($court), $courts),
        'rates' => $rates,
        'rateRules' => rate_rules($pdo, false),
        'timeSlots' => $timeSlots,
        'slotDetails' => $slotDetails,
        'sportSlotAvailability' => $sportSlotAvailability,
        'reservationStatuses' => RESERVATION_STATUSES,
        'blockingStatuses' => ['Held', 'Booked'],
        'permanentOccupancyStatus' => 'Booked',
        'bookings' => $bookings,
        'courtBlocks' => court_blocks($pdo, false),
        'openPlays' => $openPlays,
        'openPlayReservations' => $openPlayReservations,
        'paymentChannels' => payment_channels($pdo, false),
        'siteConfig' => $siteConfig,
    ];

    if ($includeAdmin) {
        $admin = current_admin();
        $rolePermissions = admin_role_menu_permissions($pdo);
        $state['currentAdmin'] = $admin ? [
            'id' => (int) $admin['id'],
            'name' => $admin['name'],
            'email' => $admin['email'],
            'role' => $admin['role'],
            'roleLabel' => admin_role_label((string) $admin['role']),
            'canManageOperations' => admin_can_manage_operations($admin),
            'canManageMembers' => admin_can_manage_members($admin),
            'canManageStaff' => admin_can_manage_staff($admin),
            'menuPermissions' => $rolePermissions[$admin['role'] === 'staff' ? 'reception' : (string) $admin['role']] ?? [],
        ] : null;
        $state['adminRoleOptions'] = $admin && admin_can_manage_staff($admin) ? admin_role_options() : [];
        $state['adminMenuCatalog'] = $admin && admin_can_manage_staff($admin) ? array_values(admin_menu_catalog()) : [];
        $state['adminRoleMenuPermissions'] = $admin && admin_can_manage_staff($admin) ? $rolePermissions : [];
        $state['adminReservations'] = admin_reservations($pdo);
        $state['adminPaymentChannels'] = payment_channels($pdo, true);
        $state['adminRateRules'] = rate_rules($pdo, true);
        $state['adminRateAudit'] = rate_audit_logs($pdo);
        $state['adminCourts'] = admin_courts($pdo);
        $state['adminCourtBlocks'] = court_blocks($pdo, true);
        $state['adminOverrideLogs'] = override_logs($pdo);
        $state['adminMembers'] = $admin && admin_menu_allowed('admin-members', $admin) ? admin_members($pdo) : [];
        $state['adminUsers'] = $admin && admin_can_manage_staff($admin) ? admin_users_list($pdo) : [];
    }

    $member = current_member();
    if ($member !== null) {
        $state['member'] = [
            'id' => (int) $member['id'],
            'name' => $member['name'],
            'nickname' => $member['nickname'] ?? '',
            'email' => $member['email'],
            'phone' => $member['phone'],
        ];
        $state['memberReservations'] = member_reservations($pdo, (int) $member['id']);
    }

    return $state;
}

function admin_courts(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT c.*,
                (SELECT COUNT(*) FROM rates r WHERE r.court_id = c.id) AS rate_count,
                (SELECT COUNT(*) FROM court_bookings cb WHERE cb.court_id = c.id AND cb.status IN (" . BLOCKING_RESERVATION_STATUS_SQL . ")) AS active_booking_count
         FROM courts c
         ORDER BY c.is_active DESC, c.display_number, c.id"
    );

    return array_map(static function (array $court): array {
        $payload = court_payload($court);
        $payload['rateCount'] = (int) $court['rate_count'];
        $payload['activeBookingCount'] = (int) $court['active_booking_count'];
        return $payload;
    }, $stmt->fetchAll());
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
                CONCAT(COALESCE(c.name, 'Deleted rate'), ' ', COALESCE(r.sport, ''), ' ', COALESCE(r.day_of_week, ''), ' ', COALESCE(ts.label, '')) AS rate_name,
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
    ensure_member_terms_columns($pdo);

    $stmt = $pdo->query(
        "SELECT m.id, m.name, m.nickname, m.email, m.phone, m.profile_picture_path, m.birth_month, m.birth_year, m.skill_level,
                m.terms_conditions_agree, m.terms_agreed_at,
                m.data_privacy_act_agree, m.data_privacy_policy_version, m.data_privacy_agreed_at,
                m.member_lookup_token, m.is_active, m.last_login_at, m.created_at,
                (SELECT COUNT(*) FROM court_bookings cb WHERE cb.member_id = m.id) AS court_bookings_count,
                (SELECT COUNT(*) FROM court_bookings cb WHERE cb.member_id = m.id AND cb.status = 'Booked') AS confirmed_court_count,
                (SELECT COALESCE(SUM(ef.amount), 0) FROM member_entrance_fee_payments ef WHERE ef.member_id = m.id) AS entrance_fee_total
         FROM members m
         ORDER BY m.created_at DESC, m.id DESC"
    );

    $members = array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'nickname' => $row['nickname'] ?? '',
        'email' => $row['email'],
        'phone' => $row['phone'],
        'profilePicture' => $row['profile_picture_path'] ?? '',
        'birthMonth' => $row['birth_month'] !== null ? (int) $row['birth_month'] : null,
        'birthYear' => $row['birth_year'] !== null ? (int) $row['birth_year'] : null,
        'skillLevel' => $row['skill_level'] ?? '',
        'skillLabel' => skill_level_label($row['skill_level'] ?? null),
        'termsConditionsAgree' => (bool) $row['terms_conditions_agree'],
        'termsAgreedAt' => $row['terms_agreed_at'] ? date(DATE_ATOM, strtotime($row['terms_agreed_at'])) : null,
        'dataPrivacyActAgree' => (bool) $row['data_privacy_act_agree'],
        'dataPrivacyPolicyVersion' => $row['data_privacy_policy_version'] ?? '',
        'dataPrivacyAgreedAt' => $row['data_privacy_agreed_at'] ? date(DATE_ATOM, strtotime($row['data_privacy_agreed_at'])) : null,
        'lookupToken' => $row['member_lookup_token'] ?? '',
        'qrPayload' => 'member=' . ($row['member_lookup_token'] ?? ''),
        'isActive' => (bool) $row['is_active'],
        'lastLoginAt' => $row['last_login_at'] ? date(DATE_ATOM, strtotime($row['last_login_at'])) : null,
        'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
        'courtBookingsCount' => (int) $row['court_bookings_count'],
        'confirmedCount' => (int) $row['confirmed_court_count'],
        'entranceFeeTotal' => (float) $row['entrance_fee_total'],
    ], $stmt->fetchAll());

    $ids = array_column($members, 'id');
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $historyStmt = $pdo->prepare(
        "SELECT ef.id, ef.member_id, ef.amount, ef.payment_date, ef.payment_time, ef.booking_id,
                ef.reference_number, ef.payment_method, ef.receipt_path, ef.notes, ef.created_at,
                au.name AS recorded_by_name
         FROM member_entrance_fee_payments ef
         LEFT JOIN admin_users au ON au.id = ef.recorded_by
         WHERE ef.member_id IN ({$placeholders})
         ORDER BY ef.payment_date DESC, ef.payment_time DESC, ef.id DESC"
    );
    $historyStmt->execute($ids);
    $history = [];
    foreach ($historyStmt->fetchAll() as $row) {
        $history[(int) $row['member_id']][] = [
            'id' => (int) $row['id'],
            'amount' => (float) $row['amount'],
            'paymentDate' => $row['payment_date'],
            'paymentTime' => substr((string) $row['payment_time'], 0, 5),
            'bookingId' => $row['booking_id'] !== null ? (int) $row['booking_id'] : null,
            'referenceNumber' => $row['reference_number'] ?? '',
            'paymentMethod' => $row['payment_method'] ?? '',
            'receipt' => $row['receipt_path'] ?? '',
            'notes' => $row['notes'] ?? '',
            'recordedByName' => $row['recorded_by_name'] ?? 'Admin',
            'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
        ];
    }

    return array_map(static function (array $member) use ($history): array {
        $member['entranceFeeCount'] = count($history[$member['id']] ?? []);
        $member['entranceFeeHistory'] = $history[$member['id']] ?? [];
        return $member;
    }, $members);
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
        'roleLabel' => admin_role_label((string) $row['role']),
        'isActive' => (bool) $row['is_active'],
        'lastLoginAt' => $row['last_login_at'] ? date(DATE_ATOM, strtotime($row['last_login_at'])) : null,
        'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
    ], $stmt->fetchAll());
}

function admin_reservations(PDO $pdo): array
{
    $courtRows = $pdo->query(
        "SELECT CONCAT('court:', cb.id) AS id, 'court' AS type, cb.booking_reference,
                cb.member_id, cb.booking_date AS date, cb.time_slot_id,
                ts.label AS time, cb.court_id AS court, c.name AS court_name, cb.sport, NULL AS session_id, NULL AS session_title,
                cb.status, cb.customer_name, cb.player_nickname, cb.customer_email, cb.customer_phone, cb.payment_method,
                cb.receipt_path, cb.final_amount, m.name AS member_name, m.nickname AS member_nickname, cb.cancel_reason, cb.created_at, cb.reviewed_at, cb.cancelled_at,
                reviewer.name AS reviewed_by_name, canceller.name AS cancelled_by_name
         FROM court_bookings cb
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         LEFT JOIN courts c ON c.id = cb.court_id
         LEFT JOIN members m ON m.id = cb.member_id
         LEFT JOIN admin_users reviewer ON reviewer.id = cb.reviewed_by
         LEFT JOIN admin_users canceller ON canceller.id = cb.cancelled_by"
    )->fetchAll();

    $openRows = $pdo->query(
        "SELECT CONCAT('openplay:', opr.id) AS id, 'openplay' AS type, NULL AS booking_reference,
                opr.member_id, ops.session_date AS date, NULL AS time_slot_id,
                ops.session_time AS time, NULL AS court, 'Open Play' AS sport, opr.session_id, ops.title AS session_title,
                opr.status, opr.customer_name, NULL AS player_nickname, opr.customer_email, opr.customer_phone, opr.payment_method,
                opr.receipt_path, opr.final_amount, m.name AS member_name, m.nickname AS member_nickname, opr.cancel_reason, opr.created_at, opr.reviewed_at, opr.cancelled_at,
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
        'bookingReference' => $row['booking_reference'] ?? '',
        'memberId' => $row['member_id'] !== null ? (int) $row['member_id'] : null,
        'date' => $row['date'],
        'timeSlotId' => $row['time_slot_id'] !== null ? (int) $row['time_slot_id'] : null,
        'time' => $row['time'],
        'court' => $row['court'] !== null ? (int) $row['court'] : null,
        'courtName' => $row['court'] !== null ? (trim((string) ($row['court_name'] ?? '')) ?: public_court_name((int) $row['court'], (string) $row['sport'])) : null,
        'sport' => $row['sport'],
        'sessionId' => $row['session_id'] !== null ? (string) $row['session_id'] : null,
        'sessionTitle' => $row['session_title'],
        'status' => $row['status'],
        'customerName' => $row['customer_name'],
        'playerNickname' => display_player_nickname($row),
        'customerEmail' => $row['customer_email'] ?? '',
        'customerPhone' => $row['customer_phone'] ?? '',
        'paymentMethod' => $row['payment_method'],
        'receipt' => $row['receipt_path'],
        'finalAmount' => (float) $row['final_amount'],
        'memberName' => $row['member_name'],
        'cancelReason' => $row['cancel_reason'],
        'reviewedByName' => $row['reviewed_by_name'],
        'cancelledByName' => $row['cancelled_by_name'],
        'createdAt' => db_datetime_to_ph_atom($row['created_at']),
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
    $member = current_member();
    if ($member === null) {
        json_response(['ok' => false, 'message' => 'Member login required before booking.'], 401);
    }

    $date = require_field('date');
    $time = require_field('time');
    $courtId = (int) require_field('court');
    $sport = $_POST['sport'] ?? 'Pickleball';
    $sport = trim((string) $sport) ?: 'Pickleball';
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $nickname = trim((string) ($_POST['nickname'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $bookingReference = reservation_reference((string) ($_POST['bookingReference'] ?? ''));
    $paymentMethod = require_field('paymentMethod');
    require_active_payment_channel($pdo, $paymentMethod);
    $receipt = save_receipt($receiptUploadDir);
    $name = trim((string) $member['name']) ?: ($name !== '' ? $name : 'Player');
    $phone = validate_phone_field((string) $member['phone'], false);
    $email = trim((string) $member['email']);
    if ($nickname === '') {
        $nickname = trim((string) ($member['nickname'] ?? ''));
    }
    if ($nickname === '') {
        $nickname = strtok($name, ' ') ?: $name;
    }

    $slotStmt = $pdo->prepare('SELECT id, label, starts_at, ends_at, price FROM time_slots WHERE label = ?');
    $slotStmt->execute([$time]);
    $slot = $slotStmt->fetch();
    $slotId = (int) ($slot['id'] ?? 0);
    if ($slotId === 0) {
        json_response(['ok' => false, 'message' => 'Invalid time slot.'], 422);
    }
    require_booking_date_enabled($pdo, $date);

    if (slot_is_past($date, $slot)) {
        json_response(['ok' => false, 'message' => 'Past dates and time slots cannot be booked.'], 422);
    }

    if (!in_array($sport, ['Pickleball', 'Basketball', 'Volleyball'], true)) {
        json_response(['ok' => false, 'message' => 'Invalid sport.'], 422);
    }
    if (!sport_time_slot_is_available($pdo, $sport, $slotId)) {
        json_response(['ok' => false, 'message' => "{$sport} is not available for the selected time slot."], 422);
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

    $rate = calculate_booking_rate($pdo, $courtId, $sport, $date, $slot, $member !== null);

    $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    $pdo->beginTransaction();
    try {
        $conflict = active_court_conflict($pdo, $date, $slotId, $courtId, $sport, null, true);
        if ($conflict !== null) {
            $pdo->rollBack();
            json_response(['ok' => false, 'message' => 'That time slot is no longer available. Please choose another slot.'], 409);
        }

        $blockConflict = active_block_conflict($pdo, $date, $slotId, $courtId, $sport);
        if ($blockConflict !== null) {
            $pdo->rollBack();
            json_response(['ok' => false, 'message' => 'That time slot is no longer available. Please choose another slot.'], 409);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO court_bookings
             (booking_reference, member_id, booking_date, time_slot_id, court_id, sport, status, customer_name, player_nickname, customer_email, customer_phone, customer_notes, payment_method, receipt_path, base_rate, final_amount, rate_snapshot)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $bookingReference,
            $member['id'] ?? null,
            $date,
            $slotId,
            $courtId,
            $sport,
            'Held',
            $name,
            $nickname,
            $email,
            $phone,
            $notes,
            $paymentMethod,
            $receipt,
            $rate['baseRate'],
            $rate['finalAmount'],
            booking_rate_snapshot(['timeSlot' => $time, 'sport' => $sport, 'courtId' => $courtId, 'date' => $date], $rate),
        ]);
        $bookingId = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_database_write_conflict($exception)) {
            json_response(['ok' => false, 'message' => 'That time slot is no longer available. Please choose another slot.'], 409);
        }
        json_response(['ok' => false, 'message' => 'Booking could not be saved. Please try again.'], 500);
    }

    json_response([
        'ok' => true,
        'message' => 'Reservation submitted and held while admin reviews it.',
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
    $phone = validate_phone_field($phone, true);

    $stmt = $pdo->prepare('SELECT capacity, price, title, session_date, session_time FROM open_play_sessions WHERE id = ? AND is_active = 1');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    $capacity = (int) ($session['capacity'] ?? 0);
    if ($capacity === 0) {
        json_response(['ok' => false, 'message' => 'Open play session not found.'], 404);
    }

    $amount = (float) $session['price'];

    $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id FROM open_play_reservations WHERE session_id = ? AND status IN (" . BLOCKING_RESERVATION_STATUS_SQL . ") FOR UPDATE");
        $stmt->execute([$sessionId]);
        if (count($stmt->fetchAll()) >= $capacity) {
            $pdo->rollBack();
            json_response(['ok' => false, 'message' => 'This open play is full.'], 409);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO open_play_reservations
             (member_id, session_id, status, customer_name, customer_email, customer_phone, payment_method, receipt_path, final_amount, rate_snapshot)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $member['id'] ?? null,
            $sessionId,
            'Held',
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
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_database_write_conflict($exception)) {
            json_response(['ok' => false, 'message' => 'This open play is full.'], 409);
        }
        json_response(['ok' => false, 'message' => 'Open play reservation could not be saved. Please try again.'], 500);
    }

    json_response([
        'ok' => true,
        'message' => 'Open play reservation submitted and held while admin reviews it.',
        'state' => get_state($pdo, current_admin() !== null),
    ]);
}

if ($action === 'admin-status') {
    $admin = require_operations_admin_json();
    $id = require_field('id');
    $status = require_field('status');
    $allowed = RESERVATION_STATUSES;

    if (!in_array($status, $allowed, true)) {
        json_response(['ok' => false, 'message' => 'Invalid status.'], 422);
    }

    [$type, $rawId] = array_pad(explode(':', $id, 2), 2, '');
    $reservationIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $rawId)), static fn (int $value): bool => $value > 0)));
    if ($reservationIds === [] || !in_array($type, ['court', 'openplay'], true) || ($type === 'openplay' && count($reservationIds) > 1)) {
        json_response(['ok' => false, 'message' => 'Invalid reservation id.'], 422);
    }
    $reservationId = $reservationIds[0];
    $idPlaceholders = implode(',', array_fill(0, count($reservationIds), '?'));

    $table = $type === 'court' ? 'court_bookings' : 'open_play_reservations';
    $nextStatus = $status;

    $existsStmt = $pdo->prepare("SELECT id, status FROM {$table} WHERE id IN ({$idPlaceholders})");
    $existsStmt->execute($reservationIds);
    $statusRows = $existsStmt->fetchAll();
    if (count($statusRows) !== count($reservationIds)) {
        json_response(['ok' => false, 'message' => 'Reservation not found.'], 404);
    }
    $allowedTransitions = [
        'Held' => ['Booked', 'Cancelled'],
        'Booked' => ['Cancelled'],
        'Cancelled' => [],
    ];
    foreach ($statusRows as $statusRow) {
        $currentStatus = (string) $statusRow['status'];
        if (!in_array($nextStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            json_response(['ok' => false, 'message' => "Invalid transition from {$currentStatus} to {$nextStatus}."], 422);
        }
    }

    if ($nextStatus === 'Booked') {
        if ($type === 'court') {
            $stmt = $pdo->prepare("SELECT id, booking_date, time_slot_id, court_id, sport FROM court_bookings WHERE id IN ({$idPlaceholders})");
            $stmt->execute($reservationIds);
            foreach ($stmt->fetchAll() as $booking) {
                $conflict = active_court_conflict(
                    $pdo,
                    (string) $booking['booking_date'],
                    (int) $booking['time_slot_id'],
                    (int) $booking['court_id'],
                    (string) $booking['sport'],
                    (int) $booking['id']
                );
                if ($conflict !== null) {
                    json_response(['ok' => false, 'message' => $conflict['message']], 409);
                }
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
        $stmt = $pdo->prepare("UPDATE {$table} SET status = 'Booked', reviewed_by = ?, reviewed_at = NOW(), cancelled_by = NULL, cancelled_at = NULL, cancel_reason = NULL WHERE id IN ({$idPlaceholders})");
        $stmt->execute(array_merge([(int) $admin['id']], $reservationIds));
    } elseif ($nextStatus === 'Cancelled') {
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            json_response(['ok' => false, 'message' => 'Cancellation reason is required.'], 422);
        }
        $stmt = $pdo->prepare("UPDATE {$table} SET status = 'Cancelled', cancelled_by = ?, cancelled_at = NOW(), cancel_reason = ? WHERE id IN ({$idPlaceholders})");
        $stmt->execute(array_merge([(int) $admin['id'], $reason], $reservationIds));
    }

    json_response([
        'ok' => true,
        'message' => $nextStatus === 'Booked' ? 'Reservation marked Booked.' : 'Reservation cancelled. The slot is available again.',
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-override-booking') {
    $admin = require_operations_admin_json();

    $date = require_field('date');
    $timeSlotId = (int) require_field('timeSlotId');
    $timeSlotIdsRaw = trim((string) ($_POST['timeSlotIds'] ?? ''));
    $courtId = (int) require_field('courtId');
    $sport = require_field('sport');
    $memberId = (int) ($_POST['memberId'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $playerNickname = '';
    $status = trim((string) ($_POST['status'] ?? 'Booked')) ?: 'Booked';
    $paymentMethod = trim((string) ($_POST['paymentMethod'] ?? 'Admin Override')) ?: 'Admin Override';
    $reason = trim((string) ($_POST['overrideReason'] ?? 'Admin override'));
    $bookingReference = reservation_reference((string) ($_POST['bookingReference'] ?? ''));
    $overrideConfirm = isset($_POST['overrideConfirm']) && $_POST['overrideConfirm'] === '1';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok' => false, 'message' => 'Use a valid booking date.'], 422);
    }
    if (!in_array($sport, ['Pickleball', 'Basketball', 'Volleyball'], true)) {
        json_response(['ok' => false, 'message' => 'Invalid sport.'], 422);
    }
    if (!in_array($status, ['Held', 'Booked'], true)) {
        json_response(['ok' => false, 'message' => 'Invalid override booking status.'], 422);
    }
    if ($timeSlotIdsRaw !== '' && (string) ($admin['role'] ?? '') !== 'super_admin') {
        json_response(['ok' => false, 'message' => 'Super Admin permission required for range override bookings.'], 403);
    }

    if ($memberId > 0) {
        $memberStmt = $pdo->prepare('SELECT id, name, nickname, phone, email FROM members WHERE id = ? AND is_active = 1');
        $memberStmt->execute([$memberId]);
        $member = $memberStmt->fetch();
        if (!$member) {
            json_response(['ok' => false, 'message' => 'Selected member was not found or is inactive.'], 422);
        }
        $name = (string) $member['name'];
        $phone = (string) $member['phone'];
        $email = (string) $member['email'];
        $playerNickname = trim((string) ($member['nickname'] ?? ''));
    }

    if ($name === '') {
        json_response(['ok' => false, 'message' => 'Customer name is required.'], 422);
    }
    $phone = validate_phone_field($phone, false, 'Customer phone');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['ok' => false, 'message' => 'Use a valid customer email.'], 422);
    }

    $timeSlotIds = [$timeSlotId];
    if ($timeSlotIdsRaw !== '') {
        $timeSlotIds = array_values(array_unique(array_map(
            static fn (string $value): int => (int) $value,
            array_filter(preg_split('/[,\s]+/', $timeSlotIdsRaw) ?: [], static fn (string $value): bool => trim($value) !== '')
        )));
    }
    if ($timeSlotIds === [] || in_array(0, $timeSlotIds, true)) {
        json_response(['ok' => false, 'message' => 'Invalid time slot.'], 422);
    }

    $slotPlaceholders = implode(',', array_fill(0, count($timeSlotIds), '?'));
    $slotStmt = $pdo->prepare("SELECT id, label, starts_at, ends_at, price FROM time_slots WHERE id IN ({$slotPlaceholders}) ORDER BY sort_order, id");
    $slotStmt->execute($timeSlotIds);
    $slots = $slotStmt->fetchAll();
    if (count($slots) !== count($timeSlotIds)) {
        json_response(['ok' => false, 'message' => 'Invalid time slot.'], 422);
    }
    if ($timeSlotIdsRaw !== '') {
        for ($index = 1; $index < count($slots); $index++) {
            $previousEnd = time_minutes_for_range((string) $slots[$index - 1]['ends_at'], true);
            $currentStart = time_minutes_for_range((string) $slots[$index]['starts_at']);
            if ($previousEnd !== $currentStart) {
                json_response(['ok' => false, 'message' => 'Choose a continuous time range for Super Admin override.'], 422);
            }
        }
    }
    $isSuperAdminRangeOverride = $timeSlotIdsRaw !== '' && (string) ($admin['role'] ?? '') === 'super_admin';
    foreach ($slots as $slot) {
        if (!sport_time_slot_is_available($pdo, $sport, (int) $slot['id'])) {
            json_response(['ok' => false, 'message' => "{$sport} is not available for {$slot['label']}."], 422);
        }
        if (!$isSuperAdminRangeOverride && slot_is_past($date, $slot)) {
            json_response(['ok' => false, 'message' => 'Past dates and time slots cannot be booked.'], 422);
        }
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

    $bookingConflicts = [];
    $blockConflicts = [];
    foreach ($slots as $slot) {
        $bookingConflicts = array_merge($bookingConflicts, active_bookings_for_booking($pdo, $date, (int) $slot['id'], $courtId, $sport));
        $blockConflicts = array_merge($blockConflicts, active_blocks_for_booking($pdo, $date, (int) $slot['id'], $courtId, $sport));
    }
    $conflictSummaries = array_values(array_unique(array_merge(array_column($bookingConflicts, 'summary'), array_column($blockConflicts, 'summary'))));

    if ($conflictSummaries !== [] && !$overrideConfirm) {
        json_response([
            'ok' => false,
            'requiresOverride' => true,
            'message' => "Resource Conflict\n\n" . implode("\n\n", $conflictSummaries) . "\n\nBooking " . public_court_name($courtId, $sport) . " for {$sport} will conflict with this reservation.\n\nCancel conflicting reservation and continue?",
            'conflicts' => array_merge($bookingConflicts, $blockConflicts),
        ], 409);
    }

    $pdo->beginTransaction();
    try {
        if ($bookingConflicts !== []) {
            $cancel = $pdo->prepare(
                "UPDATE court_bookings
                 SET status = 'Cancelled', cancelled_by = ?, cancelled_at = NOW(), cancel_reason = ?
                 WHERE id = ?"
            );
            $cancelledBookingIds = [];
            foreach ($bookingConflicts as $conflict) {
                if (isset($cancelledBookingIds[(int) $conflict['id']])) {
                    continue;
                }
                $cancelledBookingIds[(int) $conflict['id']] = true;
                $cancel->execute([(int) $admin['id'], 'Released by admin override: ' . $reason, (int) $conflict['id']]);
            }
        }

        if ($blockConflicts !== []) {
            $cancelBlock = $pdo->prepare(
                "UPDATE court_blocks
                 SET status = 'Cancelled', cancelled_by = ?, cancelled_at = NOW()
                 WHERE id = ?"
            );
            $cancelledBlockIds = [];
            foreach ($blockConflicts as $conflict) {
                if (isset($cancelledBlockIds[(int) $conflict['id']])) {
                    continue;
                }
                $cancelledBlockIds[(int) $conflict['id']] = true;
                $cancelBlock->execute([(int) $admin['id'], (int) $conflict['id']]);
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO court_bookings
             (booking_reference, member_id, booking_date, time_slot_id, court_id, sport, status, customer_name, player_nickname, customer_email, customer_phone, payment_method, base_rate, final_amount, rate_snapshot, reviewed_by, reviewed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $reviewedAt = $status === 'Booked' ? date('Y-m-d H:i:s') : null;
        $bookingIds = [];
        foreach ($slots as $slot) {
            $rate = calculate_booking_rate($pdo, $courtId, $sport, $date, $slot, false);
            $stmt->execute([
                $bookingReference,
                $memberId > 0 ? $memberId : null,
                $date,
                (int) $slot['id'],
                $courtId,
                $sport,
                $status,
                $name,
                $playerNickname !== '' ? $playerNickname : (strtok($name, ' ') ?: $name),
                $email,
                $phone,
                $paymentMethod,
                $rate['baseRate'],
                $rate['finalAmount'],
                booking_rate_snapshot(['timeSlot' => $slot['label'], 'sport' => $sport, 'courtId' => $courtId, 'date' => $date, 'override' => true], $rate),
                $status === 'Booked' ? (int) $admin['id'] : null,
                $reviewedAt,
            ]);
            $bookingIds[] = (int) $pdo->lastInsertId();
        }

        write_override_log(
            $pdo,
            (int) $admin['id'],
            'admin-booking-override',
            'court_booking',
            implode(',', $bookingIds),
            implode('; ', $conflictSummaries),
            [
                'bookingIds' => $bookingIds,
                'bookingReference' => $bookingReference,
                'memberId' => $memberId > 0 ? $memberId : null,
                'customerName' => $name,
                'customerEmail' => $email,
                'customerPhone' => $phone,
                'date' => $date,
                'timeSlotIds' => array_map(static fn (array $slot): int => (int) $slot['id'], $slots),
                'time' => array_map(static fn (array $slot): string => (string) $slot['label'], $slots),
                'courtId' => $courtId,
                'sport' => $sport,
                'status' => $status,
                'paymentMethod' => $paymentMethod,
                'rangeOverride' => $timeSlotIdsRaw !== '',
                'reason' => $reason,
                'cancelledBookings' => $bookingConflicts,
                'cancelledBlocks' => $blockConflicts,
            ]
        );

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

if ($action === 'admin-booking-update') {
    $admin = require_operations_admin_json();

    $bookingId = (int) ($_POST['bookingId'] ?? 0);
    $date = require_field('date');
    $timeSlotId = (int) require_field('timeSlotId');
    $courtId = (int) require_field('courtId');
    $sport = require_field('sport');
    $memberId = (int) ($_POST['memberId'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $paymentMethod = trim((string) ($_POST['paymentMethod'] ?? 'Admin Override')) ?: 'Admin Override';
    $reason = trim((string) ($_POST['overrideReason'] ?? 'Admin dashboard booking edit')) ?: 'Admin dashboard booking edit';
    $playerNickname = '';

    if ($bookingId <= 0) {
        json_response(['ok' => false, 'message' => 'Invalid booking id.'], 422);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok' => false, 'message' => 'Use a valid booking date.'], 422);
    }
    if (!in_array($sport, ['Pickleball', 'Basketball', 'Volleyball'], true)) {
        json_response(['ok' => false, 'message' => 'Invalid sport.'], 422);
    }

    $existingStmt = $pdo->prepare(
        'SELECT cb.id, cb.booking_reference, cb.member_id, cb.booking_date, cb.time_slot_id,
                ts.label AS time_label, cb.court_id, cb.sport, cb.status, cb.customer_name,
                cb.player_nickname, cb.customer_email, cb.customer_phone, cb.payment_method, cb.final_amount
         FROM court_bookings cb
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         WHERE cb.id = ?'
    );
    $existingStmt->execute([$bookingId]);
    $existing = $existingStmt->fetch();
    if (!$existing) {
        json_response(['ok' => false, 'message' => 'Booking not found.'], 404);
    }
    if (!in_array((string) $existing['status'], ['Held', 'Booked'], true)) {
        json_response(['ok' => false, 'message' => 'Cancelled bookings cannot be edited.'], 422);
    }

    if ($memberId > 0) {
        $memberStmt = $pdo->prepare('SELECT id, name, nickname, phone, email FROM members WHERE id = ? AND is_active = 1');
        $memberStmt->execute([$memberId]);
        $member = $memberStmt->fetch();
        if (!$member) {
            json_response(['ok' => false, 'message' => 'Selected member was not found or is inactive.'], 422);
        }
        $name = (string) $member['name'];
        $phone = (string) $member['phone'];
        $email = (string) $member['email'];
        $playerNickname = trim((string) ($member['nickname'] ?? ''));
    }

    if ($name === '') {
        json_response(['ok' => false, 'message' => 'Customer name is required.'], 422);
    }
    $phone = validate_phone_field($phone, false, 'Customer phone');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['ok' => false, 'message' => 'Use a valid customer email.'], 422);
    }

    $slotStmt = $pdo->prepare('SELECT id, label, starts_at, ends_at, price FROM time_slots WHERE id = ?');
    $slotStmt->execute([$timeSlotId]);
    $slot = $slotStmt->fetch();
    if (!$slot) {
        json_response(['ok' => false, 'message' => 'Invalid time slot.'], 422);
    }
    if (!sport_time_slot_is_available($pdo, $sport, (int) $slot['id'])) {
        json_response(['ok' => false, 'message' => "{$sport} is not available for {$slot['label']}."], 422);
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

    $rate = calculate_booking_rate($pdo, $courtId, $sport, $date, $slot, false);
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    $pdo->beginTransaction();
    try {
        $conflict = active_court_conflict($pdo, $date, $timeSlotId, $courtId, $sport, $bookingId, true);
        if ($conflict !== null) {
            $pdo->rollBack();
            json_response(['ok' => false, 'message' => $conflict['message']], 409);
        }

        $blockConflict = active_block_conflict($pdo, $date, $timeSlotId, $courtId, $sport);
        if ($blockConflict !== null) {
            $pdo->rollBack();
            json_response(['ok' => false, 'message' => $blockConflict['message']], 409);
        }

        $stmt = $pdo->prepare(
            'UPDATE court_bookings
             SET member_id = ?, booking_date = ?, time_slot_id = ?, court_id = ?, sport = ?,
                 customer_name = ?, player_nickname = ?, customer_email = ?, customer_phone = ?,
                 payment_method = ?, base_rate = ?, final_amount = ?, rate_snapshot = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $memberId > 0 ? $memberId : null,
            $date,
            $timeSlotId,
            $courtId,
            $sport,
            $name,
            $playerNickname !== '' ? $playerNickname : (strtok($name, ' ') ?: $name),
            $email !== '' ? $email : null,
            $phone,
            $paymentMethod,
            $rate['baseRate'],
            $rate['finalAmount'],
            booking_rate_snapshot(['timeSlot' => $slot['label'], 'sport' => $sport, 'courtId' => $courtId, 'date' => $date, 'adminEdit' => true], $rate),
            $bookingId,
        ]);

        write_override_log(
            $pdo,
            (int) $admin['id'],
            'admin-booking-update',
            'court_booking',
            (string) $bookingId,
            '',
            [
                'bookingId' => $bookingId,
                'bookingReference' => $existing['booking_reference'] ?? '',
                'reason' => $reason,
                'previous' => [
                    'memberId' => $existing['member_id'] !== null ? (int) $existing['member_id'] : null,
                    'customerName' => $existing['customer_name'],
                    'customerEmail' => $existing['customer_email'] ?? '',
                    'customerPhone' => $existing['customer_phone'] ?? '',
                    'playerNickname' => $existing['player_nickname'] ?? '',
                    'date' => $existing['booking_date'],
                    'timeSlotId' => (int) $existing['time_slot_id'],
                    'time' => $existing['time_label'],
                    'courtId' => (int) $existing['court_id'],
                    'sport' => $existing['sport'],
                    'status' => $existing['status'],
                    'paymentMethod' => $existing['payment_method'],
                    'finalAmount' => (float) $existing['final_amount'],
                ],
                'updated' => [
                    'memberId' => $memberId > 0 ? $memberId : null,
                    'customerName' => $name,
                    'customerEmail' => $email,
                    'customerPhone' => $phone,
                    'playerNickname' => $playerNickname !== '' ? $playerNickname : (strtok($name, ' ') ?: $name),
                    'date' => $date,
                    'timeSlotId' => $timeSlotId,
                    'time' => $slot['label'],
                    'courtId' => $courtId,
                    'sport' => $sport,
                    'status' => $existing['status'],
                    'paymentMethod' => $paymentMethod,
                    'finalAmount' => (float) $rate['finalAmount'],
                ],
            ]
        );
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_database_write_conflict($exception)) {
            json_response(['ok' => false, 'message' => 'That time slot is no longer available. Please choose another slot.'], 409);
        }
        json_response(['ok' => false, 'message' => 'Booking update failed: ' . $exception->getMessage()], 500);
    }

    json_response([
        'ok' => true,
        'message' => 'Booking updated.',
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-rate-rule') {
    $admin = function_exists('require_operations_admin_json') ? require_operations_admin_json() : require_admin_json();

    $id = (int) ($_POST['id'] ?? 0);
    $courtValue = (string) require_field('courtId');
    $sport = require_field('sport');
    $daySelection = (string) ($_POST['dayOfWeek'] ?? 'Any');
    $rateMode = $id > 0 ? 'single' : (string) ($_POST['rateMode'] ?? 'single');
    $pricePerHour = (float) require_field('pricePerHour');
    $reason = trim((string) ($_POST['reason'] ?? 'Regular rate'));
    if ($reason === '') {
        $reason = 'Regular rate';
    }

    if (!in_array($sport, ['Pickleball', 'Basketball', 'Volleyball'], true)) {
        json_response(['ok' => false, 'message' => 'Invalid sport.'], 422);
    }
    if (!in_array($daySelection, valid_rate_day_selections(), true)) {
        json_response(['ok' => false, 'message' => 'Invalid day of week.'], 422);
    }
    $daySelections = expand_rate_day_selection($daySelection);
    if ($id > 0 && count($daySelections) > 1) {
        json_response(['ok' => false, 'message' => 'Weekday and Weekend shortcuts are only available when adding rates.'], 422);
    }
    if ($pricePerHour <= 0) {
        json_response(['ok' => false, 'message' => 'Rate per hour must be greater than zero.'], 422);
    }

    $applyAllCourts = $id === 0 && strtolower($courtValue) === 'all';
    if ($id > 0 && strtolower($courtValue) === 'all') {
        json_response(['ok' => false, 'message' => 'All courts is only available when adding rates.'], 422);
    }

    if ($applyAllCourts) {
        $stmt = $pdo->prepare(
            'SELECT id FROM courts
             WHERE is_active = 1 AND FIND_IN_SET(?, supported_sports) > 0
             ORDER BY display_number, id'
        );
        $stmt->execute([$sport]);
        $courtIds = array_map('intval', array_column($stmt->fetchAll(), 'id'));
        if ($courtIds === []) {
            json_response(['ok' => false, 'message' => 'No active courts support the selected sport.'], 422);
        }
    } else {
        $courtId = (int) $courtValue;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM courts WHERE id = ? AND is_active = 1');
        $stmt->execute([$courtId]);
        if ((int) $stmt->fetchColumn() === 0) {
            json_response(['ok' => false, 'message' => 'Invalid court.'], 422);
        }
        $courtIds = [$courtId];
    }

    $slotIds = [];
    $normalizeTime = static function (string $value): ?string {
        $value = trim($value);
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $matches)) {
            return null;
        }

        return $matches[1] . ':' . $matches[2] . ':00';
    };

    if ($id > 0) {
        $courtId = $courtIds[0];
        $dayOfWeek = $daySelections[0];
        $timeSlotId = (int) require_field('timeSlotId');
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM time_slots WHERE id = ?');
        $stmt->execute([$timeSlotId]);
        if ((int) $stmt->fetchColumn() === 0) {
            json_response(['ok' => false, 'message' => 'Invalid time slot.'], 422);
        }

        $duplicate = $pdo->prepare(
            'SELECT id FROM rates
             WHERE court_id = ?
               AND sport = ?
               AND day_of_week = ?
               AND time_slot_id = ?
               AND id <> ?
             LIMIT 1'
        );
        $duplicate->execute([$courtId, $sport, $dayOfWeek, $timeSlotId, $id]);
        if ($duplicate->fetch()) {
            json_response(['ok' => false, 'message' => 'Duplicate rate found for the same court, sport, and time slot.'], 409);
        }

        $stmt = $pdo->prepare('SELECT * FROM rates WHERE id = ?');
        $stmt->execute([$id]);
        $previous = $stmt->fetch();
        if (!$previous) {
            json_response(['ok' => false, 'message' => 'Rate not found.'], 404);
        }

        $stmt = $pdo->prepare(
            'UPDATE rates
             SET court_id = ?, sport = ?, day_of_week = ?, time_slot_id = ?, rate_per_hour = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $courtId, $sport, $dayOfWeek, $timeSlotId, $pricePerHour, $id,
        ]);
        $actionName = 'updated';

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
            json_encode($previous, JSON_THROW_ON_ERROR),
            json_encode($current, JSON_THROW_ON_ERROR),
            $reason,
        ]);
    } else {
        if (!in_array($rateMode, ['single', 'range'], true)) {
            json_response(['ok' => false, 'message' => 'Invalid rate mode.'], 422);
        }

        if ($rateMode === 'range') {
            $rangeStart = $normalizeTime((string) ($_POST['rangeStart'] ?? ''));
            $rangeEnd = $normalizeTime((string) ($_POST['rangeEnd'] ?? ''));
            if ($rangeStart === null || $rangeEnd === null) {
                json_response(['ok' => false, 'message' => 'Select a valid start and end time.'], 422);
            }
            $rangeStartMinutes = time_minutes_for_range($rangeStart);
            $rangeEndMinutes = time_minutes_for_range($rangeEnd, true);
            if ($rangeEndMinutes <= $rangeStartMinutes) {
                json_response(['ok' => false, 'message' => 'End time must be after start time.'], 422);
            }

            $slotRows = $pdo->query('SELECT id, starts_at, ends_at FROM time_slots ORDER BY sort_order, id')->fetchAll();
            $slotIds = [];
            foreach ($slotRows as $slotRow) {
                $slotStart = time_minutes_for_range((string) $slotRow['starts_at']);
                $slotEnd = time_minutes_for_range((string) $slotRow['ends_at'], true);
                if ($slotStart >= $rangeStartMinutes && $slotEnd <= $rangeEndMinutes) {
                    $slotIds[] = (int) $slotRow['id'];
                }
            }
            if ($slotIds === []) {
                json_response(['ok' => false, 'message' => 'No hourly slots exist inside the selected range.'], 422);
            }
        } else {
            $timeSlotId = (int) require_field('timeSlotId');
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM time_slots WHERE id = ?');
            $stmt->execute([$timeSlotId]);
            if ((int) $stmt->fetchColumn() === 0) {
                json_response(['ok' => false, 'message' => 'Invalid time slot.'], 422);
            }
            $slotIds = [$timeSlotId];
        }

        $lookup = $pdo->prepare(
            'SELECT * FROM rates
             WHERE court_id = ? AND sport = ? AND day_of_week = ? AND time_slot_id = ?
             LIMIT 1'
        );
        $update = $pdo->prepare(
            'UPDATE rates
             SET rate_per_hour = ?
             WHERE id = ?'
        );
        $insert = $pdo->prepare(
            'INSERT INTO rates
             (court_id, sport, day_of_week, time_slot_id, rate_per_hour)
             VALUES (?, ?, ?, ?, ?)'
        );
        $selectCurrent = $pdo->prepare('SELECT * FROM rates WHERE id = ?');
        $audit = $pdo->prepare(
            'INSERT INTO rate_audit_logs (rate_id, admin_id, action, previous_payload, new_payload, reason)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $created = 0;
        $updated = 0;
        $pdo->beginTransaction();
        try {
            foreach ($courtIds as $courtId) {
                foreach ($daySelections as $dayOfWeek) {
                    foreach ($slotIds as $slotId) {
                        $lookup->execute([$courtId, $sport, $dayOfWeek, $slotId]);
                        $previous = $lookup->fetch();
                        if ($previous) {
                            $currentId = (int) $previous['id'];
                            $update->execute([$pricePerHour, $currentId]);
                            $actionName = 'updated';
                            $updated++;
                        } else {
                            $insert->execute([$courtId, $sport, $dayOfWeek, $slotId, $pricePerHour]);
                            $currentId = (int) $pdo->lastInsertId();
                            $actionName = 'created';
                            $created++;
                        }

                        $selectCurrent->execute([$currentId]);
                        $current = $selectCurrent->fetch();
                        $audit->execute([
                            $currentId,
                            (int) $admin['id'],
                            $actionName,
                            $previous ? json_encode($previous, JSON_THROW_ON_ERROR) : null,
                            json_encode($current, JSON_THROW_ON_ERROR),
                            $reason,
                        ]);
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    json_response([
        'ok' => true,
        'message' => $id > 0
            ? 'Rate saved.'
            : sprintf('Rate saved for %d court%s, %d day%s, and %d slot%s%s.', count($courtIds), count($courtIds) === 1 ? '' : 's', count($daySelections), count($daySelections) === 1 ? '' : 's', count($slotIds), count($slotIds) === 1 ? '' : 's', isset($updated, $created) ? " ({$updated} updated, {$created} created)" : ''),
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

if ($action === 'admin-court-save') {
    require_operations_admin_json();

    $id = (int) ($_POST['id'] ?? 0);
    $displayNumber = (int) require_field('displayNumber');
    $name = trim((string) require_field('name'));
    $courtType = trim((string) require_field('courtType'));
    $surfaceLabel = trim((string) ($_POST['surfaceLabel'] ?? ''));
    $sports = normalize_supported_sports($_POST['sports'] ?? []);
    $isActive = isset($_POST['isActive']) && $_POST['isActive'] === '1';

    if ($displayNumber <= 0) {
        json_response(['ok' => false, 'message' => 'Display order must be greater than zero.'], 422);
    }
    if ($name === '' || strlen($name) > 80) {
        json_response(['ok' => false, 'message' => 'Enter a valid court name.'], 422);
    }
    if ($courtType === '' || strlen($courtType) > 80) {
        json_response(['ok' => false, 'message' => 'Enter a valid court type.'], 422);
    }
    if ($surfaceLabel !== '' && strlen($surfaceLabel) > 80) {
        json_response(['ok' => false, 'message' => 'Surface label is too long.'], 422);
    }
    if ($sports === []) {
        json_response(['ok' => false, 'message' => 'Select at least one supported sport.'], 422);
    }

    $sportsValue = implode(',', $sports);
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM courts WHERE id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() === 0) {
            json_response(['ok' => false, 'message' => 'Court not found.'], 404);
        }

        $stmt = $pdo->prepare(
            'UPDATE courts
             SET display_number = ?, name = ?, court_type = ?, surface_label = ?, supported_sports = ?, is_active = ?
             WHERE id = ?'
        );
        $stmt->execute([$displayNumber, $name, $courtType, $surfaceLabel !== '' ? $surfaceLabel : null, $sportsValue, $isActive ? 1 : 0, $id]);
        $courtId = $id;
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO courts (display_number, name, court_type, surface_label, supported_sports, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$displayNumber, $name, $courtType, $surfaceLabel !== '' ? $surfaceLabel : null, $sportsValue, $isActive ? 1 : 0]);
        $courtId = (int) $pdo->lastInsertId();
    }

    if ($isActive) {
        backfill_default_rates_for_court($pdo, $courtId, $sports);
    }

    json_response([
        'ok' => true,
        'message' => 'Court saved.',
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-sport-slot-availability') {
    $admin = require_staff_admin_json();
    if ((string) ($admin['role'] ?? '') !== 'super_admin') {
        json_response(['ok' => false, 'message' => 'Super Admin permission required.'], 403);
    }

    ensure_core_booking_time_slots($pdo);
    ensure_sport_time_slot_availability($pdo);

    $slotRows = $pdo->query('SELECT id FROM time_slots ORDER BY sort_order, id')->fetchAll();
    $slotIds = array_map('intval', array_column($slotRows, 'id'));
    if ($slotIds === []) {
        json_response(['ok' => false, 'message' => 'No time slots are configured.'], 422);
    }

    $postedAvailability = $_POST['availability'] ?? [];
    if (!is_array($postedAvailability)) {
        $postedAvailability = [];
    }

    $upsert = $pdo->prepare(
        'INSERT INTO sport_time_slot_availability (sport, time_slot_id, is_available, created_by, updated_by)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE is_available = VALUES(is_available), updated_by = VALUES(updated_by), updated_at = NOW()'
    );

    $pdo->beginTransaction();
    try {
        foreach (valid_booking_sports() as $sport) {
            $enabledIds = $postedAvailability[$sport] ?? [];
            if (!is_array($enabledIds)) {
                $enabledIds = [$enabledIds];
            }
            $enabledSet = array_fill_keys(array_map('intval', $enabledIds), true);
            foreach ($slotIds as $slotId) {
                $upsert->execute([
                    $sport,
                    $slotId,
                    !empty($enabledSet[$slotId]) ? 1 : 0,
                    (int) $admin['id'],
                    (int) $admin['id'],
                ]);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_response(['ok' => false, 'message' => 'Could not save sport time-slot availability.'], 500);
    }

    json_response([
        'ok' => true,
        'message' => 'Sport time-slot availability saved.',
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-court-block') {
    $admin = require_operations_admin_json();

    $id = (int) ($_POST['id'] ?? 0);
    $blockDate = require_field('blockDate');
    $timeSlotId = (int) require_field('timeSlotId');
    $startTime = trim((string) ($_POST['startTime'] ?? ''));
    $endTime = trim((string) ($_POST['endTime'] ?? ''));
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
    $timeSlotIds = court_block_slot_ids_for_request($pdo, $timeSlotId, $startTime, $endTime);
    $timeSlotId = $timeSlotIds[0];

    $conflicts = [];
    if ($isActive) {
        foreach ($timeSlotIds as $slotId) {
            $conflicts = array_merge($conflicts, active_bookings_for_block($pdo, $blockDate, $slotId, $courtId, $sport));
        }
    }
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
    $cancelledAt = $isActive ? null : date('Y-m-d H:i:s');

    $savedIds = [];
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM court_blocks WHERE id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() === 0) {
            json_response(['ok' => false, 'message' => 'Court block not found.'], 404);
        }

        $stmt = $pdo->prepare(
            'UPDATE court_blocks
             SET block_date = ?, time_slot_id = ?, court_id = ?, sport = ?, reason = ?, notes = ?,
                 status = ?, cancelled_by = ?, cancelled_at = ?
             WHERE id = ?'
        );
        $stmt->execute([$blockDate, $timeSlotId, $courtId, $sport, $reason, $notes, $status, $cancelledBy, $cancelledAt, $id]);
        $savedIds[] = $id;
        $remainingSlotIds = array_slice($timeSlotIds, 1);
        if ($isActive && $remainingSlotIds !== []) {
            $insert = $pdo->prepare(
                'INSERT INTO court_blocks
                 (block_date, time_slot_id, court_id, sport, reason, notes, status, created_by, cancelled_by, cancelled_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($remainingSlotIds as $slotId) {
                $insert->execute([$blockDate, $slotId, $courtId, $sport, $reason, $notes, $status, (int) $admin['id'], $cancelledBy, $cancelledAt]);
                $savedIds[] = (int) $pdo->lastInsertId();
            }
        }
        $message = count($savedIds) > 1
            ? 'Court block range updated.'
            : ($isActive ? 'Court block updated.' : 'Court block cancelled.');
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO court_blocks
             (block_date, time_slot_id, court_id, sport, reason, notes, status, created_by, cancelled_by, cancelled_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($timeSlotIds as $slotId) {
            $stmt->execute([$blockDate, $slotId, $courtId, $sport, $reason, $notes, $status, (int) $admin['id'], $cancelledBy, $cancelledAt]);
            $savedIds[] = (int) $pdo->lastInsertId();
        }
        $id = $savedIds[0] ?? 0;
        $message = $isActive
            ? (count($savedIds) > 1 ? 'Court block range created.' : 'Court block created.')
            : 'Cancelled block record saved.';
    }

    write_override_log(
        $pdo,
        (int) $admin['id'],
        'court-block-override',
        'court_block',
        implode(',', $savedIds),
        implode('; ', array_column($conflicts, 'summary')),
        [
            'blockIds' => $savedIds,
            'status' => $status,
            'isActive' => $isActive,
            'overrideConfirmed' => $overrideConfirm,
            'block' => [
                'blockDate' => $blockDate,
                'timeSlotIds' => $timeSlotIds,
                'courtId' => $courtId,
                'sport' => $sport,
                'reason' => $reason,
                'notes' => $notes,
            ],
            'conflicts' => $conflicts,
        ]
    );

    json_response([
        'ok' => true,
        'message' => $message,
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-court-block-status') {
    $admin = require_operations_admin_json();
    $ids = array_values(array_unique(array_filter(array_map(
        'intval',
        explode(',', (string) ($_POST['ids'] ?? ''))
    ), static fn (int $id): bool => $id > 0)));
    $isActive = isset($_POST['isActive']) && $_POST['isActive'] === '1';

    if ($ids === []) {
        json_response(['ok' => false, 'message' => 'Choose at least one court block record.'], 422);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, block_date, time_slot_id, court_id, sport, reason, notes, status
         FROM court_blocks
         WHERE id IN ({$placeholders})"
    );
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    if (count($rows) !== count($ids)) {
        json_response(['ok' => false, 'message' => 'One or more court block records were not found.'], 404);
    }

    if ($isActive) {
        $conflicts = [];
        foreach ($rows as $row) {
            $conflicts = array_merge(
                $conflicts,
                active_bookings_for_block(
                    $pdo,
                    (string) $row['block_date'],
                    (int) $row['time_slot_id'],
                    $row['court_id'] !== null ? (int) $row['court_id'] : null,
                    $row['sport'] !== null ? (string) $row['sport'] : null
                )
            );
        }
        if ($conflicts !== []) {
            json_response([
                'ok' => false,
                'message' => 'This block cannot be activated because it overlaps active reservations: ' . implode('; ', array_column($conflicts, 'summary')),
                'conflicts' => $conflicts,
            ], 409);
        }
    }

    $status = $isActive ? 'Active' : 'Cancelled';
    $cancelledBy = $isActive ? null : (int) $admin['id'];
    $cancelledAt = $isActive ? null : date('Y-m-d H:i:s');
    $params = array_merge([$status, $cancelledBy, $cancelledAt], $ids);
    $stmt = $pdo->prepare(
        "UPDATE court_blocks
         SET status = ?, cancelled_by = ?, cancelled_at = ?
         WHERE id IN ({$placeholders})"
    );
    $stmt->execute($params);

    write_override_log(
        $pdo,
        (int) $admin['id'],
        'court-block-status',
        'court_block',
        implode(',', $ids),
        '',
        [
            'blockIds' => $ids,
            'status' => $status,
            'isActive' => $isActive,
            'blocks' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'previousStatus' => $row['status'],
                'blockDate' => $row['block_date'],
                'timeSlotId' => (int) $row['time_slot_id'],
                'courtId' => $row['court_id'] !== null ? (int) $row['court_id'] : null,
                'sport' => $row['sport'],
                'reason' => $row['reason'],
                'notes' => $row['notes'] ?? '',
            ], $rows),
        ]
    );

    json_response([
        'ok' => true,
        'message' => $isActive ? 'Court blocking activated.' : 'Court blocking set inactive.',
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
    $isActive = isset($_POST['isActive']) && $_POST['isActive'] === '1' ? 1 : 0;

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
    require_members_admin_json();

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

if ($action === 'admin-member-save') {
    require_members_admin_json();
    ensure_member_terms_columns($pdo);

    $id = (int) ($_POST['id'] ?? 0);
    $name = require_field('name');
    $nickname = trim((string) ($_POST['nickname'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $email = strtolower(require_field('email'));
    $birthMonth = (int) ($_POST['birthMonth'] ?? 0);
    $birthYear = (int) ($_POST['birthYear'] ?? 0);
    $skillLevel = trim((string) ($_POST['skillLevel'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));
    $confirmPassword = trim((string) ($_POST['confirmPassword'] ?? ''));
    $isActive = isset($_POST['isActive']) && $_POST['isActive'] === '1' ? 1 : 0;
    $termsAgree = isset($_POST['termsConditionsAgree']) && $_POST['termsConditionsAgree'] === '1';
    $privacyAgree = isset($_POST['dataPrivacyActAgree']) && $_POST['dataPrivacyActAgree'] === '1';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['ok' => false, 'message' => 'Use a valid member email.'], 422);
    }
    $phone = validate_phone_field($phone, false, 'Member phone');
    if ($nickname === '') {
        json_response(['ok' => false, 'message' => 'Nickname is required.'], 422);
    }
    if ($birthMonth < 1 || $birthMonth > 12) {
        json_response(['ok' => false, 'message' => 'Choose a valid birth month.'], 422);
    }
    $currentYear = (int) date('Y');
    if ($birthYear < 1900 || $birthYear > $currentYear) {
        json_response(['ok' => false, 'message' => 'Choose a valid birth year.'], 422);
    }
    if (!in_array($skillLevel, ['2.0', '2.5', '3.0', '3.5', '4.0', '4.5', '5.0'], true)) {
        json_response(['ok' => false, 'message' => 'Choose a valid skill level.'], 422);
    }
    if (!$termsAgree) {
        json_response(['ok' => false, 'message' => 'Terms and Conditions agreement is required.'], 422);
    }
    if (!$privacyAgree) {
        json_response(['ok' => false, 'message' => 'Data Privacy Policy consent is required.'], 422);
    }
    if ($id === 0 && strlen($password) < 8) {
        json_response(['ok' => false, 'message' => 'New members need a password with at least 8 characters.'], 422);
    }
    if ($password !== '' && strlen($password) < 8) {
        json_response(['ok' => false, 'message' => 'Password must be at least 8 characters.'], 422);
    }
    if ($password !== '' && $password !== $confirmPassword) {
        json_response(['ok' => false, 'message' => 'Password confirmation does not match.'], 422);
    }
    $privacyPolicy = data_privacy_active_policy($pdo);
    $privacyVersion = (string) ($privacyPolicy['version'] ?? 'default');
    $profilePicture = save_member_profile_picture($memberUploadDir);

    if (!email_available_for_member($email, $id)) {
        json_response(['ok' => false, 'message' => 'That email is already used by another account.'], 422);
    }

    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT id, member_lookup_token, profile_picture_path FROM members WHERE id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            json_response(['ok' => false, 'message' => 'Member not found.'], 404);
        }
        $token = $existing['member_lookup_token'] ?: member_lookup_token_value();
        $profilePicturePath = $profilePicture ?? ($existing['profile_picture_path'] ?? null);

        if ($password !== '') {
            $stmt = $pdo->prepare(
                'UPDATE members
                 SET name = ?, nickname = ?, email = ?, phone = ?, profile_picture_path = ?, birth_month = ?, birth_year = ?,
                     skill_level = ?, terms_conditions_agree = 1, terms_agreed_at = COALESCE(terms_agreed_at, NOW()),
                     data_privacy_act_agree = 1, data_privacy_policy_version = ?,
                     data_privacy_agreed_at = COALESCE(data_privacy_agreed_at, NOW()),
                     member_lookup_token = ?, password_hash = ?, is_active = ?
                 WHERE id = ?'
            );
            $stmt->execute([$name, $nickname, $email, $phone, $profilePicturePath, $birthMonth, $birthYear, $skillLevel, $privacyVersion, $token, password_hash($password, PASSWORD_DEFAULT), $isActive, $id]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE members
                 SET name = ?, nickname = ?, email = ?, phone = ?, profile_picture_path = ?, birth_month = ?, birth_year = ?,
                     skill_level = ?, terms_conditions_agree = 1, terms_agreed_at = COALESCE(terms_agreed_at, NOW()),
                     data_privacy_act_agree = 1, data_privacy_policy_version = ?,
                     data_privacy_agreed_at = COALESCE(data_privacy_agreed_at, NOW()),
                     member_lookup_token = ?, is_active = ?
                 WHERE id = ?'
            );
            $stmt->execute([$name, $nickname, $email, $phone, $profilePicturePath, $birthMonth, $birthYear, $skillLevel, $privacyVersion, $token, $isActive, $id]);
        }
        $message = 'Member updated.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO members
             (name, nickname, email, phone, profile_picture_path, birth_month, birth_year, skill_level,
              terms_conditions_agree, terms_agreed_at, data_privacy_act_agree,
              data_privacy_policy_version, data_privacy_agreed_at, member_lookup_token, password_hash, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), 1, ?, NOW(), ?, ?, ?)'
        );
        $stmt->execute([$name, $nickname, $email, $phone, $profilePicture, $birthMonth, $birthYear, $skillLevel, $privacyVersion, member_lookup_token_value(), password_hash($password, PASSWORD_DEFAULT), $isActive]);
        $message = 'Member created.';
    }

    json_response([
        'ok' => true,
        'message' => $message,
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-member-lookup') {
    require_members_admin_json();

    $query = trim((string) ($_POST['query'] ?? $_GET['query'] ?? ''));
    $qrPayload = normalize_member_qr_payload(trim((string) ($_POST['qrPayload'] ?? $_GET['qrPayload'] ?? '')));
    if ($query === '' && $qrPayload === '') {
        json_response(['ok' => false, 'message' => 'Search text or QR payload is required.'], 422);
    }

    if ($qrPayload !== '') {
        $stmt = $pdo->prepare('SELECT id FROM members WHERE member_lookup_token = ? LIMIT 1');
        $stmt->execute([$qrPayload]);
    } else {
        $like = '%' . $query . '%';
        $stmt = $pdo->prepare('SELECT id FROM members WHERE name LIKE ? OR nickname LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY is_active DESC, name LIMIT 1');
        $stmt->execute([$like, $like, $like, $like]);
    }
    $memberId = (int) ($stmt->fetchColumn() ?: 0);
    json_response([
        'ok' => $memberId > 0,
        'memberId' => $memberId ?: null,
        'message' => $memberId > 0 ? 'Member found.' : 'No matching member found.',
        'state' => get_state($pdo, true),
    ], $memberId > 0 ? 200 : 404);
}

if ($action === 'admin-receipt-upload') {
    require_operations_admin_json();

    $id = require_field('id');
    [$type, $rawId] = array_pad(explode(':', $id, 2), 2, '');
    $reservationIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $rawId)), static fn (int $value): bool => $value > 0)));
    if ($reservationIds === [] || !in_array($type, ['court', 'openplay'], true) || ($type === 'openplay' && count($reservationIds) > 1)) {
        json_response(['ok' => false, 'message' => 'Invalid reservation id.'], 422);
    }
    $idPlaceholders = implode(',', array_fill(0, count($reservationIds), '?'));

    $receipt = save_receipt($receiptUploadDir);
    if ($receipt === null) {
        json_response(['ok' => false, 'message' => 'Choose a receipt or payment proof file.'], 422);
    }

    $table = $type === 'court' ? 'court_bookings' : 'open_play_reservations';
    $stmt = $pdo->prepare("UPDATE {$table} SET receipt_path = ? WHERE id IN ({$idPlaceholders})");
    $stmt->execute(array_merge([$receipt], $reservationIds));

    json_response([
        'ok' => true,
        'message' => 'Receipt uploaded.',
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-entrance-fee') {
    $admin = require_members_admin_json();

    $memberId = (int) require_field('memberId');
    $amount = (float) ($_POST['amount'] ?? 50);
    $paymentDate = trim((string) ($_POST['paymentDate'] ?? date('Y-m-d')));
    $paymentTime = trim((string) ($_POST['paymentTime'] ?? date('H:i')));
    $bookingId = (int) ($_POST['bookingId'] ?? 0);
    $referenceNumber = trim((string) ($_POST['referenceNumber'] ?? ''));
    $paymentMethod = trim((string) ($_POST['paymentMethod'] ?? 'Cash'));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($amount <= 0) {
        json_response(['ok' => false, 'message' => 'Entrance fee amount must be greater than zero.'], 422);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
        json_response(['ok' => false, 'message' => 'Use a valid payment date.'], 422);
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $paymentTime)) {
        json_response(['ok' => false, 'message' => 'Use a valid payment time.'], 422);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM members WHERE id = ?');
    $stmt->execute([$memberId]);
    if ((int) $stmt->fetchColumn() === 0) {
        json_response(['ok' => false, 'message' => 'Member not found.'], 404);
    }

    $receipt = save_receipt($receiptUploadDir);
    $stmt = $pdo->prepare(
        'INSERT INTO member_entrance_fee_payments
         (member_id, amount, payment_date, payment_time, booking_id, reference_number, payment_method, receipt_path, recorded_by, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$memberId, $amount, $paymentDate, $paymentTime . ':00', $bookingId > 0 ? $bookingId : null, $referenceNumber, $paymentMethod, $receipt, (int) $admin['id'], $notes]);

    json_response([
        'ok' => true,
        'message' => 'Entrance fee recorded.',
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-user-save') {
    $admin = require_staff_admin_json();

    $id = (int) ($_POST['id'] ?? 0);
    $name = require_field('name');
    $email = strtolower(require_field('email'));
    $role = trim((string) ($_POST['role'] ?? 'reception')) ?: 'reception';
    $password = trim((string) ($_POST['password'] ?? ''));
    $isActive = isset($_POST['isActive']) && $_POST['isActive'] === '1' ? 1 : 0;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['ok' => false, 'message' => 'Use a valid admin email.'], 422);
    }
    if (!in_array($role, array_keys(admin_role_options()), true)) {
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

    if (!email_available_for_admin_user($email, $id)) {
        json_response(['ok' => false, 'message' => 'That email is already used by another account.'], 422);
    }

    if ($id > 0) {
        $exists = $pdo->prepare('SELECT role FROM admin_users WHERE id = ?');
        $exists->execute([$id]);
        $existingRole = $exists->fetchColumn();
        if ($existingRole === false) {
            json_response(['ok' => false, 'message' => 'Admin user not found.'], 404);
        }
        if ($existingRole === 'super_admin') {
            $role = 'super_admin';
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

if ($action === 'admin-role-menu-permissions') {
    $admin = require_staff_admin_json();
    $role = trim((string) ($_POST['role'] ?? ''));
    $allowedMenus = $_POST['menus'] ?? [];
    if (!is_array($allowedMenus)) {
        $allowedMenus = [$allowedMenus];
    }

    if (!array_key_exists($role, admin_role_options())) {
        json_response(['ok' => false, 'message' => 'Invalid role.'], 422);
    }
    if ($role === 'super_admin') {
        json_response([
            'ok' => true,
            'message' => 'Super Admin always has full access.',
            'state' => get_state($pdo, true),
        ]);
    }

    $catalog = admin_menu_catalog();
    $allowedSet = array_fill_keys(array_values(array_filter(array_map('strval', $allowedMenus))), true);
    $allowedSet['admin'] = true;
    if ($role === 'reception') {
        $allowedSet['admin-members'] = true;
    }
    if ($role === 'admin') {
        $allowedSet['admin-members'] = true;
        unset($allowedSet['admin-sport-time-slots']);
    }
    if ($role === 'executive') {
        $allowedSet['admin-reports'] = true;
        unset($allowedSet['admin-members']);
    }

    admin_ensure_role_menu_table($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO admin_role_menu_permissions (role, menu_key, is_allowed, updated_by, updated_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed), updated_by = VALUES(updated_by), updated_at = NOW()'
    );
    foreach (array_keys($catalog) as $menuKey) {
        $stmt->execute([$role, $menuKey, !empty($allowedSet[$menuKey]) ? 1 : 0, (int) $admin['id']]);
    }

    json_response([
        'ok' => true,
        'message' => admin_role_label($role) . ' menu access updated.',
        'state' => get_state($pdo, true),
    ]);
}

json_response(['ok' => false, 'message' => 'Unknown action.'], 404);
