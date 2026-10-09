<?php
declare(strict_types=1);

function ensure_open_play_rates(PDO $pdo): void
{
    $columns = [
        'rates' => ['open_play_key' => 'VARCHAR(64) NULL', 'max_players' => 'INT UNSIGNED NULL', 'rate_per_player' => 'DECIMAL(10,2) NULL', 'session_start' => 'TIME NULL', 'session_end' => 'TIME NULL'],
        'open_play_sessions' => ['rate_key' => 'VARCHAR(64) NULL', 'court_id' => 'INT UNSIGNED NULL', 'sport' => 'VARCHAR(30) NULL', 'starts_at' => 'TIME NULL', 'ends_at' => 'TIME NULL'],
        'open_play_reservations' => ['seat_number' => 'INT UNSIGNED NULL', 'active_seat' => "INT GENERATED ALWAYS AS (CASE WHEN status IN ('Held','Booked') THEN seat_number ELSE NULL END) STORED"],
    ];
    foreach ($columns as $table => $definitions) {
        $existing = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($definitions as $name => $definition) {
            if (!in_array($name, $existing, true)) $pdo->exec("ALTER TABLE `$table` ADD `$name` $definition");
        }
    }
    $indexes = $pdo->query('SHOW INDEX FROM open_play_sessions')->fetchAll();
    if (!in_array('uniq_open_play_rate_date', array_column($indexes, 'Key_name'), true)) {
        $pdo->exec('ALTER TABLE open_play_sessions ADD UNIQUE KEY uniq_open_play_rate_date (rate_key, session_date)');
    }
    $indexes = $pdo->query('SHOW INDEX FROM open_play_reservations')->fetchAll();
    if (!in_array('uniq_open_play_active_seat', array_column($indexes, 'Key_name'), true)) {
        $pdo->exec('ALTER TABLE open_play_reservations ADD UNIQUE KEY uniq_open_play_active_seat (session_id, active_seat)');
    }
}

// The same day specificity and effective-date precedence used by regular rates.
function open_play_offers(PDO $pdo, string $date): array
{
    $day = holiday_name_for_date($pdo, $date) !== null ? 'Holiday' : date('l', strtotime($date));
    $q = $pdo->prepare("SELECT r.*, ts.starts_at, ts.ends_at, c.name AS court_name FROM rates r JOIN time_slots ts ON ts.id=r.time_slot_id JOIN courts c ON c.id=r.court_id WHERE r.effective_date<=? AND r.day_of_week IN ('Any',?) AND c.is_active=1 AND FIND_IN_SET(r.sport,c.supported_sports)>0 ORDER BY (r.day_of_week=?) DESC,r.effective_date DESC,r.id DESC");
    $q->execute([$date, $day, $day]);
    $winners = [];
    foreach ($q->fetchAll() as $row) {
        $key = $row['court_id'] . '|' . $row['sport'] . '|' . $row['time_slot_id'];
        $winners[$key] ??= $row;
    }
    $groups = [];
    foreach ($winners as $row) {
        if (!$row['open_play_key']) continue;
        $key = $row['open_play_key'];
        $groups[$key] ??= ['key' => $key, 'date' => $date, 'court' => (int) $row['court_id'], 'courtName' => $row['court_name'], 'sport' => $row['sport'], 'start' => substr($row['session_start'],0,5), 'end' => substr($row['session_end'],0,5), 'capacity' => (int) $row['max_players'], 'price' => (float) $row['rate_per_player'], 'slotIds' => [], 'occupied' => [], 'sessionId' => null];
        $groups[$key]['slotIds'][] = (int) $row['time_slot_id'];
    }
    $slots = $pdo->query('SELECT id, starts_at, ends_at FROM time_slots ORDER BY starts_at')->fetchAll();
    $disabledSlots = [];
    foreach ($pdo->query('SELECT sport,time_slot_id FROM sport_time_slot_availability WHERE is_available=0')->fetchAll() as $disabled) {
        $disabledSlots[$disabled['sport']][] = (int) $disabled['time_slot_id'];
    }
    foreach ($groups as $key => &$offer) {
        $expected = array_values(array_filter($slots, static fn($s) => substr($s['starts_at'],0,5) >= $offer['start'] && time_minutes_for_range($s['ends_at'],true) <= time_minutes_for_range($offer['end'],true)));
        // A later rate replacing part of a block must not leave a partial Open Play session.
        if (count($expected) !== count($offer['slotIds'])) { unset($groups[$key]); continue; }
        $offer['time'] = display_time_label($offer['start']) . ' - ' . display_time_label($offer['end']);
        $offer['disabled'] = (bool) array_intersect($offer['slotIds'], $disabledSlots[$offer['sport']] ?? []);
        $q = $pdo->prepare('SELECT * FROM open_play_sessions WHERE rate_key=? AND session_date=?');
        $q->execute([$key,$date]);
        if ($session = $q->fetch()) {
            $offer['disabled'] = $offer['disabled'] || !(bool) $session['is_active'];
            $offer['sessionId'] = (int) $session['id'];
            $offer['capacity'] = (int) $session['capacity'];
            $offer['price'] = (float) $session['price'];
            $q = $pdo->prepare("SELECT seat_number FROM open_play_reservations WHERE session_id=? AND status IN ('Held','Booked')");
            $q->execute([$session['id']]);
            $offer['occupied'] = array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        }
    }
    unset($offer);
    $q=$pdo->prepare("SELECT s.*,c.name AS court_name FROM open_play_sessions s JOIN courts c ON c.id=s.court_id WHERE s.session_date=? AND s.rate_key IS NOT NULL AND EXISTS(SELECT 1 FROM open_play_reservations r WHERE r.session_id=s.id AND r.status IN ('Held','Booked'))");
    $q->execute([$date]);
    foreach($q->fetchAll() as $session) {
        if(isset($groups[$session['rate_key']])) continue;
        $used=$pdo->prepare("SELECT seat_number FROM open_play_reservations WHERE session_id=? AND status IN ('Held','Booked')");$used->execute([$session['id']]);
        $groups[$session['rate_key']]=['key'=>$session['rate_key'],'date'=>$date,'court'=>(int)$session['court_id'],'courtName'=>$session['court_name'],'sport'=>$session['sport'],'start'=>substr($session['starts_at'],0,5),'end'=>substr($session['ends_at'],0,5),'time'=>$session['session_time'],'capacity'=>(int)$session['capacity'],'price'=>(float)$session['price'],'sessionId'=>(int)$session['id'],'retired'=>true,'occupied'=>array_map('intval',$used->fetchAll(PDO::FETCH_COLUMN)),'slotIds'=>array_map(static fn($s)=>(int)$s['id'],array_values(array_filter($slots,static fn($s)=>$s['starts_at'] >= $session['starts_at'] && time_minutes_for_range($s['ends_at'],true)<=time_minutes_for_range($session['ends_at'],true))))];
    }
    return array_values($groups);
}

