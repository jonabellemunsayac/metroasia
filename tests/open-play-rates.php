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
ensure_open_play_rates($pdo);
ensure_rate_management_schema($pdo);
ensure_sport_time_slot_availability($pdo);
function check_op(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }
$pdo->beginTransaction();
try {
    $date='2098-10-20'; $day=date('l',strtotime($date)); $key=bin2hex(random_bytes(16));
    $slots=$pdo->query("SELECT id,starts_at,ends_at FROM time_slots WHERE starts_at>='09:00:00' AND ends_at<='12:00:00' AND starts_at<'12:00:00' ORDER BY starts_at")->fetchAll();
    check_op(count($slots)===3,'Expected 3 hourly slots');
    $pdo->exec("UPDATE courts SET is_active=1 WHERE id=7");
    $pdo->exec("DELETE FROM sport_time_slot_availability WHERE sport='Pickleball'");
    $ids=array_map(static fn($s)=>(int)$s['id'],$slots);
    $rateCount=(int)$pdo->query('SELECT COUNT(*) FROM rates')->fetchColumn();
    $session=open_play_create_calendar_session($pdo,$date,7,'Pickleball',$ids,10,150);
    $key=$pdo->query("SELECT rate_key FROM open_play_sessions WHERE id=$session")->fetchColumn();
    check_op(open_play_next_reference($pdo,$session)==='OP1020-9-12-01','First session reference format');
    check_op(open_play_next_reference($pdo,$session)==='OP1020-9-12-02','Sequence advances within session');
    $otherSession=open_play_create_calendar_session($pdo,'2098-10-21',7,'Pickleball',$ids,10,150);
    check_op(open_play_next_reference($pdo,$otherSession)==='OP1021-9-12-01','New dated session starts at 01');
    check_op((int)$pdo->query('SELECT COUNT(*) FROM rates')->fetchColumn()===$rateCount,'Calendar setup must not write rates');
    $regular=$pdo->prepare("INSERT INTO rates (court_id,sport,day_of_week,time_slot_id,rate_per_hour,effective_date) VALUES (7,'Pickleball','Any',?,265,'2098-10-01')");
    $regular->execute([$ids[0]]);
    $legacyKey=bin2hex(random_bytes(16));
    $legacy=$pdo->prepare("INSERT INTO rates (court_id,sport,day_of_week,time_slot_id,rate_per_hour,effective_date,open_play_key) VALUES (7,'Pickleball','Any',?,999,'2098-10-02',?)");
    $legacy->execute([$ids[0],$legacyKey]);
    $legacyId=(int)$pdo->lastInsertId();
    check_op(calculate_booking_rate($pdo,7,'Pickleball',$date,$slots[0],false)['baseRate']===265.0,'Obsolete Open Play prices must not replace regular rates');
    check_op(!array_filter(rate_rules($pdo),fn($r)=>$r['id']===$legacyId),'Obsolete Open Play rate hidden from Rates Management');
    check_op(!array_filter(open_play_offers($pdo,$date),fn($o)=>$o['key']===$legacyKey),'Rate metadata must not create a session');
    $overlapRejected=false;
    try { open_play_create_calendar_session($pdo,$date,2,'Basketball',$ids,10,150); } catch(RuntimeException $e) { $overlapRejected=true; }
    check_op($overlapRejected,'Parent court cannot overlap an empty Open Play session');
    $gapRejected=false;
    try { open_play_create_calendar_session($pdo,'2098-10-21',7,'Pickleball',[$ids[0],$ids[2]],10,150); } catch(InvalidArgumentException $e) { $gapRejected=true; }
    check_op($gapRejected,'Non-consecutive slots rejected');
    $offers=array_values(array_filter(open_play_offers($pdo,$date),fn($o)=>$o['key']===$key));
    check_op(count($offers)===1,'Selected hourly slots must form one session');
    check_op($offers[0]['capacity']===10 && $offers[0]['price']===150.0 && count($offers[0]['slotIds'])===3,'Session capacity and price');
    check_op(open_play_resource_conflict($pdo,$date,(int)$slots[1]['id'],2)!==null,'Miami must conflict');
    check_op(open_play_resource_conflict($pdo,$date,(int)$slots[1]['id'],1)===null,'Lakers remains separate');
    check_op(open_play_resource_conflict($pdo,$date,(int)$slots[1]['id'],7)!==null,'Normal court booking blocked');
    $q=$pdo->prepare("INSERT INTO open_play_reservations (session_id,seat_number,status,customer_name,customer_phone,payment_method,final_amount) VALUES (?,?,'Held','Test Player','09171234567','Test',150)");
    for($seat=1;$seat<=6;$seat++)$q->execute([$session,$seat]);
    $duplicateRejected=false;
    try { $q->execute([$session,1]); } catch(PDOException $e) { $duplicateRejected=$e->getCode()==='23000'; }
    check_op($duplicateRejected,'Database must reject duplicate active seats');
    $offer=array_values(array_filter(open_play_offers($pdo,$date),fn($o)=>$o['key']===$key))[0];
    check_op(count($offer['occupied'])===6,'Capacity must count session players, not hours');
    $pdo->exec("UPDATE open_play_reservations SET status='Cancelled' WHERE session_id=$session AND seat_number=3");
    $offer=array_values(array_filter(open_play_offers($pdo,$date),fn($o)=>$o['key']===$key))[0];
    check_op(count($offer['occupied'])===5 && !in_array(3,$offer['occupied'],true),'Cancellation releases a seat');
    check_op(open_play_next_reference($pdo,$session)==='OP1020-9-12-03','Cancellation does not reuse a reference');
    $pdo->exec("UPDATE open_play_sessions SET booking_sequence=99 WHERE id=$session");
    check_op(open_play_next_reference($pdo,$session)==='OP1020-9-12-100','Sequence grows beyond two digits');
    check_op(!array_filter(open_play_offers($pdo,'2098-10-19'),fn($o)=>$o['key']===$key),'Session does not recur on another date');
    $q=$pdo->prepare('DELETE FROM rates WHERE open_play_key=?');$q->execute([$key]);
    $offer=array_values(array_filter(open_play_offers($pdo,$date),fn($o)=>$o['key']===$key))[0];
    check_op(empty($offer['disabled']) && count($offer['occupied'])===5,'Sessions remain available independently of rates');
    check_op(!array_filter(open_play_offers($pdo,'2098-10-27'),fn($o)=>$o['key']===$key),'Session must not recur the following week');
    $pdo->rollBack();
    echo "Calendar Open Play: grouping, no rate writes, overlap/gap validation, capacity, cancellation, date isolation and parent conflicts passed.\n";
} catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
