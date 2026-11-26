const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const path = require('node:path');
if (process.env.RUN_BOOKING_CANCELLATION_TEST !== '1') {
    console.log('Set RUN_BOOKING_CANCELLATION_TEST=1 to test against local Apache/MySQL.');
} else run().catch(error => { console.error(error); process.exitCode = 1; });
async function run() {
    const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
    function fixture(mode, data = {}) {
        const r = spawnSync(php, [path.join(__dirname, 'concurrency-fixtures.php'), mode], { input: JSON.stringify(data) + '\n', encoding: 'utf8' });
        assert.equal(r.status, 0, r.stderr);
        return r.stdout ? JSON.parse(r.stdout) : null;
    }
    function audit(f) {
        const r = spawnSync(php, ['-r', `require 'config/database.php'; $f=json_decode(stream_get_contents(STDIN),true); $q=db()->prepare('SELECT id,status,cancel_reason,cancelled_by,cancelled_at FROM court_bookings WHERE court_id=? ORDER BY id'); $q->execute([$f['court']]); echo json_encode($q->fetchAll());`], {cwd:path.join(__dirname,'..'), input:JSON.stringify(f),encoding:'utf8'});
        assert.equal(r.status,0,r.stderr); return JSON.parse(r.stdout);
    }
    const f = fixture('init');
    let cookie = '';
    async function request(url, data) {
        const r = await fetch((process.env.TEST_APP_URL || 'http://localhost/multisportspass') + url, {method:data?'POST':'GET',redirect:'manual',headers:{Cookie:cookie,...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});
        for(const c of r.headers.getSetCookie()) if(c.startsWith('PHPSESSID=')) cookie=c.split(';')[0];
        const text=await r.text(); return {status:r.status,body:r.headers.get('content-type')?.includes('application/json')?JSON.parse(text):text};
    }
    const post = (action,data) => request('/api.php?action='+action+'&stateContext=bookings',data);
    const success = r => { assert.equal(r.status,200,JSON.stringify(r.body)); assert.equal(r.body.ok,true); };
    try {
        assert.equal((await request('/ui/login.php',{email:f.admins[0].email,password:f.password})).status,302);
        for(const [index,slot] of f.slots.slice(0,3).entries()) {
            success(await post('admin-override-booking',{date:f.date,timeSlotId:slot.id,courtId:f.court,sport:'Pickleball',name:f.tag,phone:'09171234567',status:index===1?'Booked':'Held',overrideReason:'Cancellation regression fixture'}));
        }
        const rows=audit(f); assert.equal(rows.length,3);
        success(await post('admin-status',{id:'court:'+rows[2].id,status:'Cancelled',reason:'Original reason'}));
        const original=audit(f)[2];
        const id='court:'+rows.map(row=>row.id).join(',');
        assert.equal((await post('admin-status',{id,status:'Cancelled',reason:' '})).status,422);
        success(await post('admin-status',{id,status:'Cancelled',reason:'Group cancellation'}));
        const cancelled=audit(f);
        assert.ok(cancelled.every(row=>row.status==='Cancelled'));
        assert.deepEqual(cancelled[2],original,'Previous cancellation audit must remain unchanged');
        assert.ok(cancelled.slice(0,2).every(row=>row.cancel_reason==='Group cancellation' && Number(row.cancelled_by)===Number(f.admins[0].id)));
        success(await post('admin-status',{id,status:'Cancelled',reason:'Repeated request'}));
        assert.deepEqual(audit(f),cancelled,'Repeated cancellation must not rewrite audit data');
        console.log('PASS: mixed Held/Booked/Cancelled group, reason validation, audit preservation, repeat cancellation.');
    } finally { fixture('cleanup',f); }
}