function open_play_resource_conflict(PDO $pdo, string $date, int $slotId, int $courtId): ?array
{
    foreach (open_play_offers($pdo,$date) as $offer) {
        if (in_array($slotId,$offer['slotIds'],true) && court_booking_resources_conflict($offer['court'],$courtId)) {
            return ['status'=>'Blocked','message'=>'This court is reserved for OPEN PLAY during this session.'];
        }
    }
    // Reservations remain protected even after their rate is superseded or deleted.
    $q=$pdo->prepare("SELECT s.court_id FROM open_play_sessions s JOIN time_slots ts ON ts.id=? WHERE s.session_date=? AND s.court_id IS NOT NULL AND ts.starts_at < IF(s.ends_at='00:00:00','24:00:00',s.ends_at) AND IF(ts.ends_at='00:00:00','24:00:00',ts.ends_at)>s.starts_at AND EXISTS(SELECT 1 FROM open_play_reservations r WHERE r.session_id=s.id AND r.status IN ('Held','Booked'))");
    $q->execute([$slotId,$date]);
    foreach($q->fetchAll(PDO::FETCH_COLUMN) as $other) if(court_booking_resources_conflict((int)$other,$courtId)) return ['status'=>'Blocked','message'=>'An Open Play session occupies this court.'];
    return null;
}

function open_play_lock_courts(PDO $pdo, int $court): void
{
    // Lock the parent and every subdivision in a stable order, shared with regular booking.
    $ids = in_array($court,[1,10,11,12],true) ? [1,10,11,12] : (in_array($court,[2,7,8,9],true) ? [2,7,8,9] : [$court]);
    $pdo->query('SELECT id FROM courts WHERE id IN (' . implode(',',$ids) . ') ORDER BY id FOR UPDATE')->fetchAll();
}

