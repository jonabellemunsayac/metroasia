<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/site-config.php';

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
        'Seeded courts, rates, time slots, and default admin if missing.',
        'Default admin: admin@cpg.test / admin123',
    ];
}

function seed_database(PDO $pdo): void
{
    migrate_columns($pdo);
    seed_courts($pdo);
    seed_payment_channels($pdo);
    seed_site_config($pdo);
    seed_time_slots($pdo);
    seed_rates($pdo);

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
            [$date, '09:00 AM - 10:00 AM', 3, 'Pickleball', 'Booked', 'Marco Santos', 'marco@example.com', '09170000001', 'GCash'],
            [$date, '09:00 AM - 10:00 AM', 2, 'Basketball', 'Booked', 'Nina Cruz', 'nina@example.com', '09170000002', 'BDO'],
            [$date, '10:00 AM - 11:00 AM', 5, 'Pickleball', 'Held', 'Jay Lim', 'jay@example.com', '09170000003', 'GCash'],
            [$date, '02:00 PM - 03:00 PM', 6, 'Pickleball', 'Booked', 'Alex Tan', 'alex@example.com', '09170000004', 'GCash'],
            [$tomorrow, '05:00 PM - 06:00 PM', 7, 'Pickleball', 'Held', 'Rhea Park', 'rhea@example.com', '09170000005', 'BDO'],
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
    reset_rate_tables($pdo);
    if (!column_exists($pdo, 'court_bookings', 'sport')) {
        $pdo->exec("ALTER TABLE court_bookings ADD sport ENUM('Pickleball','Basketball','Volleyball') NOT NULL DEFAULT 'Pickleball' AFTER court_id");
    }
    if (!column_exists($pdo, 'court_bookings', 'member_id')) {
        $pdo->exec('ALTER TABLE court_bookings ADD member_id INT UNSIGNED NULL AFTER id');
        $pdo->exec('ALTER TABLE court_bookings ADD INDEX idx_court_member (member_id, booking_date)');
        $pdo->exec('ALTER TABLE court_bookings ADD CONSTRAINT fk_court_booking_member FOREIGN KEY (member_id) REFERENCES members(id)');
    }
    if (!column_exists($pdo, 'court_bookings', 'booking_reference')) {
        $pdo->exec('ALTER TABLE court_bookings ADD booking_reference VARCHAR(40) NULL UNIQUE AFTER id');
    }
    if (column_exists($pdo, 'court_bookings', 'status')) {
        migrate_reservation_statuses($pdo, 'court_bookings');
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
        migrate_reservation_statuses($pdo, 'open_play_reservations');
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
    if (!table_exists($pdo, 'site_config')) {
        $pdo->exec(
            "CREATE TABLE site_config (
                config_key VARCHAR(80) PRIMARY KEY,
                config_value TEXT NULL,
                label VARCHAR(120) NOT NULL,
                field_type ENUM('text','textarea','url','image') NOT NULL DEFAULT 'text',
                sort_order INT NOT NULL DEFAULT 0,
                updated_by INT UNSIGNED NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_site_config_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id)
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

function migrate_reservation_statuses(PDO $pdo, string $table): void
{
    $pdo->exec(
        "ALTER TABLE {$table}
         MODIFY status ENUM('Available','Pending Payment','Held','Booked','Payment Pending','Payment Submitted','Under Review','Confirmed','Cancelled','Rejected','Expired','Completed','No Show','Pending')
         NOT NULL DEFAULT 'Pending Payment'"
    );
    $pdo->exec("UPDATE {$table} SET status = 'Booked' WHERE status IN ('Confirmed','Completed')");
    $pdo->exec("UPDATE {$table} SET status = 'Held' WHERE status IN ('Held','Payment Submitted','Under Review')");
    $pdo->exec("UPDATE {$table} SET status = 'Pending Payment' WHERE status IN ('Payment Pending','Pending')");
    $pdo->exec("UPDATE {$table} SET status = 'Available' WHERE status IN ('Cancelled','Rejected','Expired','No Show')");
    $pdo->exec(
        "ALTER TABLE {$table}
         MODIFY status ENUM('Available','Pending Payment','Held','Booked')
         NOT NULL DEFAULT 'Pending Payment'"
    );
}

function reset_rate_tables(PDO $pdo): void
{
    $needsReset = table_exists($pdo, 'rate_rules')
        || !table_exists($pdo, 'rates')
        || !column_exists($pdo, 'rates', 'time_slot_id')
        || !column_exists($pdo, 'rates', 'rate_per_hour')
        || (table_exists($pdo, 'rate_audit_logs') && !column_exists($pdo, 'rate_audit_logs', 'rate_id'));

    if ($needsReset) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('DROP TABLE IF EXISTS rate_audit_logs');
        $pdo->exec('DROP TABLE IF EXISTS rate_rules');
        $pdo->exec('DROP TABLE IF EXISTS rates');
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    if (!table_exists($pdo, 'rates')) {
        $pdo->exec(
            "CREATE TABLE rates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                court_id INT UNSIGNED NOT NULL,
                sport ENUM('Pickleball','Basketball','Volleyball') NOT NULL,
                time_slot_id INT UNSIGNED NOT NULL,
                rate_per_hour DECIMAL(10,2) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_rate_lookup (court_id, sport, time_slot_id),
                INDEX idx_rates_lookup (court_id, sport, time_slot_id),
                CONSTRAINT fk_rate_court FOREIGN KEY (court_id) REFERENCES courts(id),
                CONSTRAINT fk_rate_time_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
    if (column_exists($pdo, 'rates', 'senior_discount')) {
        $pdo->exec('ALTER TABLE rates DROP COLUMN senior_discount');
    }
    if (column_exists($pdo, 'rates', 'student_discount')) {
        $pdo->exec('ALTER TABLE rates DROP COLUMN student_discount');
    }

    if (!table_exists($pdo, 'rate_audit_logs')) {
        $pdo->exec(
            "CREATE TABLE rate_audit_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                rate_id INT UNSIGNED NULL,
                admin_id INT UNSIGNED NULL,
                action VARCHAR(40) NOT NULL,
                previous_payload JSON NULL,
                new_payload JSON NULL,
                reason VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_rate_audit_rate (rate_id, created_at),
                CONSTRAINT fk_rate_audit_rate FOREIGN KEY (rate_id) REFERENCES rates(id) ON DELETE SET NULL,
                CONSTRAINT fk_rate_audit_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
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
        1 => [1, 'Lakers', 'Full-size multi-sport court', 'Multi-sport', 'Basketball,Volleyball'],
        2 => [2, 'Miami', 'Full-size multi-sport court', 'Multi-sport', 'Basketball,Volleyball,Pickleball'],
        3 => [1, 'Pickleball Pro Court 1', 'Dedicated pickleball court', 'Pickleball', 'Pickleball'],
        4 => [2, 'Pickleball Pro Court 2', 'Dedicated pickleball court', 'Pickleball', 'Pickleball'],
        5 => [3, 'Pickleball Pro Court 3', 'Dedicated pickleball court', 'Pickleball', 'Pickleball'],
        6 => [4, 'Pickleball Pro Court 4', 'Dedicated pickleball court', 'Pickleball', 'Pickleball'],
        7 => [5, 'Wooden Court 5', 'Subdivision of Miami', 'Wooden', 'Pickleball'],
        8 => [6, 'Wooden Court 6', 'Subdivision of Miami', 'Wooden', 'Pickleball'],
        9 => [7, 'Wooden Court 7', 'Subdivision of Miami', 'Wooden', 'Pickleball'],
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
            $update->execute([$court[0], $court[1], $court[2], $court[3], $court[4], $id]);
        } else {
            $insert->execute([$id, $court[0], $court[1], $court[2], $court[3], $court[4]]);
        }
    }

    $pdo->exec('UPDATE courts SET is_active = 0 WHERE id NOT IN (1, 2, 3, 4, 5, 6, 7, 8, 9)');
}

function seed_time_slots(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM time_slots')->fetchColumn();
    if ($count > 0) {
        return;
    }

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

function seed_rates(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM rates')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $courts = $pdo->query('SELECT id, supported_sports FROM courts WHERE is_active = 1 ORDER BY display_number, id')->fetchAll();
    $slots = $pdo->query('SELECT id, price FROM time_slots ORDER BY sort_order, id')->fetchAll();
    $insert = $pdo->prepare(
        'INSERT INTO rates (court_id, sport, time_slot_id, rate_per_hour)
         VALUES (?, ?, ?, ?)'
    );

    foreach ($courts as $court) {
        $sports = array_filter(array_map('trim', explode(',', (string) $court['supported_sports'])));
        foreach ($sports as $sport) {
            if ((int) $court['id'] === 2 && $sport === 'Pickleball') {
                continue;
            }
            foreach ($slots as $slot) {
                $insert->execute([(int) $court['id'], $sport, (int) $slot['id'], (float) $slot['price']]);
            }
        }
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

function seed_site_config(PDO $pdo): void
{
    $labels = [
        'venue_name' => ['Venue Name', 'text', 10],
        'address' => ['Address', 'text', 20],
        'contact_phone' => ['Contact Phone', 'text', 30],
        'contact_email' => ['Contact Email', 'text', 40],
        'messenger_url' => ['Facebook Messenger Link', 'url', 50],
        'map_embed_url' => ['Contact Map Embed Link', 'url', 60],
        'hero_image_path' => ['Home Hero Image Path', 'image', 70],
        'contact_image_path' => ['Contact Image Path', 'image', 80],
        'gallery_1_title' => ['Gallery 1 Title', 'text', 110],
        'gallery_1_caption' => ['Gallery 1 Caption', 'textarea', 120],
        'gallery_1_image' => ['Gallery 1 Image Path', 'image', 130],
        'gallery_2_title' => ['Gallery 2 Title', 'text', 140],
        'gallery_2_caption' => ['Gallery 2 Caption', 'textarea', 150],
        'gallery_2_image' => ['Gallery 2 Image Path', 'image', 160],
        'gallery_3_title' => ['Gallery 3 Title', 'text', 170],
        'gallery_3_caption' => ['Gallery 3 Caption', 'textarea', 180],
        'gallery_3_image' => ['Gallery 3 Image Path', 'image', 190],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO site_config (config_key, config_value, label, field_type, sort_order)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE label = VALUES(label), field_type = VALUES(field_type), sort_order = VALUES(sort_order)'
    );

    foreach (site_config_defaults() as $key => $value) {
        [$label, $type, $sort] = $labels[$key] ?? [$key, 'text', 999];
        $stmt->execute([$key, $value, $label, $type, $sort]);
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
