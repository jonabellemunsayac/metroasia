<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
const BLOCKING_RESERVATION_STATUS_SQL = "'Held','Booked'";
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/site-config.php';
require __DIR__ . '/../includes/data-privacy.php';
require __DIR__ . '/../includes/open-play-rates.php';
$source=file_get_contents(__DIR__ . '/../api.php');
$start=strpos($source,'function json_response');
eval(substr($source,$start,strpos($source,'$action = $_GET')-$start));
$pdo=db();
foreach (['admin'=>true,'super_admin'=>true,'reception'=>false,'executive'=>false,'staff'=>false] as $role=>$allowed) {
    if (admin_can_manage_operations(['role'=>$role]) !== $allowed) throw new RuntimeException('Incorrect cancellation role permission');
}
ensure_open_play_rates($pdo);
ensure_rate_management_schema($pdo);
ensure_sport_time_slot_availability($pdo);
function check_op(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }
$pdo->beginTransaction();
try {
    $date='2098-11-22'; $key=bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO courts (display_number,name,supported_sports) VALUES (999,?,'Pickleball')")->execute(['Cancellation '.$key]);
    $court=(int)$pdo->lastInsertId();
    $ids=array_map('intval',$pdo->query("SELECT id FROM time_slots WHERE starts_at>='09:00:00' AND starts_at<'12:00:00' ORDER BY starts_at")->fetchAll(PDO::FETCH_COLUMN));
    $session=open_play_create_calendar_session($pdo,$date,$court,'Pickleball',$ids,10,150);
    $key=$pdo->query("SELECT rate_key FROM open_play_sessions WHERE id=$session")->fetchColumn();
    $q=$pdo->prepare("INSERT INTO open_play_reservations (session_id,seat_number,status,customer_name,customer_phone,payment_method) VALUES (?, ?, ?, 'Cancellation player','09171234567','Test')");
    for($seat=1;$seat<=6;$seat++) $q->execute([$session,$seat,$seat===3?'Cancelled':'Held']);
    $pdo->prepare("INSERT INTO admin_users (name,email,password_hash,role) VALUES ('Cancellation test',?,'unused','admin')")->execute(['cancel-'.$key.'@example.test']);
    $adminId = (int) $pdo->lastInsertId();
    $blankRejected = false;
    try { cancel_open_play_session($pdo,$session,$adminId,'  '); } catch (InvalidArgumentException) { $blankRejected=true; }
    check_op($blankRejected,'A cancellation reason is mandatory');
    $pdo->exec("UPDATE open_play_reservations SET status='Booked' WHERE session_id=$session AND seat_number=1");
    cancel_open_play_session($pdo,$session,$adminId,'Court maintenance');
    $cancelled=$pdo->query("SELECT * FROM open_play_sessions WHERE id=$session")->fetch();
    check_op(!$cancelled['is_active'] && $cancelled['cancel_reason']==='Court maintenance' && (int)$cancelled['cancelled_by']===$adminId && $cancelled['cancelled_at'],'Session stores cancellation audit metadata');
    check_op((int)$pdo->query("SELECT COUNT(*) FROM open_play_reservations WHERE session_id=$session AND status IN ('Held','Booked')")->fetchColumn()===0,'Cancel both pending and confirmed players');
    check_op((int)$pdo->query("SELECT COUNT(*) FROM open_play_reservations WHERE session_id=$session AND cancel_reason='Court maintenance'")->fetchColumn()===5,'Only active player reasons are updated');
    check_op(!array_filter(open_play_offers($pdo,$date),fn($o)=>$o['key']===$key),'Cancelled session cannot accept bookings');
    check_op(!array_filter(open_play_calendar_sessions($pdo),fn($o)=>(int)$o['sessionId']===$session),'Cancelled session releases calendar hours');
    check_op((int)$pdo->query("SELECT COUNT(*) FROM override_logs WHERE admin_id=$adminId AND action='cancel-open-play'")->fetchColumn()===1,'Audit log records session cancellation');
    $duplicateRejected=false;
    try { cancel_open_play_session($pdo,$session,$adminId,'Changed reason'); } catch (InvalidArgumentException) { $duplicateRejected=true; }
    check_op($duplicateRejected,'Repeated cancellation preserves the original reason');
    $emptySession=open_play_create_calendar_session($pdo,$date,$court,'Pickleball',$ids,10,150);
    cancel_open_play_session($pdo,$emptySession,$adminId,'No players');
    check_op(count(array_filter(open_play_admin_session_records($pdo),fn($o)=>(int)$o['sessionId']===$emptySession && $o['sessionCancelReason']==='No players'))===1,'Empty cancelled sessions remain in admin history');
    $pdo->rollBack();
    echo "Session cancellation: reason, players, audit, availability, repeat protection and empty-session history passed.\n";
} catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