function open_play_book_session(PDO $pdo, string $receiptDir): never
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'message'=>'POST required.'],405);
    $member=current_member();
    if (!$member) json_response(['ok'=>false,'message'=>'Sign in to book Open Play.'],401);
    $date=require_field('date'); $key=require_field('rateKey');
    if(!valid_date_string($date)) json_response(['ok'=>false,'message'=>'Invalid date.'],422);
    require_booking_date_enabled($pdo,$date);
    $seats=json_decode((string)($_POST['seats'] ?? '[]'),true);
    if(!is_array($seats) || !$seats || count($seats)>200) json_response(['ok'=>false,'message'=>'Select available players.'],422);
    foreach($seats as $seat) if(filter_var($seat,FILTER_VALIDATE_INT)===false) json_response(['ok'=>false,'message'=>'Invalid player position.'],422);
    $seats=array_map('intval',$seats);
    if(count($seats)!==count(array_unique($seats))) json_response(['ok'=>false,'message'=>'Select each player position only once.'],422);
    $payment=require_field('paymentMethod'); require_active_payment_channel($pdo,$payment);
    $receipt=save_receipt($receiptDir);
    $offer=null;
    foreach(open_play_offers($pdo,$date) as $candidate) if($candidate['key']===$key) $offer=$candidate;
    if(!$offer || !empty($offer['retired']) || !empty($offer['disabled']) || strtotime($date.' '.$offer['start'])<=time()) json_response(['ok'=>false,'message'=>'This session is no longer available.'],409);
    foreach($offer['slotIds'] as $slot) if(!sport_time_slot_is_available($pdo,$offer['sport'],$slot)) json_response(['ok'=>false,'message'=>'This session contains a disabled time slot.'],409);
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    $pdo->beginTransaction();
    try {
        open_play_lock_courts($pdo,$offer['court']);
        $currentOffers = open_play_offers($pdo,$date);
        $fresh = array_values(array_filter($currentOffers, static fn($o) => $o['key']===$key));
        if (!$fresh || !empty($fresh[0]['retired']) || !empty($fresh[0]['disabled'])) throw new RuntimeException('The session changed. Refresh the session list.');
        $offer = $fresh[0];
        foreach ($currentOffers as $other) {
            if ($other['key']!==$key && court_booking_resources_conflict($other['court'],$offer['court']) && array_intersect($other['slotIds'],$offer['slotIds'])) throw new RuntimeException('Another Open Play session conflicts with this court. Ask admin to correct the overlapping rates.');
        }
        $existing = $pdo->prepare("SELECT s.court_id,s.starts_at,s.ends_at FROM open_play_sessions s WHERE s.session_date=? AND s.rate_key<>? AND EXISTS(SELECT 1 FROM open_play_reservations r WHERE r.session_id=s.id AND r.status IN ('Held','Booked'))");
        $existing->execute([$date,$key]);
        foreach($existing->fetchAll() as $session) {
            if(court_booking_resources_conflict((int)$session['court_id'],$offer['court']) && time_minutes_for_range($session['starts_at']) < time_minutes_for_range($offer['end'],true) && time_minutes_for_range($session['ends_at'],true)>time_minutes_for_range($offer['start'])) throw new RuntimeException('Another reserved Open Play session occupies this court.');
        }
        foreach($offer['slotIds'] as $slot) {
            $conflict=active_court_conflict($pdo,$date,$slot,$offer['court'],$offer['sport'],null,true,true);
            if($conflict) throw new RuntimeException($conflict['message']);
        }
        $q=$pdo->prepare("INSERT IGNORE INTO open_play_sessions (rate_key,court_id,sport,starts_at,ends_at,title,session_date,session_time,price,capacity,level_label,description) VALUES (?,?,?,?,?,?,?,?,?,?,'All levels','Open Play')");
        $q->execute([$key,$offer['court'],$offer['sport'],$offer['start'],$offer['end'],$offer['courtName'].' OPEN PLAY',$date,$offer['time'],$offer['price'],$offer['capacity']]);
        $q=$pdo->prepare('SELECT * FROM open_play_sessions WHERE rate_key=? AND session_date=? FOR UPDATE');$q->execute([$key,$date]);$session=$q->fetch();
        $q=$pdo->prepare("SELECT seat_number FROM open_play_reservations WHERE session_id=? AND status IN ('Held','Booked') FOR UPDATE");$q->execute([$session['id']]);$occupied=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        if(count($occupied)+count($seats)>(int)$session['capacity']) throw new RuntimeException('This session is full.');
        foreach($seats as $seat) if($seat<1 || $seat>(int)$session['capacity'] || in_array($seat,$occupied,true)) throw new RuntimeException('A selected player position is already occupied. Please choose again.');
        $q=$pdo->prepare("INSERT INTO open_play_reservations (member_id,session_id,seat_number,status,customer_name,customer_email,customer_phone,payment_method,receipt_path,final_amount,rate_snapshot) VALUES (?,?,?,'Held',?,?,?,?,?,?,?)");
        foreach($seats as $seat) $q->execute([$member['id'],$session['id'],$seat,$member['name'],$member['email'],$member['phone'],$payment,$receipt,$session['price'],rate_snapshot(['sessionId'=>$session['id'],'date'=>$date,'time'=>$offer['time'],'seat'=>$seat],(float)$session['price'],'openplay')]);
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        json_response(['ok'=>false,'message'=>$e instanceof RuntimeException ? $e->getMessage() : 'The session could not be reserved. Refresh and try again.'],409);
    }
    json_response(['ok'=>true,'message'=>'Open Play players reserved. Payment is awaiting review.','bookingReference'=>'OP-'.$session['id'],'state'=>get_state($pdo,false)]);
}

function open_play_admin_rows(PDO $pdo): array
{
    return $pdo->query("SELECT CONCAT('openplay:',r.id) AS id,r.customer_name AS customerName,r.status,r.payment_method AS paymentMethod,r.receipt_path AS receipt,r.final_amount AS finalAmount,r.seat_number AS seat,s.session_date AS date,s.session_time AS time,s.sport,s.id AS sessionId,s.court_id AS court,s.starts_at AS startsAt,s.ends_at AS endsAt,c.name AS courtName,s.capacity,(SELECT COUNT(*) FROM open_play_reservations a WHERE a.session_id=s.id AND a.status IN ('Held','Booked')) AS occupied FROM open_play_reservations r JOIN open_play_sessions s ON s.id=r.session_id LEFT JOIN courts c ON c.id=s.court_id WHERE s.rate_key IS NOT NULL ORDER BY r.created_at DESC,r.id DESC")->fetchAll();
}
