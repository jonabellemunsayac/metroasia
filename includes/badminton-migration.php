<?php
declare(strict_types=1);

// Safe to rerun: existing Badminton settings and rates are retained.
function migrate_badminton(PDO $pdo): void
{
    foreach (['sport_time_slot_availability', 'rates', 'court_blocks', 'court_bookings'] as $table) {
        $query = $pdo->prepare('SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $query->execute([$table, 'sport']);
        $column = $query->fetch();
        if (!$column || str_contains($column['COLUMN_TYPE'], "'Badminton'")) {
            continue;
        }
        $type = substr($column['COLUMN_TYPE'], 0, -1) . ",'Badminton')";
        $nullable = $column['IS_NULLABLE'] === 'YES' ? ' NULL' : ' NOT NULL';
        $default = $column['COLUMN_DEFAULT'] === null ? '' : ' DEFAULT ' . $column['COLUMN_DEFAULT'];
        $pdo->exec("ALTER TABLE `$table` MODIFY sport $type$nullable$default");
    }
    $pdo->beginTransaction();
    try {
        $pdo->exec("INSERT IGNORE INTO courts (id, display_number, name, court_type, surface_label, supported_sports, is_active) VALUES
            (10, 8, 'Wooden Court 8', 'Subdivision of Lakers', 'Wooden', 'Pickleball,Badminton', 1),
            (11, 9, 'Wooden Court 9', 'Subdivision of Lakers', 'Wooden', 'Pickleball,Badminton', 1),
            (12, 10, 'Wooden Court 10', 'Subdivision of Lakers', 'Wooden', 'Pickleball,Badminton', 1)");
        $pdo->exec("UPDATE courts SET supported_sports = 'Pickleball,Badminton' WHERE id IN (7,8,9)");
        $pdo->exec("UPDATE courts SET supported_sports = 'Pickleball,Badminton', court_type = 'Subdivision of Lakers' WHERE id IN (10,11,12)");
        $pdo->exec("INSERT IGNORE INTO sport_time_slot_availability (sport, time_slot_id, is_available) SELECT 'Badminton', time_slot_id, is_available FROM sport_time_slot_availability WHERE sport = 'Pickleball'");
        // Copy each court's existing rates to its newly supported sport without overwriting rates.
        foreach (['Pickleball' => 'Badminton', 'Badminton' => 'Pickleball'] as $sourceSport => $targetSport) {
            $copy = $pdo->prepare("INSERT IGNORE INTO rates (court_id, sport, day_of_week, time_slot_id, rate_per_hour, effective_date) SELECT court_id, ?, day_of_week, time_slot_id, rate_per_hour, effective_date FROM rates WHERE sport = ? AND court_id IN (7,8,9,10,11,12)");
            $copy->execute([$targetSport, $sourceSport]);
            $fallback = $pdo->prepare("INSERT IGNORE INTO rates (court_id, sport, day_of_week, time_slot_id, rate_per_hour) SELECT c.id, ?, 'Any', ts.id, ts.price FROM courts c CROSS JOIN time_slots ts WHERE c.id IN (7,8,9,10,11,12)");
            $fallback->execute([$targetSport]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}
