<?php
declare(strict_types=1);

class BookingUnavailable extends RuntimeException {}

function ensure_open_play_rates(PDO $pdo): void
{
    $columns = [
        'rates' => ['open_play_key' => 'VARCHAR(64) NULL', 'max_players' => 'INT UNSIGNED NULL', 'rate_per_player' => 'DECIMAL(10,2) NULL', 'session_start' => 'TIME NULL', 'session_end' => 'TIME NULL'],
        'open_play_sessions' => ['booking_sequence' => 'INT UNSIGNED NOT NULL DEFAULT 0', 'rate_key' => 'VARCHAR(64) NULL', 'court_id' => 'INT UNSIGNED NULL', 'sport' => 'VARCHAR(30) NULL', 'starts_at' => 'TIME NULL', 'ends_at' => 'TIME NULL'],
        'open_play_reservations' => ['booking_reference' => 'VARCHAR(64) NULL', 'seat_number' => 'INT UNSIGNED NULL', 'active_seat' => "INT GENERATED ALWAYS AS (CASE WHEN status IN ('Held','Booked') THEN seat_number ELSE NULL END) STORED"],
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

// Calendar sessions are dated independently of hourly rates.
function open_play_offers(PDO $pdo, string $date, ?int $memberId = null): array
{
    $slots = $pdo->query('SELECT id, starts_at, ends_at FROM time_slots ORDER BY starts_at')->fetchAll();
    $disabledSlots = [];
    foreach ($pdo->query('SELECT sport,time_slot_id FROM sport_time_slot_availability WHERE is_available=0')->fetchAll() as $row) {
        $disabledSlots[$row['sport']][] = (int) $row['time_slot_id'];
    }
    $q = $pdo->prepare("SELECT s.*, c.name AS court_name, c.is_active AS court_active, c.supported_sports FROM open_play_sessions s JOIN courts c ON c.id=s.court_id WHERE s.session_date=? AND s.rate_key IS NOT NULL AND (s.is_active=1 OR EXISTS(SELECT 1 FROM open_play_reservations r WHERE r.session_id=s.id AND r.status IN ('Held','Booked')))");
    $q->execute([$date]);
    $offers = [];
    foreach ($q->fetchAll() as $session) {
        $slotIds = array_map(static fn($slot) => (int) $slot['id'], array_values(array_filter($slots, static fn($slot) =>
            $slot['starts_at'] >= $session['starts_at'] && time_minutes_for_range($slot['ends_at'], true) <= time_minutes_for_range($session['ends_at'], true)
        )));
        $used = $pdo->prepare("SELECT seat_number,member_id FROM open_play_reservations WHERE session_id=? AND status IN ('Held','Booked')");
        $used->execute([$session['id']]);
        $players = $used->fetchAll();
        $offers[] = [
            'key'=>$session['rate_key'], 'date'=>$date, 'court'=>(int)$session['court_id'],
            'courtName'=>$session['court_name'], 'sport'=>$session['sport'],
            'start'=>substr($session['starts_at'],0,5), 'end'=>substr($session['ends_at'],0,5),
            'time'=>$session['session_time'], 'capacity'=>(int)$session['capacity'], 'price'=>(float)$session['price'],
            'slotIds'=>$slotIds, 'occupied'=>array_map('intval',array_column($players,'seat_number')),
            'memberBooked'=>$memberId !== null && in_array($memberId,array_map('intval',array_column($players,'member_id')),true),
            'sessionId'=>(int)$session['id'],
            'disabled'=>!$session['is_active'] || !$session['court_active'] || !in_array($session['sport'], explode(',', $session['supported_sports']), true) || (bool)array_intersect($slotIds,$disabledSlots[$session['sport']] ?? [])
        ];
    }
    return $offers;
}

function open_play_calendar_sessions(PDO $pdo): array
{
    return $pdo->query("SELECT s.id AS sessionId,s.rate_key AS sessionKey,s.session_date AS date,s.session_time AS time,s.sport,s.court_id AS court,s.starts_at AS startsAt,s.ends_at AS endsAt,s.price,s.capacity,c.name AS courtName,(SELECT COUNT(*) FROM open_play_reservations r WHERE r.session_id=s.id AND r.status IN ('Held','Booked')) AS occupied FROM open_play_sessions s JOIN courts c ON c.id=s.court_id WHERE s.rate_key IS NOT NULL AND (s.is_active=1 OR EXISTS(SELECT 1 FROM open_play_reservations r WHERE r.session_id=s.id AND r.status IN ('Held','Booked')))")->fetchAll();
}

// Caller initializes schema before starting a transaction.
function open_play_create_calendar_session(PDO $pdo, string $date, int $court, string $sport, array $slotIds, int $capacity, float $price): int
{
    if (!valid_date_string($date) || !in_array($sport,valid_booking_sports(),true) || $capacity<1 || $capacity>200 || !is_finite($price) || $price<=0) throw new InvalidArgumentException('Use a valid date, sport, rate and maximum of 1-200 players.');
    if (!$slotIds || count($slotIds)>48 || count($slotIds)!==count(array_unique($slotIds))) throw new InvalidArgumentException('Select consecutive time slots once each.');
    $q=$pdo->prepare('SELECT * FROM courts WHERE id=? AND is_active=1'); $q->execute([$court]); $courtRow=$q->fetch();
    if (!$courtRow || !in_array($sport,normalize_supported_sports($courtRow['supported_sports']),true)) throw new InvalidArgumentException('Select an active court for this sport.');
    $q=$pdo->prepare('SELECT * FROM time_slots WHERE id IN ('.implode(',',array_fill(0,count($slotIds),'?')).') ORDER BY starts_at');
    $q->execute($slotIds); $slots=$q->fetchAll();
    if(count($slots)!==count($slotIds)) throw new InvalidArgumentException('Invalid time slot.');
    foreach($slots as $index=>$slot) {
        if(strtotime($date.' '.$slot['starts_at'])<=time()) throw new InvalidArgumentException('Select future time slots.');
        if(time_minutes_for_range($slot['ends_at'],true)<=time_minutes_for_range($slot['starts_at'])) throw new InvalidArgumentException('Select a session within one date.');
        if($index && $slots[$index-1]['ends_at']!==$slot['starts_at']) throw new InvalidArgumentException('Select consecutive time slots on one date.');
        $q=$pdo->prepare('SELECT is_available FROM sport_time_slot_availability WHERE sport=? AND time_slot_id=?');$q->execute([$sport,$slot['id']]);
        $availability=$q->fetchColumn();
        if($availability!==false && !(bool)$availability) throw new InvalidArgumentException('This sport has a disabled time slot in the selected range.');
    }
    open_play_lock_courts($pdo,$court);
    foreach($slots as $slot) {
        $conflict=active_court_conflict($pdo,$date,(int)$slot['id'],$court,$sport,null,true);
        if($conflict) throw new BookingUnavailable($conflict['message']);
    }
    $start=$slots[0]['starts_at'];$end=$slots[count($slots)-1]['ends_at'];
    $q=$pdo->prepare("INSERT INTO open_play_sessions (rate_key,court_id,sport,starts_at,ends_at,title,session_date,session_time,price,capacity,level_label,description) VALUES (?,?,?,?,?,?,?,?,?,?,'All levels','Created from admin calendar')");
    $q->execute([bin2hex(random_bytes(16)),$court,$sport,$start,$end,$courtRow['name'].' OPEN PLAY',$date,display_time_label($start).' - '.display_time_label($end),$price,$capacity]);
    return (int)$pdo->lastInsertId();
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

// Caller holds the court and session locks. Ignore this session's own reserved hours.
function open_play_session_has_conflict(PDO $pdo, array $session): bool
{
    if (empty($session['court_id'])) return false; // Legacy sessions without a court.
    if (empty($session['sport']) || empty($session['starts_at']) || empty($session['ends_at'])) return true;
    $court=(int)$session['court_id'];
    $start=time_minutes_for_range($session['starts_at']);
    $end=time_minutes_for_range($session['ends_at'],true);
    $matched=false;
    foreach ($pdo->query('SELECT id,starts_at,ends_at FROM time_slots')->fetchAll() as $slot) {
        if (time_minutes_for_range($slot['starts_at']) >= $end || time_minutes_for_range($slot['ends_at'],true) <= $start) continue;
        $matched=true;
        if (active_court_conflict($pdo,$session['session_date'],(int)$slot['id'],$court,$session['sport'],null,true,true)) return true;
    }
    if (!$matched) return true;
    $q=$pdo->prepare("SELECT s.* FROM open_play_sessions s WHERE s.session_date=? AND s.id<>? AND s.court_id IS NOT NULL AND ((s.is_active=1 AND s.rate_key IS NOT NULL) OR EXISTS(SELECT 1 FROM open_play_reservations r WHERE r.session_id=s.id AND r.status IN ('Held','Booked')))");
    $q->execute([$session['session_date'],$session['id']]);
    foreach ($q->fetchAll() as $other) {
        if (court_booking_resources_conflict($court,(int)$other['court_id']) && time_minutes_for_range($other['starts_at']) < $end && time_minutes_for_range($other['ends_at'],true) > $start) return true;
    }
    return false;
}

// Called inside the booking transaction; the session lock serializes reference allocation.
function open_play_next_reference(PDO $pdo, int $sessionId): string
{
    $q=$pdo->prepare('SELECT session_date,starts_at,ends_at,booking_sequence FROM open_play_sessions WHERE id=? FOR UPDATE');
    $q->execute([$sessionId]); $session=$q->fetch();
    if (!$session) throw new RuntimeException('Open Play session not found.');
    $sequence=(int)$session['booking_sequence']+1;
    $q=$pdo->prepare('UPDATE open_play_sessions SET booking_sequence=? WHERE id=?');
    $q->execute([$sequence,$sessionId]);
    $timePart=static function(string $value): string {
        $time=strtotime($value);
        return date('g',$time).(date('i',$time)==='00' ? '' : ':'.date('i',$time));
    };
    return 'OP'.date('md',strtotime($session['session_date'])).'-'.$timePart($session['starts_at']).'-'.$timePart($session['ends_at']).'-'.str_pad((string)$sequence,2,'0',STR_PAD_LEFT);
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
    if(!is_array($seats) || count($seats)!==1) json_response(['ok'=>false,'message'=>'Select one player position per Open Play session.'],422);
    foreach($seats as $seat) if(filter_var($seat,FILTER_VALIDATE_INT)===false) json_response(['ok'=>false,'message'=>'Invalid player position.'],422);
    $seats=array_map('intval',$seats);
    if(count($seats)!==count(array_unique($seats))) json_response(['ok'=>false,'message'=>'Select each player position only once.'],422);
    $payment=require_field('paymentMethod'); require_active_payment_channel($pdo,$payment);
    $receipt=save_receipt($receiptDir);
    $offer=null;
    foreach(open_play_offers($pdo,$date) as $candidate) if($candidate['key']===$key) $offer=$candidate;
    if(!$offer || !empty($offer['retired']) || !empty($offer['disabled']) || strtotime($date.' '.$offer['start'])<=time()) booking_taken($pdo);
    foreach($offer['slotIds'] as $slot) if(!sport_time_slot_is_available($pdo,$offer['sport'],$slot)) booking_taken($pdo);
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    $pdo->beginTransaction();
    try {
        open_play_lock_courts($pdo,$offer['court']);
        $currentOffers = open_play_offers($pdo,$date);
        $fresh = array_values(array_filter($currentOffers, static fn($o) => $o['key']===$key));
        if (!$fresh || !empty($fresh[0]['retired']) || !empty($fresh[0]['disabled']) || strtotime($date.' '.$fresh[0]['start'])<=time()) throw new BookingUnavailable('The session changed. Refresh the session list.');
        $offer = $fresh[0];
        foreach ($currentOffers as $other) {
            if ($other['key']!==$key && court_booking_resources_conflict($other['court'],$offer['court']) && array_intersect($other['slotIds'],$offer['slotIds'])) throw new BookingUnavailable('Another Open Play session conflicts with this court. Ask admin to review the overlapping sessions.');
        }
        $existing = $pdo->prepare("SELECT s.court_id,s.starts_at,s.ends_at FROM open_play_sessions s WHERE s.session_date=? AND s.rate_key<>? AND EXISTS(SELECT 1 FROM open_play_reservations r WHERE r.session_id=s.id AND r.status IN ('Held','Booked'))");
        $existing->execute([$date,$key]);
        foreach($existing->fetchAll() as $session) {
            if(court_booking_resources_conflict((int)$session['court_id'],$offer['court']) && time_minutes_for_range($session['starts_at']) < time_minutes_for_range($offer['end'],true) && time_minutes_for_range($session['ends_at'],true)>time_minutes_for_range($offer['start'])) throw new BookingUnavailable('Another reserved Open Play session occupies this court.');
        }
        foreach($offer['slotIds'] as $slot) {
            $conflict=active_court_conflict($pdo,$date,$slot,$offer['court'],$offer['sport'],null,true,true);
            if($conflict) throw new BookingUnavailable($conflict['message']);
        }
        $q=$pdo->prepare('SELECT * FROM open_play_sessions WHERE rate_key=? AND session_date=? FOR UPDATE');$q->execute([$key,$date]);$session=$q->fetch();
        if (!$session || !$session['is_active']) throw new BookingUnavailable('This session is no longer available.');
        $alreadyJoined=$pdo->prepare("SELECT id FROM open_play_reservations WHERE session_id=? AND member_id=? AND status IN ('Held','Booked') FOR UPDATE");
        $alreadyJoined->execute([$session['id'],$member['id']]);
        if ($alreadyJoined->fetchColumn()) throw new BookingUnavailable('Already joined this session.');
        $q=$pdo->prepare("SELECT seat_number FROM open_play_reservations WHERE session_id=? AND status IN ('Held','Booked') FOR UPDATE");$q->execute([$session['id']]);$occupied=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        if(count($occupied)+count($seats)>(int)$session['capacity']) throw new BookingUnavailable('This session is full.');
        foreach($seats as $seat) if($seat<1 || $seat>(int)$session['capacity'] || in_array($seat,$occupied,true)) throw new BookingUnavailable('A selected player position is already occupied. Please choose again.');
        $reference=open_play_next_reference($pdo,(int)$session['id']);
        $q=$pdo->prepare("INSERT INTO open_play_reservations (booking_reference,member_id,session_id,seat_number,status,customer_name,customer_email,customer_phone,payment_method,receipt_path,final_amount,rate_snapshot) VALUES (?,?,?,?,'Held',?,?,?,?,?,?,?)");
        foreach($seats as $seat) $q->execute([$reference,$member['id'],$session['id'],$seat,$member['name'],$member['email'],$member['phone'],$payment,$receipt,$session['price'],rate_snapshot(['sessionId'=>$session['id'],'date'=>$date,'time'=>$offer['time'],'seat'=>$seat],(float)$session['price'],'openplay')]);
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        if ($e instanceof BookingUnavailable || is_database_write_conflict($e)) booking_taken($pdo);
        json_response(['ok'=>false,'message'=>'Open Play reservation could not be saved. Please try again.'],500);
    }
    json_response(['ok'=>true,'message'=>'Open Play players reserved. Payment is awaiting review.','bookingReference'=>$reference,'openPlayBooking'=>['date'=>$session['session_date'],'start'=>$session['starts_at'],'end'=>$session['ends_at'],'time'=>$session['session_time'],'court'=>(int)$session['court_id'],'courtName'=>$offer['courtName'],'sport'=>$session['sport']],'state'=>get_state($pdo,false)]);
}

function open_play_admin_rows(PDO $pdo): array
{
    return $pdo->query("SELECT CONCAT('openplay:',r.id) AS id,r.booking_reference AS bookingReference,r.customer_name AS customerName,r.status,r.payment_method AS paymentMethod,r.receipt_path AS receipt,r.final_amount AS finalAmount,r.seat_number AS seat,s.session_date AS date,s.session_time AS time,s.sport,s.id AS sessionId,s.court_id AS court,s.starts_at AS startsAt,s.ends_at AS endsAt,c.name AS courtName,s.capacity,s.price AS ratePerPlayer,(SELECT COUNT(*) FROM open_play_reservations a WHERE a.session_id=s.id AND a.status IN ('Held','Booked')) AS occupied FROM open_play_reservations r JOIN open_play_sessions s ON s.id=r.session_id LEFT JOIN courts c ON c.id=s.court_id WHERE s.rate_key IS NOT NULL ORDER BY r.created_at DESC,r.id DESC")->fetchAll();
}
