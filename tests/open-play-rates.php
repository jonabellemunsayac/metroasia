<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
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
function check_op(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }
$pdo->beginTransaction();
try {
    $date='2098-10-20'; $day=date('l',strtotime($date)); $key=bin2hex(random_bytes(16));
    $slots=$pdo->query("SELECT id,starts_at,ends_at FROM time_slots WHERE starts_at>='09:00:00' AND ends_at<='12:00:00' AND starts_at<'12:00:00' ORDER BY starts_at")->fetchAll();
    check_op(count($slots)===3,'Expected 3 hourly slots');
    $q=$pdo->prepare("INSERT INTO rates (court_id,sport,day_of_week,time_slot_id,rate_per_hour,effective_date,open_play_key,max_players,rate_per_player,session_start,session_end) VALUES (7,'Pickleball',?,?,265,?,?,10,150,'09:00:00','12:00:00')");
    foreach($slots as $slot)$q->execute([$day,$slot['id'],$date,$key]);
    $offers=array_values(array_filter(open_play_offers($pdo,$date),fn($o)=>$o['key']===$key));
    check_op(count($offers)===1,'Hourly rows must form one session');
    check_op($offers[0]['capacity']===10 && $offers[0]['price']===150.0 && count($offers[0]['slotIds'])===3,'Session capacity and price');
    check_op(open_play_resource_conflict($pdo,$date,(int)$slots[1]['id'],2)!==null,'Miami must conflict');
    check_op(open_play_resource_conflict($pdo,$date,(int)$slots[1]['id'],1)===null,'Lakers remains separate');
    check_op(open_play_resource_conflict($pdo,$date,(int)$slots[1]['id'],7)!==null,'Normal court booking blocked');
    $q=$pdo->prepare("INSERT INTO open_play_sessions (rate_key,court_id,sport,starts_at,ends_at,title,session_date,session_time,price,capacity,level_label,description) VALUES (?,7,'Pickleball','09:00:00','12:00:00','Test',?,'9 AM - 12 PM',150,10,'All','Test')");$q->execute([$key,$date]);$session=(int)$pdo->lastInsertId();
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
    check_op(!array_filter(open_play_offers($pdo,'2098-10-19'),fn($o)=>$o['key']===$key),'Future effective date respected');
    $q=$pdo->prepare('DELETE FROM rates WHERE open_play_key=?');$q->execute([$key]);
    $offer=array_values(array_filter(open_play_offers($pdo,$date),fn($o)=>$o['key']===$key))[0];
    check_op(!empty($offer['retired']) && count($offer['occupied'])===5,'Rate deletion must preserve booked sessions');
    $pdo->rollBack();
    echo "Open Play session grouping, rates, capacity, cancellation, effective date, and parent conflicts passed.\n";
} catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
