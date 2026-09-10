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

function api_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function api_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function api_index_columns(PDO $pdo, string $table, string $index): array
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
         ORDER BY SEQ_IN_INDEX'
    );
    $stmt->execute([$table, $index]);
    return array_map('strval', array_column($stmt->fetchAll(), 'COLUMN_NAME'));
}

function ensure_rate_management_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS holiday_schedules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `date` DATE NOT NULL,
            holiday_name VARCHAR(160) NOT NULL,
            UNIQUE KEY uniq_holiday_schedule_date (`date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS holiday_schedule_audit_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            holiday_schedule_id INT UNSIGNED NULL,
            admin_id INT UNSIGNED NULL,
            action VARCHAR(40) NOT NULL,
            previous_payload JSON NULL,
            new_payload JSON NULL,
            reason VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_holiday_schedule_audit (holiday_schedule_id, created_at),
            CONSTRAINT fk_holiday_schedule_audit_holiday FOREIGN KEY (holiday_schedule_id) REFERENCES holiday_schedules(id) ON DELETE SET NULL,
            CONSTRAINT fk_holiday_schedule_audit_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if (api_table_exists($pdo, 'rates') && api_column_exists($pdo, 'rates', 'day_of_week')) {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rates' AND COLUMN_NAME = 'day_of_week'
             LIMIT 1"
        );
        $stmt->execute();
        if (strpos((string) $stmt->fetchColumn(), "'Holiday'") === false) {
            $pdo->exec(
                "ALTER TABLE rates
                 MODIFY day_of_week ENUM('Any','Holiday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL DEFAULT 'Any'"
            );
        }

        if (!api_column_exists($pdo, 'rates', 'effective_date')) {
            $pdo->exec("ALTER TABLE rates ADD effective_date DATE NOT NULL DEFAULT '1970-01-01' AFTER rate_per_hour");
        }

        if (api_table_exists($pdo, 'rate_audit_logs') && api_column_exists($pdo, 'rate_audit_logs', 'rate_id')) {
            $pdo->exec(
                'UPDATE rate_audit_logs ral
                 JOIN (
                    SELECT duplicate_rate.id AS duplicate_id, MIN(kept_rate.id) AS kept_id
                    FROM rates duplicate_rate
                    JOIN rates kept_rate
                      ON duplicate_rate.court_id = kept_rate.court_id
                     AND duplicate_rate.sport = kept_rate.sport
                     AND duplicate_rate.day_of_week = kept_rate.day_of_week
                     AND duplicate_rate.time_slot_id = kept_rate.time_slot_id
                     AND duplicate_rate.effective_date = kept_rate.effective_date
                     AND duplicate_rate.id > kept_rate.id
                    GROUP BY duplicate_rate.id
                 ) duplicates ON duplicates.duplicate_id = ral.rate_id
                 SET ral.rate_id = duplicates.kept_id'
            );
        }
        $pdo->exec(
            'DELETE duplicate_rate
             FROM rates kept_rate
             JOIN rates duplicate_rate
               ON duplicate_rate.court_id = kept_rate.court_id
              AND duplicate_rate.sport = kept_rate.sport
              AND duplicate_rate.day_of_week = kept_rate.day_of_week
              AND duplicate_rate.time_slot_id = kept_rate.time_slot_id
              AND duplicate_rate.effective_date = kept_rate.effective_date
              AND duplicate_rate.id > kept_rate.id'
        );

        $lookupColumns = ['court_id', 'sport', 'day_of_week', 'time_slot_id', 'effective_date'];
        if (api_index_columns($pdo, 'rates', 'uniq_rate_lookup') !== $lookupColumns) {
            if (api_index_exists($pdo, 'rates', 'uniq_rate_lookup')) {
                $pdo->exec('ALTER TABLE rates DROP INDEX uniq_rate_lookup');
            }
            $pdo->exec('ALTER TABLE rates ADD UNIQUE KEY uniq_rate_lookup (court_id, sport, day_of_week, time_slot_id, effective_date)');
        }
        if (api_index_columns($pdo, 'rates', 'idx_rates_lookup') !== $lookupColumns) {
            if (api_index_exists($pdo, 'rates', 'idx_rates_lookup')) {
                $pdo->exec('ALTER TABLE rates DROP INDEX idx_rates_lookup');
            }
            $pdo->exec('ALTER TABLE rates ADD INDEX idx_rates_lookup (court_id, sport, day_of_week, time_slot_id, effective_date)');
        }
    }

    $done = true;
}

function ensure_booking_list_indexes(PDO $pdo): void
{
    static $done = false;
    if ($done || !api_table_exists($pdo, 'court_bookings')) {
        return;
    }

    $indexes = [
        'idx_booking_admin_status_created' => [
            'columns' => ['status', 'created_at', 'id'],
            'sql' => 'ALTER TABLE court_bookings ADD INDEX idx_booking_admin_status_created (status, created_at, id)',
        ],
        'idx_booking_admin_date_status' => [
            'columns' => ['booking_date', 'status', 'created_at'],
            'sql' => 'ALTER TABLE court_bookings ADD INDEX idx_booking_admin_date_status (booking_date, status, created_at)',
        ],
        'idx_booking_admin_reference_status' => [
            'columns' => ['booking_reference', 'status', 'booking_date'],
            'sql' => 'ALTER TABLE court_bookings ADD INDEX idx_booking_admin_reference_status (booking_reference, status, booking_date)',
        ],
        'idx_booking_admin_created_by' => [
            'columns' => ['created_by_type', 'created_by_id'],
            'sql' => 'ALTER TABLE court_bookings ADD INDEX idx_booking_admin_created_by (created_by_type, created_by_id)',
        ],
    ];

    foreach ($indexes as $index => $definition) {
        foreach ($definition['columns'] as $column) {
            if (!api_column_exists($pdo, 'court_bookings', $column)) {
                continue 2;
            }
        }
        if (!api_index_exists($pdo, 'court_bookings', $index)) {
            $pdo->exec($definition['sql']);
        }
    }

    $done = true;
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

function ensure_entrance_fee_activity_columns(PDO $pdo): void
{
    if (!api_column_exists($pdo, 'member_entrance_fee_payments', 'entry_type')) {
        $pdo->exec("ALTER TABLE member_entrance_fee_payments ADD entry_type VARCHAR(20) NOT NULL DEFAULT 'entrance_fee' AFTER id");
    }
    if (!api_column_exists($pdo, 'member_entrance_fee_payments', 'play_start_time')) {
        $pdo->exec('ALTER TABLE member_entrance_fee_payments ADD play_start_time TIME NULL AFTER payment_time');
    }
    if (!api_column_exists($pdo, 'member_entrance_fee_payments', 'play_date')) {
        $pdo->exec('ALTER TABLE member_entrance_fee_payments ADD play_date DATE NULL AFTER payment_time');
        $pdo->exec('UPDATE member_entrance_fee_payments SET play_date = payment_date WHERE play_date IS NULL');
    }
    if (!api_column_exists($pdo, 'member_entrance_fee_payments', 'play_end_time')) {
        $pdo->exec('ALTER TABLE member_entrance_fee_payments ADD play_end_time TIME NULL AFTER play_start_time');
    }
    if (!api_column_exists($pdo, 'member_entrance_fee_payments', 'played_hours')) {
        $pdo->exec('ALTER TABLE member_entrance_fee_payments ADD played_hours DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER play_end_time');
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
    return ['Any', 'Holiday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
}

function valid_rate_day_selections(): array
{
    return ['Any', 'Holiday', 'Weekday', 'Weekend', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
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
    ensure_rate_management_schema($pdo);

    $stmt = $pdo->query(
        "SELECT r.id, r.court_id, c.name AS court_name, r.sport, r.day_of_week, r.time_slot_id,
                ts.label AS time_label, ts.starts_at, ts.ends_at,
                r.rate_per_hour, r.effective_date, r.updated_at
         FROM rates r
         JOIN courts c ON c.id = r.court_id
         JOIN time_slots ts ON ts.id = r.time_slot_id
         ORDER BY c.display_number, c.id, r.sport, FIELD(r.day_of_week, 'Any', 'Holiday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), r.effective_date DESC, ts.sort_order, ts.id"
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
        'effectiveFrom' => $row['effective_date'] ?? '1970-01-01',
        'effectiveDate' => $row['effective_date'] ?? '1970-01-01',
        'effectiveTo' => null,
        'priority' => 0,
        'isActive' => true,
        'changeReason' => '',
        'updatedAt' => date(DATE_ATOM, strtotime($row['updated_at'])),
    ], $stmt->fetchAll());
}

function holiday_schedules(PDO $pdo): array
{
    ensure_rate_management_schema($pdo);

    $stmt = $pdo->query(
        'SELECT id, `date`, holiday_name
         FROM holiday_schedules
         ORDER BY `date` DESC, id DESC'
    );

    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'date' => $row['date'],
        'holidayName' => $row['holiday_name'],
    ], $stmt->fetchAll());
}

function holiday_name_for_date(PDO $pdo, string $date): ?string
{
    ensure_rate_management_schema($pdo);

    $stmt = $pdo->prepare('SELECT holiday_name FROM holiday_schedules WHERE `date` = ? LIMIT 1');
    $stmt->execute([$date]);
    $holidayName = $stmt->fetchColumn();

    return $holidayName === false ? null : (string) $holidayName;
}

function write_holiday_schedule_audit(PDO $pdo, ?int $holidayScheduleId, int $adminId, string $action, ?array $previous, ?array $current, string $reason): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO holiday_schedule_audit_logs (holiday_schedule_id, admin_id, action, previous_payload, new_payload, reason)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $holidayScheduleId,
        $adminId,
        $action,
        $previous ? json_encode($previous, JSON_THROW_ON_ERROR) : null,
        $current ? json_encode($current, JSON_THROW_ON_ERROR) : null,
        $reason !== '' ? $reason : null,
    ]);
}

function calculate_booking_rate(PDO $pdo, int $courtId, string $sport, string $date, array $slot, bool $isMember): array
{
    $duration = duration_hours((string) $slot['starts_at'], (string) $slot['ends_at']);
    $holidayName = holiday_name_for_date($pdo, $date);
    $dayOfWeek = $holidayName !== null ? 'Holiday' : date('l', strtotime($date));
    $stmt = $pdo->prepare(
        "SELECT r.id, r.rate_per_hour, r.day_of_week, r.effective_date, ts.label AS time_label
         FROM rates r
         JOIN time_slots ts ON ts.id = r.time_slot_id
         WHERE r.court_id = ? AND r.sport = ? AND r.time_slot_id = ?
           AND r.day_of_week IN ('Any', ?)
           AND r.effective_date <= ?
         ORDER BY CASE WHEN r.day_of_week = ? THEN 0 ELSE 1 END,
                  r.effective_date DESC,
                  r.id DESC
         LIMIT 1"
    );
    $stmt->execute([$courtId, $sport, (int) $slot['id'], $dayOfWeek, $date, $dayOfWeek]);
    $rate = $stmt->fetch();

    if ($rate) {
        $baseRate = (float) $rate['rate_per_hour'];
        $rateLabel = $rate['day_of_week'] === 'Any' ? 'Rate' : $rate['day_of_week'] . ' rate';
        if ($rate['day_of_week'] === 'Holiday' && $holidayName) {
            $rateLabel = 'Holiday rate (' . $holidayName . ')';
        }

        return [
            'baseRate' => $baseRate,
            'finalAmount' => $baseRate * $duration,
            'durationHours' => $duration,
            'ruleId' => (int) $rate['id'],
            'ruleName' => $rateLabel . ' for ' . $rate['time_label'] . ' effective ' . $rate['effective_date'],
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
    $exists = $pdo->prepare('SELECT id FROM time_slots WHERE starts_at = ? AND ends_at = ? LIMIT 1');
    $insert = $pdo->prepare(
        'INSERT INTO time_slots (period, label, starts_at, ends_at, price, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    for ($hour = 5; $hour < 24; $hour++) {
        $nextHour = ($hour + 1) % 24;
        $startsAt = sprintf('%02d:00:00', $hour);
        $endsAt = sprintf('%02d:00:00', $nextHour);
        $exists->execute([$startsAt, $endsAt]);
        if ($exists->fetchColumn()) {
            continue;
        }

        $period = $hour < 8 ? 'Early morning' : ($hour < 12 ? 'Morning' : ($hour < 18 ? 'Afternoon' : 'Evening'));
        $label = display_time_label($startsAt) . ' - ' . display_time_label($endsAt);
        $insert->execute([$period, $label, $startsAt, $endsAt, $fallbackPrice, $hour - 7]);
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
         ORDER BY cb.block_date DESC, ts.sort_order, cb.id DESC"
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

function is_miami_court(int $courtId): bool
{
    return $courtId === 2;
}

function is_wooden_court(int $courtId): bool
{
    return in_array($courtId, [7, 8, 9], true);
}

function court_booking_resources_conflict(int $existingCourtId, int $requestedCourtId): bool
{
    if ($existingCourtId === $requestedCourtId) {
        return true;
    }

    return (is_miami_court($existingCourtId) && is_wooden_court($requestedCourtId))
        || (is_wooden_court($existingCourtId) && is_miami_court($requestedCourtId));
}

function related_booking_conflict_court_ids(int $courtId): array
{
    if (is_miami_court($courtId)) {
        return [7, 8, 9];
    }
    if (is_wooden_court($courtId)) {
        return [2];
    }

    return [];
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

    $relatedCourtIds = related_booking_conflict_court_ids($courtId);
    if ($relatedCourtIds !== []) {
        $relatedPlaceholders = implode(',', array_fill(0, count($relatedCourtIds), '?'));
        $related = $pdo->prepare(
            "SELECT cb.id, cb.court_id, cb.sport, cb.status, c.name AS court_name, ts.label AS time_label
             FROM court_bookings cb
             LEFT JOIN courts c ON c.id = cb.court_id
             JOIN time_slots ts ON ts.id = cb.time_slot_id
             WHERE cb.booking_date = ? AND cb.time_slot_id = ? AND cb.court_id IN ({$relatedPlaceholders})
               AND cb.status IN (" . BLOCKING_RESERVATION_STATUS_SQL . ") {$excludeSql}
             LIMIT 1{$lockSql}"
        );
        $relatedParams = array_merge([$date, $slotId], $relatedCourtIds);
        if ($excludeBookingId !== null) {
            $relatedParams[] = $excludeBookingId;
        }
        $related->execute($relatedParams);
        $row = $related->fetch();
        if ($row) {
            $name = public_court_name((int) $row['court_id'], (string) $row['sport']);
            $requestedName = public_court_name($courtId, $sport);
            return [
                'message' => "{$requestedName} is unavailable because {$name} is already {$row['status']} for {$row['sport']} during {$row['time_label']}.",
                'blockingCourt' => $name,
                'blockingSport' => $row['sport'],
                'status' => $row['status'],
            ];
        }
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
            'timeSlotId' => $slotId,
            'date' => $date,
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
            $slotIds[] = [
                'id' => (int) $slot['id'],
                'start' => $slotStart,
                'end' => $slotEnd,
            ];
        }
    }

    if ($slotIds === []) {
        json_response(['ok' => false, 'message' => 'Choose a time range that matches available booking slots.'], 422);
    }

    usort($slotIds, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);
    if ($slotIds[0]['start'] !== $rangeStart || $slotIds[count($slotIds) - 1]['end'] !== $rangeEnd) {
        json_response(['ok' => false, 'message' => 'Choose a time range that fully matches available booking slots.'], 422);
    }
    for ($index = 1, $count = count($slotIds); $index < $count; $index++) {
        if ($slotIds[$index - 1]['end'] !== $slotIds[$index]['start']) {
            json_response(['ok' => false, 'message' => 'Choose a continuous blocking time range.'], 422);
        }
    }

    return array_column($slotIds, 'id');
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
        if (!court_booking_resources_conflict($existingCourt, $courtId)) {
            continue;
        }

        $courtName = public_court_name($existingCourt, $existingSport);
        $requestedCourtName = public_court_name($courtId, $sport);
        $summary = $existingCourt === $courtId
            ? "{$courtName} is currently reserved for {$existingSport} from {$row['time_label']} ({$row['status']}, {$row['customer_name']})."
            : "{$requestedCourtName} is unavailable because {$courtName} is currently reserved for {$existingSport} from {$row['time_label']} ({$row['status']}, {$row['customer_name']}).";
        $matches[] = [
            'id' => (int) $row['id'],
            'courtId' => $existingCourt,
            'courtName' => $courtName,
            'sport' => $existingSport,
            'status' => $row['status'],
            'customerName' => $row['customer_name'],
            'time' => $row['time_label'],
            'summary' => $summary,
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
    ensure_rate_management_schema($pdo);
    $sportSlotAvailability = sport_time_slot_availability_payload($pdo);
    $courts = $pdo->query('SELECT id, display_number, name, court_type, surface_label, supported_sports FROM courts WHERE is_active = 1 ORDER BY display_number, id')->fetchAll();
    $rateRows = $pdo->query(
        "SELECT r.court_id, c.name AS court_name, c.display_number, r.sport, r.rate_per_hour, ts.starts_at, ts.ends_at, ts.sort_order
         FROM rates r
         JOIN courts c ON c.id = r.court_id AND c.is_active = 1
         JOIN time_slots ts ON ts.id = r.time_slot_id
         WHERE r.day_of_week = 'Any'
         ORDER BY r.sport, c.display_number, c.id, r.rate_per_hour, ts.sort_order"
    )->fetchAll();
    $rateGroups = [];
    foreach ($rateRows as $row) {
        $key = (string) $row['sport'] . '|' . (string) $row['court_id'] . '|' . (string) $row['rate_per_hour'];
        if (!isset($rateGroups[$key])) {
            $rateGroups[$key] = [
                'courtId' => (int) $row['court_id'],
                'courtName' => (string) $row['court_name'],
                'courtSort' => (int) $row['display_number'],
                'sport' => (string) $row['sport'],
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
    usort($rateGroups, static fn (array $a, array $b): int => strcmp($a['sport'], $b['sport']) ?: $a['courtSort'] <=> $b['courtSort'] ?: $a['sort'] <=> $b['sort'] ?: $a['price'] <=> $b['price']);
    $rates = array_map(static fn (array $group): array => [
        'courtId' => $group['courtId'],
        'courtName' => $group['courtName'],
        'sport' => $group['sport'],
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
        'holidaySchedules' => holiday_schedules($pdo),
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
        $state['adminReservations'] = [];
        $state['adminGroupedReservations'] = [];
        $state['adminBookingPagination'] = [
            'page' => 1,
            'pageSize' => 20,
            'total' => 0,
            'totalPages' => 1,
            'from' => 0,
            'to' => 0,
        ];
        $state['adminBookingStatusCounts'] = ['Held' => 0, 'Booked' => 0, 'Cancelled' => 0, 'All' => 0];
        $state['adminPaymentChannels'] = payment_channels($pdo, true);
        $state['adminRateRules'] = rate_rules($pdo, true);
        $state['adminRateAudit'] = rate_audit_logs($pdo);
        $state['adminHolidayScheduleAudit'] = holiday_schedule_audit_logs($pdo);
        $state['adminCourts'] = admin_courts($pdo);
        $state['adminCourtBlocks'] = court_blocks($pdo, true);
        $state['adminOverrideLogs'] = override_logs($pdo);
        $state['adminMembers'] = $admin && admin_menu_allowed('admin-members', $admin) ? admin_members($pdo) : [];
        $state['adminUsers'] = $admin && admin_can_manage_staff($admin) ? admin_users_list($pdo) : [];
        $state['adminAccessLogs'] = $admin && admin_can_manage_staff($admin) ? admin_access_logs($pdo) : [];
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
                CONCAT(COALESCE(c.name, 'Deleted rate'), ' ', COALESCE(r.sport, ''), ' ', COALESCE(r.day_of_week, ''), ' ', COALESCE(ts.label, ''), ' effective ', COALESCE(r.effective_date, '')) AS rate_name,
                au.name AS admin_name,
                ral.action, ral.previous_payload, ral.new_payload, ral.reason, ral.created_at
         FROM rate_audit_logs ral
         LEFT JOIN rates r ON r.id = ral.rate_id
         LEFT JOIN courts c ON c.id = r.court_id
         LEFT JOIN time_slots ts ON ts.id = r.time_slot_id
         LEFT JOIN admin_users au ON au.id = ral.admin_id
         ORDER BY ral.created_at DESC, ral.id DESC
         LIMIT 12"
    );

    return array_map(static function (array $row): array {
        $payload = json_decode((string) ($row['new_payload'] ?: $row['previous_payload'] ?: ''), true) ?: [];
        $rateName = trim((string) $row['rate_name']);
        if ($rateName === '' || str_starts_with($rateName, 'Deleted rate')) {
            $rateName = trim(sprintf(
                'Court #%s %s %s slot #%s',
                $payload['court_id'] ?? '',
                $payload['sport'] ?? '',
                $payload['day_of_week'] ?? '',
                $payload['time_slot_id'] ?? ''
            )) ?: 'Deleted rate';
        }

        return [
            'id' => (int) $row['id'],
            'ruleId' => $row['rate_id'] !== null ? (int) $row['rate_id'] : null,
            'ruleName' => $rateName,
            'adminName' => $row['admin_name'] ?? 'System',
            'action' => $row['action'],
            'reason' => $row['reason'] ?? '',
            'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
        ];
    }, $stmt->fetchAll());
}

function holiday_schedule_audit_logs(PDO $pdo): array
{
    ensure_rate_management_schema($pdo);

    $stmt = $pdo->query(
        "SELECT hsal.id, hsal.holiday_schedule_id,
                COALESCE(hs.holiday_name, 'Deleted holiday') AS holiday_name,
                hs.`date` AS holiday_date,
                au.name AS admin_name,
                hsal.action, hsal.previous_payload, hsal.new_payload, hsal.reason, hsal.created_at
         FROM holiday_schedule_audit_logs hsal
         LEFT JOIN holiday_schedules hs ON hs.id = hsal.holiday_schedule_id
         LEFT JOIN admin_users au ON au.id = hsal.admin_id
         ORDER BY hsal.created_at DESC, hsal.id DESC
         LIMIT 12"
    );

    return array_map(static function (array $row): array {
        $payload = json_decode((string) ($row['new_payload'] ?: $row['previous_payload'] ?: ''), true) ?: [];

        return [
            'id' => (int) $row['id'],
            'holidayScheduleId' => $row['holiday_schedule_id'] !== null ? (int) $row['holiday_schedule_id'] : null,
            'holidayName' => $row['holiday_name'] !== 'Deleted holiday' ? $row['holiday_name'] : ($payload['holiday_name'] ?? 'Deleted holiday'),
            'holidayDate' => $row['holiday_date'] ?? ($payload['date'] ?? ''),
            'adminName' => $row['admin_name'] ?? 'System',
            'action' => $row['action'],
            'reason' => $row['reason'] ?? '',
            'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
        ];
    }, $stmt->fetchAll());
}

function valid_date_string(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $date;
}

function rate_day_applies_to_date(PDO $pdo, string $dayOfWeek, string $date): bool
{
    if ($dayOfWeek === 'Any') {
        return true;
    }

    $holidayName = holiday_name_for_date($pdo, $date);
    $bookingDay = $holidayName !== null ? 'Holiday' : date('l', strtotime($date));
    return $dayOfWeek === $bookingDay;
}

function advance_bookings_for_rate_change(PDO $pdo, array $courtIds, string $sport, array $daySelections, array $slotIds, string $effectiveDate): array
{
    if ($courtIds === [] || $slotIds === []) {
        return [];
    }

    $courtPlaceholders = implode(',', array_fill(0, count($courtIds), '?'));
    $slotPlaceholders = implode(',', array_fill(0, count($slotIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT cb.id, cb.booking_reference, cb.booking_date, cb.time_slot_id, cb.court_id,
                c.name AS court_name, cb.sport, cb.status, cb.customer_name,
                cb.base_rate, cb.final_amount, ts.label AS time_label
         FROM court_bookings cb
         JOIN courts c ON c.id = cb.court_id
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         WHERE cb.court_id IN ({$courtPlaceholders})
           AND cb.sport = ?
           AND cb.time_slot_id IN ({$slotPlaceholders})
           AND cb.booking_date >= ?
           AND cb.booking_date >= CURDATE()
           AND cb.status IN (" . BLOCKING_RESERVATION_STATUS_SQL . ")
         ORDER BY cb.booking_date, c.display_number, cb.court_id, ts.sort_order, cb.id"
    );
    $stmt->execute(array_merge($courtIds, [$sport], $slotIds, [$effectiveDate]));

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        foreach ($daySelections as $dayOfWeek) {
            if (!rate_day_applies_to_date($pdo, (string) $dayOfWeek, (string) $row['booking_date'])) {
                continue;
            }
            $rows[] = [
                'id' => (int) $row['id'],
                'reference' => $row['booking_reference'] ?? '',
                'date' => $row['booking_date'],
                'time' => $row['time_label'],
                'timeSlotId' => (int) $row['time_slot_id'],
                'courtId' => (int) $row['court_id'],
                'courtName' => $row['court_name'] ?: public_court_name((int) $row['court_id'], (string) $row['sport']),
                'sport' => $row['sport'],
                'status' => $row['status'],
                'customerName' => $row['customer_name'],
                'currentBaseRate' => (float) $row['base_rate'],
                'currentFinalAmount' => (float) $row['final_amount'],
            ];
            break;
        }
    }

    return $rows;
}

function update_advance_bookings_for_rate_change(PDO $pdo, array $bookingIds): int
{
    if ($bookingIds === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT cb.id, cb.booking_date, cb.time_slot_id, cb.court_id, cb.sport, ts.label, ts.starts_at, ts.ends_at, ts.price
         FROM court_bookings cb
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         WHERE cb.id IN ({$placeholders})
           AND cb.booking_date >= CURDATE()
           AND cb.status IN (" . BLOCKING_RESERVATION_STATUS_SQL . ")"
    );
    $stmt->execute($bookingIds);

    $update = $pdo->prepare(
        'UPDATE court_bookings
         SET base_rate = ?, final_amount = ?, rate_snapshot = ?
         WHERE id = ?'
    );

    $updated = 0;
    foreach ($stmt->fetchAll() as $row) {
        $slot = [
            'id' => (int) $row['time_slot_id'],
            'label' => $row['label'],
            'starts_at' => $row['starts_at'],
            'ends_at' => $row['ends_at'],
            'price' => $row['price'],
        ];
        $rate = calculate_booking_rate($pdo, (int) $row['court_id'], (string) $row['sport'], (string) $row['booking_date'], $slot, false);
        $update->execute([
            $rate['baseRate'],
            $rate['finalAmount'],
            booking_rate_snapshot([
                'timeSlot' => $slot['label'],
                'sport' => $row['sport'],
                'courtId' => (int) $row['court_id'],
                'date' => $row['booking_date'],
                'rateAdjustment' => true,
            ], $rate),
            (int) $row['id'],
        ]);
        $updated++;
    }

    return $updated;
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
    ensure_entrance_fee_activity_columns($pdo);

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
                ef.entry_type, ef.play_date, ef.play_start_time, ef.play_end_time, ef.played_hours,
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
            'entryType' => $row['entry_type'] ?? 'entrance_fee',
            'amount' => (float) $row['amount'],
            'paymentDate' => $row['payment_date'],
            'paymentTime' => substr((string) $row['payment_time'], 0, 5),
            'playDate' => $row['play_date'] ?? $row['payment_date'],
            'playStartTime' => $row['play_start_time'] ? substr((string) $row['play_start_time'], 0, 5) : '',
            'playEndTime' => $row['play_end_time'] ? substr((string) $row['play_end_time'], 0, 5) : '',
            'playedHours' => (float) ($row['played_hours'] ?? 0),
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

function admin_access_logs(PDO $pdo): array
{
    access_log_ensure_table($pdo);

    $stmt = $pdo->query(
        "SELECT al.id, al.account_type, al.account_id, al.role, al.event_type, al.session_id,
                al.ip_address, al.user_agent, al.session_payload, al.created_at,
                au.name AS admin_name, au.email AS admin_email,
                m.name AS member_name, m.nickname AS member_nickname, m.email AS member_email
         FROM access_logs al
         LEFT JOIN admin_users au ON al.account_type = 'admin' AND au.id = al.account_id
         LEFT JOIN members m ON al.account_type = 'member' AND m.id = al.account_id
         ORDER BY al.created_at DESC, al.id DESC
         LIMIT 300"
    );

    return array_map(static function (array $row): array {
        $payload = [];
        if (!empty($row['session_payload'])) {
            $decoded = json_decode((string) $row['session_payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $isAdmin = (string) $row['account_type'] === 'admin';
        $name = $isAdmin ? ($row['admin_name'] ?? '') : ($row['member_name'] ?? '');
        $email = $isAdmin ? ($row['admin_email'] ?? '') : ($row['member_email'] ?? '');

        return [
            'id' => (int) $row['id'],
            'accountType' => $row['account_type'],
            'accountId' => (int) $row['account_id'],
            'accountName' => $name ?: ucfirst((string) $row['account_type']) . ' #' . $row['account_id'],
            'accountEmail' => $email ?: '',
            'memberNickname' => $row['member_nickname'] ?? '',
            'role' => $row['role'] ?? '',
            'roleLabel' => $isAdmin ? admin_role_label((string) ($row['role'] ?? '')) : 'Member',
            'eventType' => $row['event_type'],
            'sessionId' => $row['session_id'] ?? '',
            'ipAddress' => $row['ip_address'] ?? '',
            'userAgent' => $row['user_agent'] ?? '',
            'sessionPayload' => $payload,
            'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
        ];
    }, $stmt->fetchAll());
}

function admin_reservations(PDO $pdo): array
{
    $courtRows = $pdo->query(
        "SELECT CONCAT('court:', cb.id) AS id, 'court' AS type, cb.booking_reference,
                cb.member_id, cb.booking_date AS date, cb.time_slot_id,
                ts.label AS time, cb.court_id AS court, c.name AS court_name, cb.sport, NULL AS session_id, NULL AS session_title,
                cb.status, cb.customer_name, cb.player_nickname, cb.customer_email, cb.customer_phone, cb.payment_method,
                cb.receipt_path, cb.final_amount, m.name AS member_name, m.nickname AS member_nickname,
                cb.created_by_type, cb.created_by_id,
                creator_admin.name AS creator_admin_name, creator_admin.role AS creator_admin_role,
                creator_member.name AS creator_member_name,
                cb.cancel_reason, cb.created_at, cb.reviewed_at, cb.cancelled_at,
                reviewer.name AS reviewed_by_name, canceller.name AS cancelled_by_name
         FROM court_bookings cb
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         LEFT JOIN courts c ON c.id = cb.court_id
         LEFT JOIN members m ON m.id = cb.member_id
         LEFT JOIN admin_users creator_admin ON cb.created_by_type = 'admin' AND creator_admin.id = cb.created_by_id
         LEFT JOIN members creator_member ON cb.created_by_type = 'member' AND creator_member.id = cb.created_by_id
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
        'createdByType' => $row['created_by_type'],
        'createdById' => $row['created_by_id'] !== null ? (int) $row['created_by_id'] : null,
        'createdByName' => $row['created_by_type'] === 'admin'
            ? ($row['creator_admin_name'] ?? '')
            : ($row['creator_member_name'] ?? ''),
        'createdByRole' => $row['created_by_type'] === 'admin' ? ($row['creator_admin_role'] ?? '') : 'member',
        'cancelReason' => $row['cancel_reason'],
        'reviewedByName' => $row['reviewed_by_name'],
        'cancelledByName' => $row['cancelled_by_name'],
        'createdAt' => db_datetime_to_ph_atom($row['created_at']),
        'reviewedAt' => $row['reviewed_at'] ? date(DATE_ATOM, strtotime($row['reviewed_at'])) : null,
        'cancelledAt' => $row['cancelled_at'] ? date(DATE_ATOM, strtotime($row['cancelled_at'])) : null,
    ], $rows);
}

function admin_booking_request_options(): array
{
    $status = trim((string) ($_GET['status'] ?? 'Held'));
    if (!in_array($status, ['Held', 'Booked', 'Cancelled', 'All'], true)) {
        $status = 'Held';
    }

    $startDate = trim((string) ($_GET['from'] ?? ''));
    $endDate = trim((string) ($_GET['to'] ?? ''));
    $isValidDate = static function (string $value): bool {
        if ($value === '') {
            return true;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    };
    if (!$isValidDate($startDate)) {
        $startDate = '';
    }
    if (!$isValidDate($endDate)) {
        $endDate = '';
    }
    if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
        [$startDate, $endDate] = [$endDate, $startDate];
    }

    $sort = trim((string) ($_GET['sort'] ?? 'created-desc'));
    if (!in_array($sort, ['created-desc', 'reservation-asc', 'reservation-desc'], true)) {
        $sort = 'created-desc';
    }

    $pageSize = (int) ($_GET['pageSize'] ?? 20);
    if (!in_array($pageSize, [10, 20, 50, 100], true)) {
        $pageSize = 20;
    }

    return [
        'status' => $status,
        'search' => trim((string) ($_GET['search'] ?? '')),
        'from' => $startDate,
        'to' => $endDate,
        'sort' => $sort,
        'page' => max(1, (int) ($_GET['page'] ?? 1)),
        'pageSize' => $pageSize,
    ];
}

function admin_booking_columns(PDO $pdo): array
{
    static $columns = null;
    if ($columns !== null) {
        return $columns;
    }

    $optionalColumns = [
        'booking_reference',
        'member_id',
        'player_nickname',
        'customer_email',
        'customer_phone',
        'payment_method',
        'receipt_path',
        'final_amount',
        'created_by_type',
        'created_by_id',
        'cancel_reason',
        'reviewed_by',
        'reviewed_at',
        'cancelled_by',
        'cancelled_at',
    ];
    $columns = [];
    foreach ($optionalColumns as $column) {
        try {
            $columns[$column] = api_column_exists($pdo, 'court_bookings', $column);
        } catch (Throwable) {
            $columns[$column] = false;
        }
    }
    try {
        $columns['members_table'] = api_table_exists($pdo, 'members');
        $columns['members_nickname'] = $columns['members_table'] && api_column_exists($pdo, 'members', 'nickname');
        $columns['courts_table'] = api_table_exists($pdo, 'courts');
        $columns['courts_name'] = $columns['courts_table'] && api_column_exists($pdo, 'courts', 'name');
        $columns['time_slots_label'] = api_column_exists($pdo, 'time_slots', 'label');
        $columns['time_slots_starts_at'] = api_column_exists($pdo, 'time_slots', 'starts_at');
        $columns['time_slots_ends_at'] = api_column_exists($pdo, 'time_slots', 'ends_at');
        $columns['time_slots_sort_order'] = api_column_exists($pdo, 'time_slots', 'sort_order');
    } catch (Throwable) {
        $columns['members_table'] = false;
        $columns['members_nickname'] = false;
        $columns['courts_table'] = false;
        $columns['courts_name'] = false;
        $columns['time_slots_label'] = true;
        $columns['time_slots_starts_at'] = false;
        $columns['time_slots_ends_at'] = false;
        $columns['time_slots_sort_order'] = false;
    }

    return $columns;
}

function admin_booking_column_sql(array $columns, string $column, string $fallback, ?string $alias = null): string
{
    $alias = $alias ?? $column;
    if (($columns[$column] ?? false) === true) {
        return "cb.{$column} AS {$alias}";
    }

    return "{$fallback} AS {$alias}";
}

function admin_booking_time_sort_sql(array $columns): string
{
    return ($columns['time_slots_sort_order'] ?? false) ? 'ts.sort_order' : 'ts.id';
}

function admin_booking_time_label_sql(array $columns, string $alias = 'time'): string
{
    if ($columns['time_slots_label'] ?? false) {
        return "ts.label AS {$alias}";
    }
    if (($columns['time_slots_starts_at'] ?? false) && ($columns['time_slots_ends_at'] ?? false)) {
        return "CONCAT(TIME_FORMAT(ts.starts_at, '%h:%i %p'), ' - ', TIME_FORMAT(ts.ends_at, '%h:%i %p')) AS {$alias}";
    }

    return "CONCAT('Slot #', ts.id) AS {$alias}";
}

function admin_booking_group_key_sql(array $columns = []): string
{
    if (($columns['booking_reference'] ?? true) === false) {
        return "CONCAT('id:', cb.id)";
    }

    return "COALESCE(NULLIF(cb.booking_reference, ''), CONCAT('id:', cb.id))";
}

function admin_booking_status_condition_sql(string $status): string
{
    return "CONVERT(cb.status USING utf8mb4) COLLATE utf8mb4_unicode_ci = '{$status}' COLLATE utf8mb4_unicode_ci";
}

function admin_booking_group_status_rank_sql(): string
{
    $held = admin_booking_status_condition_sql('Held');
    $booked = admin_booking_status_condition_sql('Booked');

    return "CASE
        WHEN SUM(CASE WHEN {$held} THEN 1 ELSE 0 END) > 0 THEN 1
        WHEN SUM(CASE WHEN {$booked} THEN 1 ELSE 0 END) > 0 THEN 2
        ELSE 3
    END";
}

function admin_booking_status_rank(string $status): int
{
    return match ($status) {
        'Held' => 1,
        'Booked' => 2,
        'Cancelled' => 3,
        default => 0,
    };
}

function admin_booking_status_from_rank(int $rank): string
{
    return match ($rank) {
        1 => 'Held',
        2 => 'Booked',
        default => 'Cancelled',
    };
}

function admin_booking_group_where_sql(array $options, array &$params, array $columns): string
{
    $where = [];
    if ($options['from'] !== '') {
        $where[] = 'cb.booking_date >= ?';
        $params[] = $options['from'];
    }
    if ($options['to'] !== '') {
        $where[] = 'cb.booking_date <= ?';
        $params[] = $options['to'];
    }
    if ($options['search'] !== '') {
        $searchTerm = '%' . $options['search'] . '%';
        if (($columns['booking_reference'] ?? false) === true) {
            $where[] = '(cb.booking_reference LIKE ? OR cb.customer_name LIKE ?)';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        } else {
            $where[] = 'cb.customer_name LIKE ?';
            $params[] = $searchTerm;
        }
    }

    return $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
}

function admin_booking_group_sql(array $options, array &$params, array $columns): string
{
    $where = admin_booking_group_where_sql($options, $params, $columns);
    $groupKey = admin_booking_group_key_sql($columns);
    $groupStatusRank = admin_booking_group_status_rank_sql();
    $timeSort = admin_booking_time_sort_sql($columns);

    return "SELECT {$groupKey} AS group_key,
                   {$groupStatusRank} AS group_status_rank,
                   MIN(cb.created_at) AS group_created_at,
                   MIN(CONCAT(cb.booking_date, ' ', LPAD({$timeSort}, 6, '0'), ' ', LPAD(cb.id, 10, '0'))) AS reservation_sort
            FROM court_bookings cb
            JOIN time_slots ts ON ts.id = cb.time_slot_id
            {$where}
            GROUP BY {$groupKey}";
}

function admin_booking_status_filter_sql(array $options, array &$params): string
{
    if ($options['status'] === 'All') {
        return '';
    }

    $params[] = admin_booking_status_rank($options['status']);
    return 'WHERE group_status_rank = ?';
}

function admin_booking_order_sql(string $sort): string
{
    if ($sort === 'reservation-asc') {
        return 'ORDER BY reservation_sort ASC, group_created_at ASC, group_key ASC';
    }
    if ($sort === 'reservation-desc') {
        return 'ORDER BY reservation_sort DESC, group_created_at DESC, group_key DESC';
    }

    return 'ORDER BY group_created_at DESC, group_key DESC';
}

function admin_court_reservation_payload(array $row): array
{
    return [
        'id' => $row['id'],
        'type' => 'court',
        'bookingReference' => $row['booking_reference'] ?? '',
        'memberId' => $row['member_id'] !== null ? (int) $row['member_id'] : null,
        'date' => $row['date'],
        'timeSlotId' => $row['time_slot_id'] !== null ? (int) $row['time_slot_id'] : null,
        'time' => $row['time'],
        'court' => $row['court'] !== null ? (int) $row['court'] : null,
        'courtName' => $row['court'] !== null ? (trim((string) ($row['court_name'] ?? '')) ?: public_court_name((int) $row['court'], (string) $row['sport'])) : null,
        'sport' => $row['sport'],
        'sessionId' => null,
        'sessionTitle' => null,
        'status' => $row['status'],
        'customerName' => $row['customer_name'],
        'playerNickname' => display_player_nickname($row),
        'customerEmail' => $row['customer_email'] ?? '',
        'customerPhone' => $row['customer_phone'] ?? '',
        'paymentMethod' => $row['payment_method'],
        'receipt' => $row['receipt_path'],
        'finalAmount' => (float) $row['final_amount'],
        'memberName' => $row['member_name'],
        'createdByType' => $row['created_by_type'],
        'createdById' => $row['created_by_id'] !== null ? (int) $row['created_by_id'] : null,
        'createdByName' => $row['created_by_type'] === 'admin'
            ? ($row['creator_admin_name'] ?? '')
            : ($row['creator_member_name'] ?? ''),
        'createdByRole' => $row['created_by_type'] === 'admin' ? ($row['creator_admin_role'] ?? '') : 'member',
        'cancelReason' => $row['cancel_reason'],
        'reviewedByName' => $row['reviewed_by_name'],
        'cancelledByName' => $row['cancelled_by_name'],
        'createdAt' => db_datetime_to_ph_atom($row['created_at']),
        'reviewedAt' => $row['reviewed_at'] ? date(DATE_ATOM, strtotime($row['reviewed_at'])) : null,
        'cancelledAt' => $row['cancelled_at'] ? date(DATE_ATOM, strtotime($row['cancelled_at'])) : null,
    ];
}

function admin_booking_rows_for_group_keys(PDO $pdo, array $groupKeys, array $columns): array
{
    if ($groupKeys === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($groupKeys), '?'));
    $groupKey = admin_booking_group_key_sql($columns);
    $timeSort = admin_booking_time_sort_sql($columns);
    $timeLabelSql = admin_booking_time_label_sql($columns, 'time');
    $courtJoin = ($columns['courts_table'] ?? false) ? 'LEFT JOIN courts c ON c.id = cb.court_id' : '';
    $courtNameSql = ($columns['courts_name'] ?? false) ? 'c.name AS court_name' : 'NULL AS court_name';
    $memberJoin = ($columns['member_id'] ?? false) && ($columns['members_table'] ?? false) ? 'LEFT JOIN members m ON m.id = cb.member_id' : '';
    $hasCreatorColumns = ($columns['created_by_type'] ?? false) && ($columns['created_by_id'] ?? false);
    $creatorAdminJoin = $hasCreatorColumns ? "LEFT JOIN admin_users creator_admin ON cb.created_by_type = 'admin' AND creator_admin.id = cb.created_by_id" : '';
    $creatorMemberJoin = $hasCreatorColumns && ($columns['members_table'] ?? false) ? "LEFT JOIN members creator_member ON cb.created_by_type = 'member' AND creator_member.id = cb.created_by_id" : '';
    $creatorJoins = trim($creatorAdminJoin . "\n         " . $creatorMemberJoin);
    $reviewerJoin = ($columns['reviewed_by'] ?? false) ? 'LEFT JOIN admin_users reviewer ON reviewer.id = cb.reviewed_by' : '';
    $cancellerJoin = ($columns['cancelled_by'] ?? false) ? 'LEFT JOIN admin_users canceller ON canceller.id = cb.cancelled_by' : '';
    $createdByTypeSql = ($columns['created_by_type'] ?? false)
        ? 'cb.created_by_type AS created_by_type'
        : (($columns['member_id'] ?? false) ? "CASE WHEN cb.member_id IS NOT NULL THEN 'member' ELSE NULL END AS created_by_type" : 'NULL AS created_by_type');
    $createdByIdSql = ($columns['created_by_id'] ?? false)
        ? 'cb.created_by_id AS created_by_id'
        : (($columns['member_id'] ?? false) ? 'cb.member_id AS created_by_id' : 'NULL AS created_by_id');
    $creatorAdminNameSql = $hasCreatorColumns ? 'creator_admin.name AS creator_admin_name' : 'NULL AS creator_admin_name';
    $creatorAdminRoleSql = $hasCreatorColumns ? 'creator_admin.role AS creator_admin_role' : 'NULL AS creator_admin_role';
    $creatorMemberNameSql = $hasCreatorColumns && ($columns['members_table'] ?? false)
        ? 'creator_member.name AS creator_member_name'
        : (($columns['member_id'] ?? false) && ($columns['members_table'] ?? false) ? 'm.name AS creator_member_name' : 'NULL AS creator_member_name');
    $memberNameSql = ($columns['member_id'] ?? false) && ($columns['members_table'] ?? false) ? 'm.name AS member_name' : 'NULL AS member_name';
    $memberNicknameSql = ($columns['member_id'] ?? false) && ($columns['members_nickname'] ?? false) ? 'm.nickname AS member_nickname' : 'NULL AS member_nickname';
    $reviewedByNameSql = ($columns['reviewed_by'] ?? false) ? 'reviewer.name AS reviewed_by_name' : 'NULL AS reviewed_by_name';
    $cancelledByNameSql = ($columns['cancelled_by'] ?? false) ? 'canceller.name AS cancelled_by_name' : 'NULL AS cancelled_by_name';
    $stmt = $pdo->prepare(
        "SELECT CONCAT('court:', cb.id) AS id,
                " . admin_booking_column_sql($columns, 'booking_reference', "''", 'booking_reference') . ",
                " . admin_booking_column_sql($columns, 'member_id', 'NULL', 'member_id') . ",
                cb.booking_date AS date, cb.time_slot_id,
                {$timeLabelSql}, cb.court_id AS court, {$courtNameSql}, cb.sport,
                cb.status, cb.customer_name,
                " . admin_booking_column_sql($columns, 'player_nickname', 'NULL', 'player_nickname') . ",
                " . admin_booking_column_sql($columns, 'customer_email', "''", 'customer_email') . ",
                " . admin_booking_column_sql($columns, 'customer_phone', "''", 'customer_phone') . ",
                " . admin_booking_column_sql($columns, 'payment_method', "''", 'payment_method') . ",
                " . admin_booking_column_sql($columns, 'receipt_path', 'NULL', 'receipt_path') . ",
                " . admin_booking_column_sql($columns, 'final_amount', '0', 'final_amount') . ",
                {$memberNameSql}, {$memberNicknameSql},
                {$createdByTypeSql}, {$createdByIdSql},
                {$creatorAdminNameSql}, {$creatorAdminRoleSql}, {$creatorMemberNameSql},
                " . admin_booking_column_sql($columns, 'cancel_reason', 'NULL', 'cancel_reason') . ",
                cb.created_at,
                " . admin_booking_column_sql($columns, 'reviewed_at', 'NULL', 'reviewed_at') . ",
                " . admin_booking_column_sql($columns, 'cancelled_at', 'NULL', 'cancelled_at') . ",
                {$reviewedByNameSql}, {$cancelledByNameSql}
         FROM court_bookings cb
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         {$courtJoin}
         {$memberJoin}
         {$creatorJoins}
         {$reviewerJoin}
         {$cancellerJoin}
         WHERE {$groupKey} IN ({$placeholders})
         ORDER BY cb.booking_date, cb.court_id, {$timeSort}, cb.id"
    );
    $stmt->execute($groupKeys);

    return array_map('admin_court_reservation_payload', $stmt->fetchAll());
}

function admin_booking_page(PDO $pdo, array $options): array
{
    try {
        ensure_booking_list_indexes($pdo);
    } catch (Throwable) {
        // Index creation is an optimization; the booking list should still load without ALTER privileges.
    }
    $columns = admin_booking_columns($pdo);

    $baseParams = [];
    $groupSql = admin_booking_group_sql($options, $baseParams, $columns);

    $countParams = $baseParams;
    $statusWhere = admin_booking_status_filter_sql($options, $countParams);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ({$groupSql}) grouped {$statusWhere}");
    $countStmt->execute($countParams);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $options['pageSize']));
    $page = min($options['page'], $totalPages);
    $offset = ($page - 1) * $options['pageSize'];

    $pageParams = $baseParams;
    $pageStatusWhere = admin_booking_status_filter_sql($options, $pageParams);
    $orderSql = admin_booking_order_sql($options['sort']);
    $limit = (int) $options['pageSize'];
    $pageStmt = $pdo->prepare(
        "SELECT group_key
         FROM ({$groupSql}) grouped
         {$pageStatusWhere}
         {$orderSql}
         LIMIT {$limit} OFFSET {$offset}"
    );
    $pageStmt->execute($pageParams);
    $groupKeys = array_map('strval', array_column($pageStmt->fetchAll(), 'group_key'));

    $statusStmt = $pdo->prepare(
        "SELECT group_status_rank, COUNT(*) AS total
         FROM ({$groupSql}) grouped
         GROUP BY group_status_rank"
    );
    $statusStmt->execute($baseParams);
    $statusCounts = ['Held' => 0, 'Booked' => 0, 'Cancelled' => 0, 'All' => 0];
    foreach ($statusStmt->fetchAll() as $row) {
        $status = admin_booking_status_from_rank((int) $row['group_status_rank']);
        $count = (int) $row['total'];
        $statusCounts[$status] = $count;
        $statusCounts['All'] += $count;
    }

    return [
        'reservations' => admin_booking_rows_for_group_keys($pdo, $groupKeys, $columns),
        'pagination' => [
            'page' => $page,
            'pageSize' => $options['pageSize'],
            'total' => $total,
            'totalPages' => $totalPages,
            'from' => $total === 0 ? 0 : $offset + 1,
            'to' => min($offset + $options['pageSize'], $total),
        ],
        'statusCounts' => $statusCounts,
    ];
}

function booking_history_actor(?string $type, ?int $id, ?string $name, ?string $role): string
{
    $type = trim((string) $type);
    $name = trim((string) $name);
    $role = trim((string) $role);
    if ($name !== '') {
        return $role !== '' ? "{$name} ({$role})" : $name;
    }
    if ($type !== '' && $id !== null && $id > 0) {
        return "{$type} #{$id}";
    }

    return 'System';
}

function booking_history_entry(string $createdAt, string $actor, string $action, string $reason = '', array $values = []): array
{
    return [
        'createdAt' => $createdAt,
        'actor' => $actor,
        'action' => $action,
        'reason' => $reason,
        'values' => $values,
    ];
}

function override_payload_matches_booking(array $payload, int $bookingId, string $bookingReference): bool
{
    if ((int) ($payload['bookingId'] ?? 0) === $bookingId) {
        return true;
    }
    if (in_array($bookingId, array_map('intval', (array) ($payload['bookingIds'] ?? [])), true)) {
        return true;
    }
    if ($bookingReference !== '' && (string) ($payload['bookingReference'] ?? '') === $bookingReference) {
        return true;
    }

    foreach (['cancelledBookings', 'conflicts'] as $key) {
        foreach ((array) ($payload[$key] ?? []) as $item) {
            if (is_array($item) && (int) ($item['id'] ?? 0) === $bookingId) {
                return true;
            }
        }
    }

    return false;
}

function booking_history_override_values(array $payload): array
{
    $source = is_array($payload['updated'] ?? null)
        ? $payload['updated']
        : (is_array($payload['block'] ?? null) ? $payload['block'] : $payload);
    $values = [];
    foreach ([
        'bookingReference' => 'Reference',
        'date' => 'Date',
        'time' => 'Time',
        'timeSlotId' => 'Time Slot ID',
        'courtId' => 'Court ID',
        'sport' => 'Sport',
        'status' => 'Status',
        'paymentMethod' => 'Payment',
        'customerName' => 'Customer',
        'customerPhone' => 'Phone',
        'customerEmail' => 'Email',
        'finalAmount' => 'Amount',
    ] as $key => $label) {
        if (!array_key_exists($key, $source) || $source[$key] === null || $source[$key] === '') {
            continue;
        }
        $value = is_array($source[$key]) ? implode(', ', array_map('strval', $source[$key])) : (string) $source[$key];
        $values[] = ['label' => $label, 'value' => $value];
    }

    return $values;
}

function booking_history_time_range_labels(array $rows, bool $includeDate = false): array
{
    $byDate = [];
    foreach ($rows as $row) {
        $date = (string) ($row['booking_date'] ?? '');
        $start = (string) ($row['starts_at'] ?? '');
        $end = (string) ($row['ends_at'] ?? '');
        if ($date === '' || !preg_match('/^\d{2}:\d{2}/', $start) || !preg_match('/^\d{2}:\d{2}/', $end)) {
            continue;
        }
        $byDate[$date][] = [
            'start' => time_minutes_for_range($start),
            'end' => time_minutes_for_range($end, true),
            'startsAt' => $start,
            'endsAt' => $end,
        ];
    }

    ksort($byDate);
    $labels = [];
    foreach ($byDate as $date => $slots) {
        usort($slots, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);
        $ranges = [];
        foreach ($slots as $slot) {
            $lastIndex = count($ranges) - 1;
            if ($lastIndex >= 0 && $ranges[$lastIndex]['end'] === $slot['start']) {
                $ranges[$lastIndex]['end'] = $slot['end'];
                $ranges[$lastIndex]['endsAt'] = $slot['endsAt'];
                continue;
            }
            $ranges[] = $slot;
        }

        $rangeLabels = array_map(
            static fn (array $range): string => display_time_label($range['startsAt']) . ' - ' . display_time_label($range['endsAt']),
            $ranges
        );
        $labels[] = ($includeDate ? "{$date}: " : '') . implode(', ', $rangeLabels);
    }

    return $labels;
}

function admin_booking_logs(PDO $pdo, int $bookingId, string $bookingReference = ''): array
{
    if ($bookingId <= 0 && $bookingReference === '') {
        json_response(['ok' => false, 'message' => 'Booking ID or reference is required.'], 422);
    }

    $lookupByReference = $bookingReference !== '';
    $where = $lookupByReference ? 'cb.booking_reference = ?' : 'cb.id = ?';
    $bookingStmt = $pdo->prepare(
        "SELECT cb.id, cb.booking_reference, cb.booking_date, cb.time_slot_id,
                ts.label AS time_label, ts.starts_at, ts.ends_at, ts.sort_order,
                cb.court_id, c.name AS court_name, cb.sport, cb.status, cb.customer_name,
                cb.payment_method, cb.final_amount, cb.created_by_type, cb.created_by_id,
                creator_admin.name AS creator_admin_name, creator_admin.role AS creator_admin_role,
                creator_member.name AS creator_member_name,
                cb.created_at, cb.reviewed_at, cb.cancelled_at, cb.cancel_reason,
                reviewer.name AS reviewed_by_name, reviewer.role AS reviewed_by_role,
                canceller.name AS cancelled_by_name, canceller.role AS cancelled_by_role
         FROM court_bookings cb
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         LEFT JOIN courts c ON c.id = cb.court_id
         LEFT JOIN admin_users creator_admin ON cb.created_by_type = 'admin' AND creator_admin.id = cb.created_by_id
         LEFT JOIN members creator_member ON cb.created_by_type = 'member' AND creator_member.id = cb.created_by_id
         LEFT JOIN admin_users reviewer ON reviewer.id = cb.reviewed_by
         LEFT JOIN admin_users canceller ON canceller.id = cb.cancelled_by
         WHERE {$where}
         ORDER BY cb.booking_date, cb.court_id, ts.sort_order, cb.id"
    );
    $bookingStmt->execute([$bookingReference !== '' ? $bookingReference : $bookingId]);
    $bookings = $bookingStmt->fetchAll();
    if ($bookings === []) {
        json_response(['ok' => false, 'message' => 'Booking not found.'], 404);
    }

    $booking = $bookings[0];
    $bookingReference = trim((string) ($booking['booking_reference'] ?? $bookingReference));
    $bookingIds = array_map(static fn (array $row): int => (int) $row['id'], $bookings);
    $bookingId = $bookingIds[0] ?? (int) $booking['id'];
    $unique = static fn (array $values): array => array_values(array_unique(array_filter(array_map(
        static fn ($value): string => trim((string) $value),
        $values
    ), static fn (string $value): bool => $value !== '')));
    $courtNames = $unique(array_map(static fn (array $row): string => trim((string) ($row['court_name'] ?? '')) ?: public_court_name((int) $row['court_id'], (string) $row['sport']), $bookings));
    $dates = $unique(array_column($bookings, 'booking_date'));
    $timeRanges = booking_history_time_range_labels($bookings, count($dates) > 1);
    $sports = $unique(array_column($bookings, 'sport'));
    $statuses = $unique(array_column($bookings, 'status'));
    $paymentMethods = $unique(array_column($bookings, 'payment_method'));
    $totalAmount = array_reduce($bookings, static fn (float $sum, array $row): float => $sum + (float) $row['final_amount'], 0.0);
    $createdAt = array_reduce($bookings, static function (?string $earliest, array $row): string {
        $value = (string) $row['created_at'];
        return $earliest === null || strcmp($value, $earliest) < 0 ? $value : $earliest;
    }, null) ?? (string) $booking['created_at'];
    $entries = [];
    $entries[] = booking_history_entry(
        db_datetime_to_ph_atom($createdAt) ?? date(DATE_ATOM, strtotime($createdAt)),
        booking_history_actor(
            $booking['created_by_type'],
            $booking['created_by_id'] !== null ? (int) $booking['created_by_id'] : null,
            $booking['created_by_type'] === 'admin' ? ($booking['creator_admin_name'] ?? '') : ($booking['creator_member_name'] ?? ''),
            $booking['created_by_type'] === 'admin' ? ($booking['creator_admin_role'] ?? '') : 'member'
        ),
        'Booking created',
        '',
        [
            ['label' => 'Reference', 'value' => $bookingReference ?: 'N/A'],
            ['label' => 'Date', 'value' => implode(', ', $dates)],
            ['label' => 'Time', 'value' => implode('; ', $timeRanges)],
            ['label' => 'Court', 'value' => implode(', ', $courtNames)],
            ['label' => 'Sport', 'value' => implode(', ', $sports)],
            ['label' => 'Status', 'value' => implode(', ', $statuses)],
            ['label' => 'Payment', 'value' => implode(', ', $paymentMethods)],
            ['label' => 'Amount', 'value' => number_format($totalAmount, 2, '.', '')],
        ]
    );

    $reviewGroups = [];
    foreach ($bookings as $row) {
        if (empty($row['reviewed_at'])) {
            continue;
        }
        $key = implode('|', [$row['reviewed_at'], $row['reviewed_by_name'] ?? '', $row['reviewed_by_role'] ?? '']);
        if (!isset($reviewGroups[$key])) {
            $reviewGroups[$key] = ['row' => $row, 'rows' => []];
        }
        $reviewGroups[$key]['rows'][] = $row;
    }
    foreach ($reviewGroups as $group) {
        $row = $group['row'];
        $entries[] = booking_history_entry(
            date(DATE_ATOM, strtotime($row['reviewed_at'])),
            booking_history_actor('admin', null, $row['reviewed_by_name'] ?? '', $row['reviewed_by_role'] ?? ''),
            'Booking confirmed',
            '',
            [
                ['label' => 'Status', 'value' => 'Booked'],
                ['label' => 'Time', 'value' => implode('; ', booking_history_time_range_labels($group['rows'], count($unique(array_column($group['rows'], 'booking_date'))) > 1))],
            ]
        );
    }

    $cancelGroups = [];
    foreach ($bookings as $row) {
        if (empty($row['cancelled_at'])) {
            continue;
        }
        $key = implode('|', [$row['cancelled_at'], $row['cancelled_by_name'] ?? '', $row['cancelled_by_role'] ?? '', $row['cancel_reason'] ?? '']);
        if (!isset($cancelGroups[$key])) {
            $cancelGroups[$key] = ['row' => $row, 'rows' => []];
        }
        $cancelGroups[$key]['rows'][] = $row;
    }
    foreach ($cancelGroups as $group) {
        $row = $group['row'];
        $entries[] = booking_history_entry(
            date(DATE_ATOM, strtotime($row['cancelled_at'])),
            booking_history_actor('admin', null, $row['cancelled_by_name'] ?? '', $row['cancelled_by_role'] ?? ''),
            'Booking cancelled',
            $row['cancel_reason'] ?? '',
            [
                ['label' => 'Status', 'value' => 'Cancelled'],
                ['label' => 'Time', 'value' => implode('; ', booking_history_time_range_labels($group['rows'], count($unique(array_column($group['rows'], 'booking_date'))) > 1))],
            ]
        );
    }

    $logStmt = $pdo->query(
        "SELECT ol.action, ol.target_type, ol.target_id, ol.conflict_summary, ol.payload, ol.created_at,
                au.name AS admin_name, au.role AS admin_role
         FROM override_logs ol
         LEFT JOIN admin_users au ON au.id = ol.admin_id
         WHERE ol.target_type IN ('court_booking','court_block')
         ORDER BY ol.created_at DESC, ol.id DESC
         LIMIT 500"
    );
    foreach ($logStmt->fetchAll() as $row) {
        $targetIds = array_filter(array_map('trim', explode(',', (string) ($row['target_id'] ?? ''))));
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        $payload = is_array($payload) ? $payload : [];
        $matchesTarget = array_intersect(array_map('strval', $bookingIds), $targetIds) !== [];
        $matchesPayload = false;
        foreach ($bookingIds as $id) {
            if (override_payload_matches_booking($payload, $id, $bookingReference)) {
                $matchesPayload = true;
                break;
            }
        }
        if (!$matchesTarget && !$matchesPayload) {
            continue;
        }

        $entries[] = booking_history_entry(
            date(DATE_ATOM, strtotime($row['created_at'])),
            booking_history_actor('admin', null, $row['admin_name'] ?? '', $row['admin_role'] ?? ''),
            ucwords(str_replace('-', ' ', (string) $row['action'])),
            (string) ($payload['reason'] ?? ($payload['block']['reason'] ?? $row['conflict_summary'] ?? '')),
            booking_history_override_values($payload)
        );
    }

    usort($entries, static fn (array $a, array $b): int => strcmp((string) $b['createdAt'], (string) $a['createdAt']));

    return [
        'booking' => [
            'id' => $bookingId,
            'ids' => $bookingIds,
            'reference' => $bookingReference,
            'customerName' => $booking['customer_name'],
            'status' => implode(', ', $statuses),
        ],
        'logs' => $entries,
    ];
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'state';
$pdo = db_or_error();

if ($action === 'state') {
    json_response(['ok' => true, 'state' => get_state($pdo, current_admin() !== null)]);
}

if ($action === 'admin-booking-logs') {
    require_admin_json();
    $id = (int) str_replace('court:', '', (string) ($_GET['id'] ?? ''));
    $reference = trim((string) ($_GET['reference'] ?? ''));
    json_response(['ok' => true] + admin_booking_logs($pdo, $id, $reference));
}

if ($action === 'admin-bookings') {
    require_admin_json();
    json_response(['ok' => true] + admin_booking_page($pdo, admin_booking_request_options()));
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
             (booking_reference, member_id, booking_date, time_slot_id, court_id, sport, status, customer_name, player_nickname, customer_email, customer_phone, customer_notes, payment_method, receipt_path, base_rate, final_amount, rate_snapshot, created_by_type, created_by_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
            'member',
            (int) $member['id'],
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
             (booking_reference, member_id, booking_date, time_slot_id, court_id, sport, status, customer_name, player_nickname, customer_email, customer_phone, payment_method, base_rate, final_amount, rate_snapshot, reviewed_by, reviewed_at, created_by_type, created_by_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
                'admin',
                (int) $admin['id'],
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
                'createdByType' => 'admin',
                'createdById' => (int) $admin['id'],
                'createdByRole' => $admin['role'] ?? '',
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

if ($action === 'admin-holiday-schedule') {
    $admin = function_exists('require_operations_admin_json') ? require_operations_admin_json() : require_admin_json();
    ensure_rate_management_schema($pdo);

    $id = (int) ($_POST['id'] ?? 0);
    $date = (string) require_field('date');
    $holidayName = trim((string) require_field('holidayName'));
    $reason = trim((string) ($_POST['reason'] ?? 'Holiday schedule change'));
    if ($reason === '') {
        $reason = 'Holiday schedule change';
    }

    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
        json_response(['ok' => false, 'message' => 'Enter a valid holiday date.'], 422);
    }
    if ($holidayName === '' || strlen($holidayName) > 160) {
        json_response(['ok' => false, 'message' => 'Enter a valid holiday name.'], 422);
    }

    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM holiday_schedules WHERE id = ?');
        $stmt->execute([$id]);
        $previous = $stmt->fetch();
        if (!$previous) {
            json_response(['ok' => false, 'message' => 'Holiday schedule not found.'], 404);
        }

        $duplicate = $pdo->prepare('SELECT id FROM holiday_schedules WHERE `date` = ? AND id <> ? LIMIT 1');
        $duplicate->execute([$date, $id]);
        if ($duplicate->fetch()) {
            json_response(['ok' => false, 'message' => 'A holiday schedule already exists for this date.'], 409);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE holiday_schedules SET `date` = ?, holiday_name = ? WHERE id = ?');
            $stmt->execute([$date, $holidayName, $id]);

            $stmt = $pdo->prepare('SELECT * FROM holiday_schedules WHERE id = ?');
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            write_holiday_schedule_audit($pdo, $id, (int) $admin['id'], 'updated', $previous, $current ?: null, $reason);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_database_write_conflict($exception)) {
                json_response(['ok' => false, 'message' => 'A holiday schedule already exists for this date.'], 409);
            }
            throw $exception;
        }
    } else {
        $duplicate = $pdo->prepare('SELECT id FROM holiday_schedules WHERE `date` = ? LIMIT 1');
        $duplicate->execute([$date]);
        if ($duplicate->fetch()) {
            json_response(['ok' => false, 'message' => 'A holiday schedule already exists for this date.'], 409);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO holiday_schedules (`date`, holiday_name) VALUES (?, ?)');
            $stmt->execute([$date, $holidayName]);
            $id = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare('SELECT * FROM holiday_schedules WHERE id = ?');
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            write_holiday_schedule_audit($pdo, $id, (int) $admin['id'], 'created', null, $current ?: null, $reason);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_database_write_conflict($exception)) {
                json_response(['ok' => false, 'message' => 'A holiday schedule already exists for this date.'], 409);
            }
            throw $exception;
        }
    }

    json_response([
        'ok' => true,
        'message' => 'Holiday schedule saved.',
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-holiday-delete') {
    $admin = function_exists('require_operations_admin_json') ? require_operations_admin_json() : require_admin_json();
    ensure_rate_management_schema($pdo);

    $id = (int) require_field('id');
    $stmt = $pdo->prepare('SELECT * FROM holiday_schedules WHERE id = ?');
    $stmt->execute([$id]);
    $previous = $stmt->fetch();
    if (!$previous) {
        json_response(['ok' => false, 'message' => 'Holiday schedule not found.'], 404);
    }

    $reason = trim((string) ($_POST['reason'] ?? 'Holiday schedule deleted'));
    if ($reason === '') {
        $reason = 'Holiday schedule deleted';
    }

    $pdo->beginTransaction();
    try {
        $delete = $pdo->prepare('DELETE FROM holiday_schedules WHERE id = ?');
        $delete->execute([$id]);
        write_holiday_schedule_audit($pdo, null, (int) $admin['id'], 'deleted', $previous, null, $reason);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    json_response([
        'ok' => true,
        'message' => 'Holiday schedule deleted.',
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-rate-rule') {
    $admin = function_exists('require_operations_admin_json') ? require_operations_admin_json() : require_admin_json();
    ensure_rate_management_schema($pdo);

    $id = (int) ($_POST['id'] ?? 0);
    $courtValue = (string) require_field('courtId');
    $sport = require_field('sport');
    $daySelection = (string) ($_POST['dayOfWeek'] ?? 'Any');
    $pricePerHour = (float) require_field('pricePerHour');
    $reason = trim((string) ($_POST['reason'] ?? 'Regular rate'));
    $effectiveDate = trim((string) ($_POST['effectiveDate'] ?? date('Y-m-d')));
    $advanceBookingChoice = trim((string) ($_POST['advanceBookingChoice'] ?? ''));
    if ($reason === '') {
        $reason = 'Regular rate';
    }

    if (!valid_date_string($effectiveDate)) {
        json_response(['ok' => false, 'message' => 'Use a valid effective date.'], 422);
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

    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM rates WHERE id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() === 0) {
            json_response(['ok' => false, 'message' => 'Rate not found.'], 404);
        }
    }

    $slotIds = [];
    $normalizeTime = static function (string $value): ?string {
        $value = trim($value);
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $matches)) {
            return null;
        }

        return $matches[1] . ':' . $matches[2] . ':00';
    };

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

    $sameVersionCheck = $pdo->prepare(
        'SELECT COUNT(*) FROM rates
         WHERE court_id = ? AND sport = ? AND day_of_week = ? AND time_slot_id = ? AND effective_date = ?'
    );
    foreach ($courtIds as $courtId) {
        foreach ($daySelections as $dayOfWeek) {
            foreach ($slotIds as $slotId) {
                $sameVersionCheck->execute([$courtId, $sport, $dayOfWeek, $slotId, $effectiveDate]);
                if ((int) $sameVersionCheck->fetchColumn() > 0) {
                    json_response(['ok' => false, 'message' => 'A rate already exists for the same court, sport, day, time slot, and effective date. Choose a different effective date or delete the existing rate version first.'], 409);
                }
            }
        }
    }

    $affectedBookings = advance_bookings_for_rate_change($pdo, $courtIds, $sport, $daySelections, $slotIds, $effectiveDate);
    if ($affectedBookings !== [] && !in_array($advanceBookingChoice, ['update', 'keep'], true)) {
        json_response([
            'ok' => false,
            'requiresAdvanceRateChoice' => true,
            'message' => 'There are existing advance bookings affected by this rate change. Do you want to update them to the new rate or keep their current rates?',
            'affectedBookings' => $affectedBookings,
        ], 409);
    }

    $insert = $pdo->prepare(
        'INSERT INTO rates
         (court_id, sport, day_of_week, time_slot_id, rate_per_hour, effective_date)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $selectPrevious = $pdo->prepare(
        'SELECT * FROM rates
         WHERE court_id = ? AND sport = ? AND day_of_week = ? AND time_slot_id = ? AND effective_date < ?
         ORDER BY effective_date DESC, id DESC
         LIMIT 1'
    );
    $selectCurrent = $pdo->prepare('SELECT * FROM rates WHERE id = ?');
    $audit = $pdo->prepare(
        'INSERT INTO rate_audit_logs (rate_id, admin_id, action, previous_payload, new_payload, reason)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $created = 0;
    $updatedBookings = 0;
    $pdo->beginTransaction();
    try {
        foreach ($courtIds as $courtId) {
            foreach ($daySelections as $dayOfWeek) {
                foreach ($slotIds as $slotId) {
                    $selectPrevious->execute([$courtId, $sport, $dayOfWeek, $slotId, $effectiveDate]);
                    $previous = $selectPrevious->fetch() ?: null;
                    $insert->execute([$courtId, $sport, $dayOfWeek, $slotId, $pricePerHour, $effectiveDate]);
                    $currentId = (int) $pdo->lastInsertId();
                    $selectCurrent->execute([$currentId]);
                    $current = $selectCurrent->fetch();
                    $created++;

                    $audit->execute([
                        $currentId,
                        (int) $admin['id'],
                        $previous ? 'scheduled_adjustment' : 'created',
                        $previous ? json_encode($previous, JSON_THROW_ON_ERROR) : null,
                        json_encode($current, JSON_THROW_ON_ERROR),
                        $reason,
                    ]);
                }
            }
        }
        if ($advanceBookingChoice === 'update' && $affectedBookings !== []) {
            $affectedBookingIds = array_values(array_unique(array_map('intval', array_column($affectedBookings, 'id'))));
            $updatedBookings = update_advance_bookings_for_rate_change($pdo, $affectedBookingIds);
            write_override_log(
                $pdo,
                (int) $admin['id'],
                'rate-adjustment-advance-bookings',
                'court_booking',
                implode(',', $affectedBookingIds),
                'Advance booking rates updated after rate adjustment.',
                [
                    'bookingIds' => $affectedBookingIds,
                    'effectiveDate' => $effectiveDate,
                    'courtIds' => $courtIds,
                    'sport' => $sport,
                    'daySelections' => $daySelections,
                    'timeSlotIds' => $slotIds,
                    'ratePerHour' => $pricePerHour,
                ]
            );
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    json_response([
        'ok' => true,
        'message' => sprintf('Rate adjustment saved for %d court%s, %d day%s, and %d slot%s (%d new version%s, %d advance booking%s updated).', count($courtIds), count($courtIds) === 1 ? '' : 's', count($daySelections), count($daySelections) === 1 ? '' : 's', count($slotIds), count($slotIds) === 1 ? '' : 's', $created, $created === 1 ? '' : 's', $updatedBookings, $updatedBookings === 1 ? '' : 's'),
        'state' => get_state($pdo, true),
    ]);
}

if ($action === 'admin-rate-delete') {
    $admin = function_exists('require_operations_admin_json') ? require_operations_admin_json() : require_admin_json();
    ensure_rate_management_schema($pdo);

    $rawIds = trim((string) ($_POST['ids'] ?? ''));
    $ids = [];
    if ($rawIds !== '') {
        foreach (explode(',', $rawIds) as $rawId) {
            $id = (int) trim($rawId);
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
    }
    if ($ids === []) {
        $ids[] = (int) require_field('id');
    }
    $ids = array_values(array_filter(array_unique($ids), static fn (int $id): bool => $id > 0));
    if ($ids === []) {
        json_response(['ok' => false, 'message' => 'Rate not found.'], 404);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM rates WHERE id IN ({$placeholders}) ORDER BY id");
    $stmt->execute($ids);
    $previousRows = $stmt->fetchAll();
    if ($previousRows === []) {
        json_response(['ok' => false, 'message' => 'Rate not found.'], 404);
    }

    $delete = $pdo->prepare("DELETE FROM rates WHERE id IN ({$placeholders})");
    $delete->execute($ids);

    $audit = $pdo->prepare(
        'INSERT INTO rate_audit_logs (rate_id, admin_id, action, previous_payload, new_payload, reason)
         VALUES (NULL, ?, ?, ?, NULL, ?)'
    );
    foreach ($previousRows as $previous) {
        $audit->execute([
            (int) $admin['id'],
            'deleted',
            json_encode($previous, JSON_THROW_ON_ERROR),
            'Rate deleted from admin rate management.',
        ]);
    }

    json_response([
        'ok' => true,
        'message' => count($previousRows) === 1 ? 'Rate deleted.' : count($previousRows) . ' rate slots deleted.',
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
    $proceedAvailableOnly = isset($_POST['proceedAvailableOnly']) && $_POST['proceedAvailableOnly'] === '1';
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
    if ($conflicts !== [] && !$proceedAvailableOnly) {
        $conflictSlotIds = array_values(array_unique(array_map('intval', array_column($conflicts, 'timeSlotId'))));
        $availableSlotIds = array_values(array_diff($timeSlotIds, $conflictSlotIds));
        json_response([
            'ok' => false,
            'requiresAvailabilityConfirm' => true,
            'message' => 'Some selected slots already have bookings. Only available/unbooked slots will be blocked if you proceed.',
            'conflicts' => $conflicts,
            'availableSlotCount' => count($availableSlotIds),
            'bookedSlotCount' => count($conflictSlotIds),
        ], 409);
    }
    if ($conflicts !== [] && $proceedAvailableOnly) {
        $conflictSlotIds = array_values(array_unique(array_map('intval', array_column($conflicts, 'timeSlotId'))));
        $timeSlotIds = array_values(array_diff($timeSlotIds, $conflictSlotIds));
        if ($timeSlotIds === []) {
            json_response(['ok' => false, 'message' => 'All selected slots already have bookings. No court blocks were created.'], 422);
        }
    }

    $status = $isActive ? 'Active' : 'Cancelled';
    $cancelledBy = $isActive ? null : (int) $admin['id'];
    $cancelledAt = $isActive ? null : date('Y-m-d H:i:s');

    $savedIds = [];
    $replacedIds = [];
    $deleteBlocksForDateCourt = static function (string $date, ?int $courtId) use ($pdo): array {
        if ($courtId === null) {
            $select = $pdo->prepare('SELECT id FROM court_blocks WHERE block_date = ? AND court_id IS NULL');
            $select->execute([$date]);
            $ids = array_map('intval', array_column($select->fetchAll(), 'id'));
            $delete = $pdo->prepare('DELETE FROM court_blocks WHERE block_date = ? AND court_id IS NULL');
            $delete->execute([$date]);
        } else {
            $select = $pdo->prepare('SELECT id FROM court_blocks WHERE block_date = ? AND court_id = ?');
            $select->execute([$date, $courtId]);
            $ids = array_map('intval', array_column($select->fetchAll(), 'id'));
            $delete = $pdo->prepare('DELETE FROM court_blocks WHERE block_date = ? AND court_id = ?');
            $delete->execute([$date, $courtId]);
        }

        return $ids;
    };
    $insertBlock = $pdo->prepare(
        'INSERT INTO court_blocks
         (block_date, time_slot_id, court_id, sport, reason, notes, status, created_by, cancelled_by, cancelled_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT block_date, court_id FROM court_blocks WHERE id = ?');
            $stmt->execute([$id]);
            $existingBlock = $stmt->fetch();
            if (!$existingBlock) {
                $pdo->rollBack();
                json_response(['ok' => false, 'message' => 'Court block not found.'], 404);
            }

            $oldDate = (string) $existingBlock['block_date'];
            $oldCourtId = $existingBlock['court_id'] !== null ? (int) $existingBlock['court_id'] : null;
            $replacedIds = array_merge($replacedIds, $deleteBlocksForDateCourt($oldDate, $oldCourtId));
            if ($oldDate !== $blockDate || $oldCourtId !== $courtId) {
                $replacedIds = array_merge($replacedIds, $deleteBlocksForDateCourt($blockDate, $courtId));
            }

            foreach ($timeSlotIds as $slotId) {
                $insertBlock->execute([$blockDate, $slotId, $courtId, $sport, $reason, $notes, $status, (int) $admin['id'], $cancelledBy, $cancelledAt]);
                $savedIds[] = (int) $pdo->lastInsertId();
            }
            $message = count($savedIds) > 1
                ? 'Court block range updated.'
                : ($isActive ? 'Court block updated.' : 'Court block cancelled.');
        } else {
            $replacedIds = array_merge($replacedIds, $deleteBlocksForDateCourt($blockDate, $courtId));
            foreach ($timeSlotIds as $slotId) {
                $insertBlock->execute([$blockDate, $slotId, $courtId, $sport, $reason, $notes, $status, (int) $admin['id'], $cancelledBy, $cancelledAt]);
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
                'replacedBlockIds' => array_values(array_unique($replacedIds)),
                'status' => $status,
                'isActive' => $isActive,
                'proceededWithAvailableSlotsOnly' => $proceedAvailableOnly,
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
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_response(['ok' => false, 'message' => 'Could not save court blocking.'], 500);
    }

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

        $seenDateCourts = [];
        foreach ($rows as $row) {
            $dateCourtKey = $row['block_date'] . '|' . ($row['court_id'] ?? 'null');
            if (isset($seenDateCourts[$dateCourtKey])) {
                continue;
            }
            $seenDateCourts[$dateCourtKey] = true;

            if ($row['court_id'] === null) {
                $duplicateStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM court_blocks
                     WHERE block_date = ? AND court_id IS NULL AND id NOT IN ({$placeholders})"
                );
                $duplicateStmt->execute(array_merge([(string) $row['block_date']], $ids));
            } else {
                $duplicateStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM court_blocks
                     WHERE block_date = ? AND court_id = ? AND id NOT IN ({$placeholders})"
                );
                $duplicateStmt->execute(array_merge([(string) $row['block_date'], (int) $row['court_id']], $ids));
            }

            if ((int) $duplicateStmt->fetchColumn() > 0) {
                json_response(['ok' => false, 'message' => 'Another court blocking record already exists for the same date and court. Edit the block details to replace it.'], 409);
            }
        }
    }

    $status = $isActive ? 'Active' : 'Cancelled';
    $params = array_merge([$status], $ids);
    $stmt = $pdo->prepare(
        "UPDATE court_blocks
         SET status = ?
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
    ensure_entrance_fee_activity_columns($pdo);

    $memberId = (int) require_field('memberId');
    $entryType = ($_POST['entryType'] ?? '') === 'op' ? 'op' : 'entrance_fee';
    $amount = 50.00;
    $paymentTimezone = new DateTimeZone('Asia/Manila');
    $paymentNow = new DateTimeImmutable('now', $paymentTimezone);
    $paymentDate = $paymentNow->format('Y-m-d');
    $paymentTime = $paymentNow->format('H:i');
    $playDate = trim((string) ($_POST['playDate'] ?? ($_POST['paymentDate'] ?? '')));
    $playStartTime = trim((string) ($_POST['playStartTime'] ?? ($_POST['paymentTime'] ?? '')));
    $playEndTime = trim((string) ($_POST['playEndTime'] ?? ''));
    $isHourlyTime = static function (string $value, bool $allowEndOfDay = false): bool {
        if ($allowEndOfDay && $value === '24:00') {
            return true;
        }
        return (bool) preg_match('/^(?:[01]\d|2[0-3]):00$/', $value);
    };
    $playedHours = 0.00;
    $bookingId = (int) ($_POST['bookingId'] ?? 0);
    $referenceNumber = trim((string) ($_POST['referenceNumber'] ?? ''));
    $paymentMethod = null;
    if ($entryType === 'entrance_fee') {
        $paymentMethod = require_field('paymentMethod');
        if (strcasecmp($paymentMethod, 'Cash') !== 0) {
            require_active_payment_channel($pdo, $paymentMethod);
        } else {
            $paymentMethod = 'Cash';
        }
    }
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($entryType === 'entrance_fee' && $amount <= 0) {
        json_response(['ok' => false, 'message' => 'Entrance fee amount must be greater than zero.'], 422);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $playDate)) {
        json_response(['ok' => false, 'message' => 'Use a valid date of play.'], 422);
    }
    if ($entryType === 'entrance_fee' && !$isHourlyTime($playStartTime)) {
        json_response(['ok' => false, 'message' => 'Use a valid hourly start time of play.'], 422);
    }

    if (!$isHourlyTime($playStartTime) || !$isHourlyTime($playEndTime, true)) {
        json_response(['ok' => false, 'message' => 'Use a valid hourly time of play range.'], 422);
    }
    $startMinutes = ((int) substr($playStartTime, 0, 2) * 60) + (int) substr($playStartTime, 3, 2);
    $endMinutes = ((int) substr($playEndTime, 0, 2) * 60) + (int) substr($playEndTime, 3, 2);
    if ($endMinutes <= $startMinutes) {
        json_response(['ok' => false, 'message' => 'End time of play must be later than start time.'], 422);
    }
    $playedHours = round(($endMinutes - $startMinutes) / 60, 2);

    $playAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "{$playDate} {$playStartTime}", $paymentTimezone);
    if (!$playAt) {
        json_response(['ok' => false, 'message' => 'Use a valid date and time of play.'], 422);
    }
    $nowMinute = $paymentNow->setTime((int) $paymentNow->format('H'), (int) $paymentNow->format('i'), 0);
    if ($playAt < $nowMinute) {
        json_response(['ok' => false, 'message' => 'Date and time of play cannot be in the past.'], 422);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM members WHERE id = ?');
    $stmt->execute([$memberId]);
    if ((int) $stmt->fetchColumn() === 0) {
        json_response(['ok' => false, 'message' => 'Member not found.'], 404);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO member_entrance_fee_payments
         (member_id, entry_type, amount, payment_date, payment_time, play_date, play_start_time, play_end_time, played_hours, booking_id, reference_number, payment_method, receipt_path, recorded_by, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $memberId,
        $entryType,
        $amount,
        $paymentDate,
        $paymentTime . ':00',
        $playDate,
        $playStartTime . ':00',
        $playEndTime . ':00',
        $playedHours,
        $bookingId > 0 ? $bookingId : null,
        $entryType === 'op' ? '' : $referenceNumber,
        $paymentMethod,
        null,
        (int) $admin['id'],
        $notes,
    ]);

    json_response([
        'ok' => true,
        'message' => $entryType === 'op' ? 'OP hours recorded.' : 'Entrance fee recorded.',
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
