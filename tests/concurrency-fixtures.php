<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/../includes/auth.php';
$pdo=db();
ensure_account_sessions($pdo);
$mode=$argv[1] ?? '';
$input=json_decode(trim((string)fgets(STDIN)),true) ?: [];
if ($mode==='init') {
    $tag='concurrency_'.bin2hex(random_bytes(6));
    $password=bin2hex(random_bytes(16));
    $hash=password_hash($password,PASSWORD_DEFAULT);
    $result=['tag'=>$tag,'password'=>$password,'members'=>[],'admins'=>[]];
    $pdo->beginTransaction();
    try {
        foreach (['members'=>4,'admins'=>2] as $type=>$count) {
            for ($i=0;$i<$count;$i++) {
                $email=$tag.'_'.$type.$i.'@example.invalid';
                $sql=$type==='members'
                    ? "INSERT INTO members (name,email,phone,password_hash) VALUES (?,?,'09171234567',?)"
                    : "INSERT INTO admin_users (name,email,password_hash,role) VALUES (?,?,?,'super_admin')";
                $pdo->prepare($sql)->execute([$tag,$email,$hash]);
                $result[$type][]=['id'=>(int)$pdo->lastInsertId(),'email'=>$email];
            }
        }
        $pdo->prepare("INSERT INTO courts (display_number,name,supported_sports) VALUES (999,?,'Pickleball,Badminton,Basketball')")->execute([$tag]);
        $result['court']=(int)$pdo->lastInsertId();
        $result['slots']=$pdo->query("SELECT id,label,starts_at,ends_at FROM time_slots WHERE starts_at>='09:00:00' AND starts_at<'12:00:00' ORDER BY starts_at")->fetchAll();
        $result['payment']=$pdo->query('SELECT code FROM payment_channels WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
        $result['date']=date('Y-m-d',strtotime('+1 day'));
        $pdo->prepare("INSERT INTO open_play_sessions (title,session_date,session_time,price,capacity,level_label,description) VALUES (?,?,'9 AM - 12 PM',150,2,'All levels','Test fixture')")->execute([$tag,$result['date']]);
        $result['legacy']=(int)$pdo->lastInsertId();
        $pdo->commit();
        echo json_encode($result);
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
} elseif ($mode==='lock') {
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT id FROM courts WHERE id=? FOR UPDATE');
    $q->execute([(int)$input['court']]);
    echo "locked\n"; flush();
    fgets(STDIN);
    $pdo->rollBack();
} elseif ($mode==='stats') {
    $q=$pdo->prepare('SELECT id,status,member_id,booking_date,time_slot_id FROM court_bookings WHERE court_id=?');
    $q->execute([$input['court']]); $result=['regular'=>$q->fetchAll()];
    $q=$pdo->prepare('SELECT r.id,r.status,r.member_id,r.seat_number,r.session_id FROM open_play_reservations r JOIN open_play_sessions s ON s.id=r.session_id WHERE s.court_id=? OR s.id=?');
    $q->execute([$input['court'],$input['legacy']]); $result['openplay']=$q->fetchAll();
    echo json_encode($result);
} elseif ($mode==='expire') {
    $pdo->prepare("UPDATE account_sessions SET last_seen=? WHERE account_type='member' AND account_id=?")->execute([time()-ACCESS_SESSION_TIMEOUT_SECONDS-10,$input['members'][3]['id']]);
} elseif ($mode==='cleanup') {
    $pdo->beginTransaction();
    $q=$pdo->prepare('DELETE r FROM open_play_reservations r JOIN open_play_sessions s ON s.id=r.session_id WHERE s.court_id=? OR s.id=?');
    $q->execute([$input['court'],$input['legacy']]);
    $pdo->prepare('DELETE FROM open_play_sessions WHERE court_id=? OR id=?')->execute([$input['court'],$input['legacy']]);
    $pdo->prepare('DELETE FROM court_bookings WHERE court_id=?')->execute([$input['court']]);
    $pdo->prepare('DELETE FROM court_blocks WHERE court_id=?')->execute([$input['court']]);
    $pdo->prepare('DELETE FROM courts WHERE id=? AND name=?')->execute([$input['court'],$input['tag']]);
    foreach (['members'=>'member','admins'=>'admin'] as $key=>$type) foreach ($input[$key] as $account) {
        $pdo->prepare('DELETE FROM account_sessions WHERE account_type=? AND account_id=?')->execute([$type,$account['id']]);
        $pdo->prepare('DELETE FROM access_logs WHERE account_type=? AND account_id=?')->execute([$type,$account['id']]);
        if ($type==='admin') $pdo->prepare('DELETE FROM override_logs WHERE admin_id=?')->execute([$account['id']]);
        $table=$type==='member' ? 'members' : 'admin_users';
        $pdo->prepare("DELETE FROM $table WHERE id=? AND email=?")->execute([$account['id'],$account['email']]);
    }
    $pdo->commit();
}
