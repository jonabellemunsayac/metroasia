<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/site-config.php';
require __DIR__ . '/../includes/data-privacy.php';
require __DIR__ . '/../includes/open-play-rates.php';
$source = file_get_contents(__DIR__ . '/../api.php');
$start = strpos($source, 'function json_response');
eval(substr($source, $start, strpos($source, '$action = $_GET') - $start));
$pdo = db();
$expected = $pdo->query("SELECT m.id,
    (SELECT COUNT(*) FROM court_bookings cb WHERE cb.member_id=m.id) AS total,
    (SELECT COUNT(*) FROM court_bookings cb WHERE cb.member_id=m.id AND cb.status='Booked') AS confirmed,
    (SELECT COALESCE(SUM(ef.amount),0) FROM member_entrance_fee_payments ef WHERE ef.member_id=m.id) AS fees
    FROM members m ORDER BY m.created_at DESC,m.id DESC")->fetchAll();
$actual = admin_members($pdo);
if (count($expected) !== count($actual)) throw new RuntimeException('Member count changed');
foreach ($expected as $index => $row) {
    $member = $actual[$index];
    if ((int) $row['id'] !== $member['id']
        || (int) $row['total'] !== $member['courtBookingsCount']
        || (int) $row['confirmed'] !== $member['confirmedCount']
        || (float) $row['fees'] !== $member['entranceFeeTotal']) {
        throw new RuntimeException('Member order or totals changed');
    }
}
echo 'Grouped totals match previous query for all ' . count($actual) . " members.\n";
