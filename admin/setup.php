<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

$messages = [];
$error = null;

function run_setup(): array
{
    $pdo = db(false);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');

    $schema = file_get_contents(dirname(__DIR__) . '/database.sql');
    foreach (array_filter(array_map('trim', explode(';', (string) $schema))) as $statement) {
        if ($statement !== '' && !str_starts_with(strtoupper($statement), 'CREATE DATABASE') && !str_starts_with(strtoupper($statement), 'USE ')) {
            $pdo->exec($statement);
        }
    }

    seed_database($pdo);

    return [
        'Database created or updated.',
        'Seeded courts, rates, rate rules, time slots, open play sessions, and default admin if missing.',
        'Default admin: admin@cpg.test / admin123',
    ];
}

function seed_database(PDO $pdo): void
{
    migrate_columns($pdo);
    seed_courts($pdo);
    seed_payment_channels($pdo);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM rates')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO rates (label, price, display_time, sort_order) VALUES (?, ?, ?, ?)');
        $stmt->execute(['Morning', 265, '9 AM - 3 PM', 1]);
        $stmt->execute(['Prime', 315, '3 PM - 5 PM', 2]);
        $stmt->execute(['Night', 365, '5 PM - 12 AM', 3]);
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM time_slots')->fetchColumn();
    if ($count === 0) {
        $slots = [
            ['Morning', '09:00 AM - 10:00 AM', '09:00:00', '10:00:00', 265],
            ['Morning', '10:00 AM - 11:00 AM', '10:00:00', '11:00:00', 265],
            ['Morning', '11:00 AM - 12:00 PM', '11:00:00', '12:00:00', 265],
            ['Afternoon', '12:00 PM - 01:00 PM', '12:00:00', '13:00:00', 265],
            ['Afternoon', '01:00 PM - 02:00 PM', '13:00:00', '14:00:00', 265],
            ['Afternoon', '02:00 PM - 03:00 PM', '14:00:00', '15:00:00', 265],
            ['Afternoon', '03:00 PM - 04:00 PM', '15:00:00', '16:00:00', 315],
            ['Evening', '04:00 PM - 05:00 PM', '16:00:00', '17:00:00', 315],
            ['Evening', '05:00 PM - 06:00 PM', '17:00:00', '18:00:00', 365],
            ['Evening', '06:00 PM - 07:00 PM', '18:00:00', '19:00:00', 365],
            ['Evening', '07:00 PM - 08:00 PM', '19:00:00', '20:00:00', 365],
        ];
        $stmt = $pdo->prepare('INSERT INTO time_slots (period, label, starts_at, ends_at, price, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($slots as $index => $slot) {
            $stmt->execute([$slot[0], $slot[1], $slot[2], $slot[3], $slot[4], $index + 1]);
        }
    }

    seed_rate_rules($pdo);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM open_play_sessions')->fetchColumn();
    if ($count === 0) {
        $today = new DateTimeImmutable('today');
        $date = $today->format('Y-m-d');
        $tomorrow = $today->modify('+1 day')->format('Y-m-d');
        $stmt = $pdo->prepare('INSERT INTO open_play_sessions (title, session_date, session_time, price, capacity, level_label, description) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(['Beginner Social Rally', $date, '08:00 AM - 10:00 AM', 220, 24, 'Beginner friendly', 'Casual rotating doubles with hosts helping first-time players settle in.']);
        $stmt->execute(['Competitive Open Play', $date, '06:00 PM - 08:00 PM', 280, 20, 'Intermediate', 'Fast-paced games for players who already know scoring and court rotation.']);
        $stmt->execute(['Weekend Mixed Ladder', $tomorrow, '04:00 PM - 07:00 PM', 320, 18, 'All levels', 'Structured ladder format with friendly pairings and court captains.']);
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM court_bookings')->fetchColumn();
    if ($count === 0) {
        $today = new DateTimeImmutable('today');
        $date = $today->format('Y-m-d');
        $tomorrow = $today->modify('+1 day')->format('Y-m-d');
        $slot = $pdo->prepare('SELECT id FROM time_slots WHERE label = ?');
        $insert = $pdo->prepare(
            'INSERT INTO court_bookings
             (booking_date, time_slot_id, court_id, sport, status, customer_name, customer_email, customer_phone, payment_method, base_rate, final_amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ([
            [$date, '09:00 AM - 10:00 AM', 1, 'Pickleball', 'Confirmed', 'Marco Santos', 'marco@example.com', '09170000001', 'GCash'],
            [$date, '09:00 AM - 10:00 AM', 2, 'Basketball', 'Confirmed', 'Nina Cruz', 'nina@example.com', '09170000002', 'BDO'],
            [$date, '10:00 AM - 11:00 AM', 5, 'Pickleball', 'Under Review', 'Jay Lim', 'jay@example.com', '09170000003', 'GCash'],
            [$date, '02:00 PM - 03:00 PM', 6, 'Pickleball', 'Confirmed', 'Alex Tan', 'alex@example.com', '09170000004', 'GCash'],
            [$tomorrow, '05:00 PM - 06:00 PM', 7, 'Pickleball', 'Under Review', 'Rhea Park', 'rhea@example.com', '09170000005', 'BDO'],
        ] as $booking) {
            $slot->execute([$booking[1]]);
            $slotId = (int) $slot->fetchColumn();
            if ($slotId === 0 || !court_exists($pdo, (int) $booking[2])) {
                continue;
            }
            $price = (float) $pdo->query('SELECT price FROM time_slots WHERE id = ' . $slotId)->fetchColumn();
            $insert->execute([$booking[0], $slotId, $booking[2], $booking[3], $booking[4], $booking[5], $booking[6], $booking[7], $booking[8], $price, $price]);
        }
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE email = ?');
    $stmt->execute(['admin@cpg.test']);
    if ((int) $stmt->fetchColumn() === 0) {
        $stmt = $pdo->prepare('INSERT INTO admin_users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute(['Metro Asia Admin', 'admin@cpg.test', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
    }
}

function migrate_columns(PDO $pdo): void
{
    if (!table_exists($pdo, 'members')) {
        $pdo->exec(
            "CREATE TABLE members (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(160) NOT NULL,
                email VARCHAR(190) NOT NULL UNIQUE,
                phone VARCHAR(60) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_login_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
    if (!column_exists($pdo, 'courts', 'display_number')) {
        $pdo->exec('ALTER TABLE courts ADD display_number INT UNSIGNED NULL AFTER id');
        $pdo->exec('UPDATE courts SET display_number = id');
        $pdo->exec('ALTER TABLE courts MODIFY display_number INT UNSIGNED NOT NULL');
    }
    if (!column_exists($pdo, 'courts', 'surface_label')) {
        $pdo->exec('ALTER TABLE courts ADD surface_label VARCHAR(80) NULL AFTER court_type');
    }
    if (!column_exists($pdo, 'courts', 'supported_sports')) {
        $pdo->exec("ALTER TABLE courts ADD supported_sports VARCHAR(160) NOT NULL DEFAULT 'Pickleball' AFTER surface_label");
    }
    if (!table_exists($pdo, 'rate_rules')) {
        $pdo->exec(
            "CREATE TABLE rate_rules (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(140) NOT NULL,
                court_id INT UNSIGNED NULL,
                sport ENUM('Pickleball','Basketball','Volleyball') NULL,
                day_type ENUM('Any','Weekday','Weekend') NOT NULL DEFAULT 'Any',
                day_pattern VARCHAR(80) NOT NULL DEFAULT 'Any',
                starts_at TIME NOT NULL,
                ends_at TIME NOT NULL,
                duration_minutes INT UNSIGNED NULL,
                price_per_hour DECIMAL(10,2) NOT NULL,
                member_price_per_hour DECIMAL(10,2) NULL,
                effective_from DATE NULL,
                effective_to DATE NULL,
                priority INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL,
                updated_by INT UNSIGNED NULL,
                change_reason VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_rate_lookup (court_id, sport, day_type, starts_at, ends_at, is_active),
                INDEX idx_rate_pattern_lookup (court_id, sport, day_pattern, starts_at, ends_at, duration_minutes, is_active),
                CONSTRAINT fk_rate_rule_court FOREIGN KEY (court_id) REFERENCES courts(id),
                CONSTRAINT fk_rate_rule_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id),
                CONSTRAINT fk_rate_rule_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
    if (!column_exists($pdo, 'rate_rules', 'day_pattern')) {
        $pdo->exec("ALTER TABLE rate_rules ADD day_pattern VARCHAR(80) NOT NULL DEFAULT 'Any' AFTER day_type");
        $pdo->exec("UPDATE rate_rules SET day_pattern = day_type WHERE day_pattern = 'Any'");
    }
    if (!column_exists($pdo, 'rate_rules', 'duration_minutes')) {
        $pdo->exec('ALTER TABLE rate_rules ADD duration_minutes INT UNSIGNED NULL AFTER ends_at');
        $pdo->exec('UPDATE rate_rules SET duration_minutes = 60 WHERE duration_minutes IS NULL');
    }
    if (column_exists($pdo, 'rate_rules', 'day_pattern')) {
        $pdo->exec("UPDATE rate_rules SET day_pattern = 'Monday-Friday' WHERE day_pattern = 'Weekday'");
        $pdo->exec("UPDATE rate_rules SET day_pattern = 'Saturday-Sunday' WHERE day_pattern = 'Weekend'");
        $pdo->exec("UPDATE rate_rules SET name = 'Miami Basketball Saturday', day_pattern = 'Saturday' WHERE name = 'Miami Basketball Weekend'");
    }
    if (!table_exists($pdo, 'rate_audit_logs')) {
        $pdo->exec(
            "CREATE TABLE rate_audit_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                rate_rule_id INT UNSIGNED NULL,
                admin_id INT UNSIGNED NULL,
                action VARCHAR(40) NOT NULL,
                previous_payload JSON NULL,
                new_payload JSON NULL,
                reason VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_rate_audit_rule (rate_rule_id, created_at),
                CONSTRAINT fk_rate_audit_rule FOREIGN KEY (rate_rule_id) REFERENCES rate_rules(id),
                CONSTRAINT fk_rate_audit_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
    if (!column_exists($pdo, 'court_bookings', 'sport')) {
        $pdo->exec("ALTER TABLE court_bookings ADD sport ENUM('Pickleball','Basketball','Volleyball') NOT NULL DEFAULT 'Pickleball' AFTER court_id");
    }
    if (!column_exists($pdo, 'court_bookings', 'member_id')) {
        $pdo->exec('ALTER TABLE court_bookings ADD member_id INT UNSIGNED NULL AFTER id');
        $pdo->exec('ALTER TABLE court_bookings ADD INDEX idx_court_member (member_id, booking_date)');
        $pdo->exec('ALTER TABLE court_bookings ADD CONSTRAINT fk_court_booking_member FOREIGN KEY (member_id) REFERENCES members(id)');
    }
    if (column_exists($pdo, 'court_bookings', 'status')) {
        $pdo->exec("UPDATE court_bookings SET status = 'Confirmed' WHERE status = 'Booked'");
        $pdo->exec("UPDATE court_bookings SET status = 'Under Review' WHERE status = 'Pending' AND receipt_path IS NOT NULL");
        $pdo->exec("UPDATE court_bookings SET status = 'Payment Pending' WHERE status = 'Pending'");
        $pdo->exec("ALTER TABLE court_bookings MODIFY status ENUM('Held','Payment Pending','Payment Submitted','Under Review','Confirmed','Cancelled','Rejected','Expired','Completed','No Show') NOT NULL DEFAULT 'Payment Pending'");
    }
    if (!column_exists($pdo, 'court_bookings', 'base_rate')) {
        $pdo->exec('ALTER TABLE court_bookings ADD base_rate DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER receipt_path');
    }
    if (!column_exists($pdo, 'court_bookings', 'final_amount')) {
        $pdo->exec('ALTER TABLE court_bookings ADD final_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER base_rate');
    }
    if (!column_exists($pdo, 'court_bookings', 'rate_snapshot')) {
        $pdo->exec('ALTER TABLE court_bookings ADD rate_snapshot JSON NULL AFTER final_amount');
    }
    $pdo->exec(
        'UPDATE court_bookings cb
         JOIN time_slots ts ON ts.id = cb.time_slot_id
         SET cb.base_rate = ts.price, cb.final_amount = ts.price
         WHERE cb.final_amount = 0'
    );
    if (!column_exists($pdo, 'open_play_reservations', 'member_id')) {
        $pdo->exec('ALTER TABLE open_play_reservations ADD member_id INT UNSIGNED NULL AFTER id');
        $pdo->exec('ALTER TABLE open_play_reservations ADD INDEX idx_open_play_member (member_id)');
        $pdo->exec('ALTER TABLE open_play_reservations ADD CONSTRAINT fk_open_play_reservation_member FOREIGN KEY (member_id) REFERENCES members(id)');
    }
    if (column_exists($pdo, 'open_play_reservations', 'status')) {
        $pdo->exec("UPDATE open_play_reservations SET status = 'Confirmed' WHERE status = 'Booked'");
        $pdo->exec("UPDATE open_play_reservations SET status = 'Under Review' WHERE status = 'Pending' AND receipt_path IS NOT NULL");
        $pdo->exec("UPDATE open_play_reservations SET status = 'Payment Pending' WHERE status = 'Pending'");
        $pdo->exec("ALTER TABLE open_play_reservations MODIFY status ENUM('Held','Payment Pending','Payment Submitted','Under Review','Confirmed','Cancelled','Rejected','Expired','Completed','No Show') NOT NULL DEFAULT 'Payment Pending'");
    }
    if (!column_exists($pdo, 'open_play_reservations', 'final_amount')) {
        $pdo->exec('ALTER TABLE open_play_reservations ADD final_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER receipt_path');
    }
    if (!column_exists($pdo, 'open_play_reservations', 'rate_snapshot')) {
        $pdo->exec('ALTER TABLE open_play_reservations ADD rate_snapshot JSON NULL AFTER final_amount');
    }
    $pdo->exec(
        'UPDATE open_play_reservations opr
         JOIN open_play_sessions ops ON ops.id = opr.session_id
         SET opr.final_amount = ops.price
         WHERE opr.final_amount = 0'
    );
    if (!table_exists($pdo, 'payment_channels')) {
        $pdo->exec(
            "CREATE TABLE payment_channels (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(80) NOT NULL UNIQUE,
                name VARCHAR(120) NOT NULL,
                channel_type ENUM('qr','bank') NOT NULL DEFAULT 'bank',
                account_name VARCHAR(160) NULL,
                account_number VARCHAR(120) NULL,
                bank_name VARCHAR(160) NULL,
                instructions TEXT NULL,
                qr_path VARCHAR(255) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
    if (!table_exists($pdo, 'court_blocks')) {
        $pdo->exec(
            "CREATE TABLE court_blocks (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                block_date DATE NOT NULL,
                time_slot_id INT UNSIGNED NOT NULL,
                court_id INT UNSIGNED NULL,
                sport ENUM('Pickleball','Basketball','Volleyball') NULL,
                reason VARCHAR(80) NOT NULL,
                notes VARCHAR(255) NULL,
                status ENUM('Active','Cancelled') NOT NULL DEFAULT 'Active',
                created_by INT UNSIGNED NULL,
                cancelled_by INT UNSIGNED NULL,
                cancelled_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_court_blocks_lookup (block_date, time_slot_id, court_id, sport, status),
                CONSTRAINT fk_court_block_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id),
                CONSTRAINT fk_court_block_court FOREIGN KEY (court_id) REFERENCES courts(id),
                CONSTRAINT fk_court_block_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id),
                CONSTRAINT fk_court_block_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES admin_users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
    if (!table_exists($pdo, 'override_logs')) {
        $pdo->exec(
            "CREATE TABLE override_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                admin_id INT UNSIGNED NULL,
                action VARCHAR(80) NOT NULL,
                target_type VARCHAR(40) NOT NULL,
                target_id VARCHAR(80) NULL,
                conflict_summary TEXT NULL,
                payload JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_override_logs_created (created_at),
                CONSTRAINT fk_override_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
    if (column_exists($pdo, 'court_bookings', 'payment_method')) {
        $pdo->exec('ALTER TABLE court_bookings MODIFY payment_method VARCHAR(80) NOT NULL');
    }
    if (column_exists($pdo, 'open_play_reservations', 'payment_method')) {
        $pdo->exec('ALTER TABLE open_play_reservations MODIFY payment_method VARCHAR(80) NOT NULL');
    }
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $stmt->execute([DB_NAME, $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([DB_NAME, $table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function court_exists(PDO $pdo, int $courtId): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM courts WHERE id = ?');
    $stmt->execute([$courtId]);
    return (int) $stmt->fetchColumn() > 0;
}

function seed_courts(PDO $pdo): void
{
    $definitions = [
        1 => ['Lakers', 'Full-size multi-sport court', 'USAPA', 'Basketball,Volleyball,Pickleball'],
        2 => ['Miami', 'Full-size multi-sport court', 'USAPA', 'Basketball,Volleyball,Pickleball'],
        5 => ['Wooden Court 5', 'Subdivision of Miami', 'Wooden', 'Pickleball'],
        6 => ['Wooden Court 6', 'Subdivision of Miami', 'Wooden', 'Pickleball'],
        7 => ['Wooden Court 7', 'Subdivision of Miami', 'Wooden', 'Pickleball'],
    ];

    $update = $pdo->prepare(
        'UPDATE courts
         SET display_number = ?, name = ?, court_type = ?, surface_label = ?, supported_sports = ?, is_active = 1
         WHERE id = ?'
    );
    $insert = $pdo->prepare(
        'INSERT INTO courts (id, display_number, name, court_type, surface_label, supported_sports, is_active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    );

    foreach ($definitions as $id => $court) {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM courts WHERE id = ?');
        $exists->execute([$id]);
        if ((int) $exists->fetchColumn() > 0) {
            $update->execute([$id, $court[0], $court[1], $court[2], $court[3], $id]);
        } else {
            $insert->execute([$id, $id, $court[0], $court[1], $court[2], $court[3]]);
        }
    }

    $pdo->exec('UPDATE courts SET is_active = 0 WHERE id IN (3, 4)');
}

function seed_rate_rules(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM rate_rules')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $rules = [
        ['Lakers Basketball Weekday Day', 1, 'Basketball', 'Weekday', 'Monday-Friday', '08:00:00', '17:00:00', 60, 800, null, 50, 'PRD sample weekday day rate.'],
        ['Lakers Basketball Weekday Night', 1, 'Basketball', 'Weekday', 'Monday-Friday', '17:00:00', '22:00:00', 60, 1000, null, 60, 'PRD sample weekday night rate.'],
        ['Lakers Volleyball Weekday Night', 1, 'Volleyball', 'Weekday', 'Monday-Friday', '17:00:00', '22:00:00', 60, 1000, null, 60, 'PRD sample volleyball rate.'],
        ['Miami Basketball Saturday', 2, 'Basketball', 'Weekend', 'Saturday', '09:00:00', '22:00:00', 60, 1200, null, 70, 'PRD sample Miami Saturday rate.'],
        ['Wooden 5 Pickleball Weekday Day', 5, 'Pickleball', 'Weekday', 'Monday-Friday', '08:00:00', '17:00:00', 60, 400, null, 60, 'PRD sample Wooden 5 day rate.'],
        ['Wooden 5 Pickleball Weekday Night', 5, 'Pickleball', 'Weekday', 'Monday-Friday', '17:00:00', '22:00:00', 60, 600, null, 70, 'PRD sample Wooden 5 night rate.'],
        ['Pickleball Standard Morning', null, 'Pickleball', 'Any', 'Any', '09:00:00', '15:00:00', 60, 265, 250, 10, 'Default public pickleball morning rate.'],
        ['Pickleball Standard Prime', null, 'Pickleball', 'Any', 'Any', '15:00:00', '17:00:00', 60, 315, 300, 10, 'Default public pickleball prime rate.'],
        ['Pickleball Standard Night', null, 'Pickleball', 'Any', 'Any', '17:00:00', '23:59:59', 60, 365, 350, 10, 'Default public pickleball night rate.'],
        ['Basketball Standard', null, 'Basketball', 'Any', 'Any', '09:00:00', '23:59:59', 60, 1000, 950, 5, 'Default basketball fallback.'],
        ['Volleyball Standard', null, 'Volleyball', 'Any', 'Any', '09:00:00', '23:59:59', 60, 1000, 950, 5, 'Default volleyball fallback.'],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO rate_rules
         (name, court_id, sport, day_type, day_pattern, starts_at, ends_at, duration_minutes, price_per_hour, member_price_per_hour, priority, change_reason)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($rules as $rule) {
        $stmt->execute($rule);
    }
}

function seed_payment_channels(PDO $pdo): void
{
    $pdo->exec("UPDATE payment_channels SET account_name = 'Metro Asia' WHERE account_name = 'City Pickle Grounds'");
    $pdo->exec("UPDATE payment_channels SET name = 'BDO Online' WHERE code = 'BDO' AND name = 'BDO'");
    $pdo->exec("UPDATE payment_channels SET qr_path = 'assets/bdo-pay-qr.svg' WHERE code = 'BDO' AND (qr_path IS NULL OR qr_path = '')");
    $pdo->exec("UPDATE payment_channels SET is_active = 0 WHERE code NOT IN ('GCash', 'BDO')");
    $pdo->exec("UPDATE payment_channels SET is_active = 1 WHERE code IN ('GCash', 'BDO')");

    $definitions = [
        ['GCash', 'GCash', 'qr', 'Metro Asia', '09XX XXX XXXX', null, 'Scan the QR code, complete the transfer, then upload your receipt screenshot.', 'assets/gcash-qr.svg', 1],
        ['BDO', 'BDO Online', 'bank', 'Metro Asia', '0000-0000-0000', 'BDO Unibank', 'Use your reservation name as the transfer note, then upload the deposit or transfer receipt.', 'assets/bdo-pay-qr.svg', 2],
    ];

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM payment_channels WHERE code = ?');
    $insert = $pdo->prepare(
        'INSERT INTO payment_channels
         (code, name, channel_type, account_name, account_number, bank_name, instructions, qr_path, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($definitions as $channel) {
        $stmt->execute([$channel[0]]);
        if ((int) $stmt->fetchColumn() === 0) {
            $insert->execute($channel);
        }
    }
}

try {
    $messages = run_setup();
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database Setup | Multi-Sport Court Scheduling & Reservation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/themes/metro/bootstrap-redesign.css">
</head>
<body class="bg-slate-100 p-4 p-md-5 font-sans text-slate-900">
    <main class="mx-auto max-w-2xl rounded-xl bg-white p-4 p-md-5 shadow">
        <h1 class="text-3xl font-black mb-0">Database Setup</h1>
        <?php if ($error): ?>
            <div class="mt-5 rounded-lg bg-rose-50 p-4 font-bold text-rose-700"><?php echo htmlspecialchars($error); ?></div>
            <p class="mt-4 text-slate-600">Check that MySQL is running in XAMPP and that the credentials in <code>../config/database.php</code> match your local server.</p>
        <?php else: ?>
            <div class="mt-5 grid gap-2">
                <?php foreach ($messages as $message): ?>
                    <p class="rounded-lg bg-emerald-50 p-3 font-bold text-emerald-700"><?php echo htmlspecialchars($message); ?></p>
                <?php endforeach; ?>
            </div>
            <div class="mt-5 flex gap-3">
                <a class="rounded-full bg-slate-900 px-5 py-3 font-black text-white" href="login.php">Sign In</a>
                <a class="rounded-full bg-lime-300 px-5 py-3 font-black text-slate-900" href="../ui/booking.php">Booking Page</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
