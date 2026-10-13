<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$source = file_get_contents(__DIR__ . '/../ui/member.php');
$start = strpos($source, 'function member_minutes_from_time');
$end = strpos($source, "if (\$_SERVER['REQUEST_METHOD']", $start);
eval(substr($source, $start, $end - $start));
function verify_reference_group(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$row = ['id'=>1,'session_id'=>10,'booking_reference'=>'OP1012-9-12-01',
    'date'=>'2026-10-12','starts_at'=>'09:00:00','ends_at'=>'12:00:00',
    'session_time'=>'9:00 AM - 12:00 PM','court'=>7,'court_name'=>'Wooden Court 5',
    'sport'=>'Pickleball','status'=>'Booked','payment_method'=>'GCash',
    'receipt_path'=>'uploads/first.jpg','final_amount'=>150,'created_at'=>'2026-10-11 09:00:00','seat_number'=>1];
$groups = member_group_open_play_reservations([
    $row,
    array_replace($row,['id'=>2,'seat_number'=>2,'status'=>'Held','receipt_path'=>'uploads/second.jpg']),
    array_replace($row,['id'=>3,'booking_reference'=>'OP1012-9-12-02']),
    array_replace($row,['id'=>4,'session_id'=>11,'court'=>8]),
]);
verify_reference_group(count($groups)===3,'Group same reference within session, keeping separate references/sessions distinct');
$group=$groups[0];
verify_reference_group($group['playerCount']===2 && $group['amount']===300.0,'Grouped player count and total');
verify_reference_group(count($group['detailRows'])===2,'Keep individual player details');
verify_reference_group($group['historyStatus']==='Held','Mixed pending/confirmed group stays in pending history');
verify_reference_group($group['detailRows'][1]['receipt']==='uploads/second.jpg','Keep each player receipt');
verify_reference_group($group['detailRows'][1]['timeRanges']==='9:00 AM - 12:00 PM','Keep full session time');
verify_reference_group($group['confirmedAmount']===150.0 && $group['confirmedHours']===3.0,'Only confirmed players count toward played totals');
$cancelled=member_group_open_play_reservations([array_replace($row,['status'=>'Cancelled'])])[0];
verify_reference_group($cancelled['historyStatus']==='Cancelled','Cancelled-only group remains in Cancelled');
verify_reference_group(count(member_group_reservations([$row,array_replace($row,['id'=>2])] ))===1,'Regular reference grouping preserved');
echo "Booking history reference grouping, player details, mixed statuses, totals and session isolation passed.\n";
