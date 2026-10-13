// Opt-in integration test against local Apache/MySQL. Creates and removes isolated fixtures.
const assert=require('node:assert/strict');
const {spawn,spawnSync}=require('node:child_process');
const path=require('node:path');
if (process.env.RUN_DATABASE_CONCURRENCY_TESTS!=='1') {
    console.log('Concurrency integration test skipped; set RUN_DATABASE_CONCURRENCY_TESTS=1 to run locally.');
} else run().catch(error=>{console.error(error);process.exitCode=1;});

async function run() {
    const php=process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
    const script=path.join(__dirname,'concurrency-fixtures.php');
    const base=process.env.TEST_APP_URL || 'http://localhost/multisportspass';
    const taken='This slot is already taken. Please select another available slot.';
    function fixture(mode,data={}) {
        const result=spawnSync(php,[script,mode],{input:JSON.stringify(data)+'\n',encoding:'utf8'});
        assert.equal(result.status,0,result.stderr || result.stdout);
        return result.stdout ? JSON.parse(result.stdout) : null;
    }
    const f=fixture('init');
    const clients=[];
    function client(){const c={cookie:''};clients.push(c);return c;}
    async function request(c,url,data) {
        const res=await fetch(base+url,{method:data?'POST':'GET',headers:{Cookie:c.cookie,...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined,redirect:'manual'});
        for(const cookie of res.headers.getSetCookie()) if(cookie.startsWith('PHPSESSID=')) c.cookie=cookie.split(';')[0];
        const text=await res.text();
        return {status:res.status,text,json:res.headers.get('content-type')?.includes('application/json') ? JSON.parse(text) : null};
    }
    const login=(c,account)=>request(c,'/ui/login.php',{email:account.email,password:f.password});
    const post=(c,action,data)=>request(c,'/api.php?action='+action,data);
    function success(r){assert.equal(r.status,200,r.text.slice(0,600));assert.equal(r.json.ok,true,r.json.message);}
    function conflict(r){assert.equal(r.status,409,r.text.slice(0,600));assert.equal(r.json.message,taken);assert.equal(r.json.availabilityChanged,true);assert.ok(r.json.state);}
    async function race(jobs) {
        const child=spawn(php,[script,'lock'],{stdio:['pipe','pipe','pipe']});
        child.stdin.write(JSON.stringify(f)+'\n');
        await new Promise((resolve,reject)=>{child.stdout.once('data',resolve);child.once('error',reject);});
        const requests=jobs.map(job=>job());
        // Both HTTP requests reach the locked court before either can save.
        await new Promise(resolve=>setTimeout(resolve,700));
        child.stdin.end('release\n');
        const responses=await Promise.all(requests);
        assert.equal(responses.filter(r=>r.json?.ok).length,1,JSON.stringify(responses.map(r=>({status:r.status,message:r.json?.message,text:r.json?undefined:r.text.slice(0,200)}))));
        conflict(responses.find(r=>!r.json?.ok));
        return responses;
    }
    try {
        const a=client(),b=client();
        const logins=await Promise.all([login(a,f.members[0]),login(b,f.members[0])]);
        assert.deepEqual(logins.map(r=>r.status),[302,302]);
        const players=[a,client(),client(),client()];
        for(let i=1;i<4;i++) assert.equal((await login(players[i],f.members[i])).status,302);
        const admins=[client(),client()];
        for(let i=0;i<2;i++) assert.equal((await login(admins[i],f.admins[i])).status,302);
        const secondAdmin=client();
        assert.equal((await login(secondAdmin,f.admins[0])).status,302);
        await request(admins[0],'/admin/logout.php');
        assert.equal((await login(secondAdmin,f.admins[0])).status,302);
        admins[0]=secondAdmin;
        fixture('expire',f);
        const renewed=client();assert.equal((await login(renewed,f.members[3])).status,302);
        assert.equal((await post(players[3],'openplay',{sessionId:f.legacy,name:'Test',phone:'09171234567',paymentMethod:f.payment})).status,401);
        players[3]=renewed;
        console.log('Concurrent account login, admin logout/relogin, timeout and stale-session rejection passed.');

        const regular={date:f.date,time:f.slots[0].label,court:f.court,sport:'Pickleball',paymentMethod:f.payment};
        await race([()=>post(players[0],'book',regular),()=>post(players[1],'book',regular)]);
        const adminBooking={date:f.date,timeSlotId:f.slots[1].id,courtId:f.court,sport:'Pickleball',name:'Test',phone:'09171234567',status:'Booked',overrideReason:'Concurrency test'};
        await race([()=>post(admins[0],'admin-override-booking',adminBooking),()=>post(players[1],'book',{...regular,time:f.slots[1].label})]);
        const stats=fixture('stats',f);
        const held=stats.regular.find(r=>r.status==='Held');
        const confirmations=await Promise.all(admins.map(c=>post(c,'admin-status',{id:'court:'+held.id,status:'Booked'})));
        assert.equal(confirmations.filter(r=>r.json?.ok).length,1);conflict(confirmations.find(r=>!r.json?.ok));
        const override={...adminBooking,timeSlotId:f.slots[0].id};
        const prompt=await post(admins[0],'admin-override-booking',override);
        assert.equal(prompt.json.requiresOverride,true);
        const old=fixture('stats',f).regular.find(r=>Number(r.time_slot_id)===Number(f.slots[0].id)&&r.status!=='Cancelled');
        success(await post(admins[0],'admin-status',{id:'court:'+old.id,status:'Cancelled',reason:'Concurrency test'}));
        success(await post(players[2],'book',regular));
        conflict(await post(admins[0],'admin-override-booking',{...override,overrideConfirm:'1',conflictVersion:prompt.json.conflictVersion}));
        const freshPrompt=await post(admins[0],'admin-override-booking',override);
        success(await post(admins[0],'admin-override-booking',{...override,overrideConfirm:'1',conflictVersion:freshPrompt.json.conflictVersion}));
        console.log('Regular bookings: player/player, admin/player and simultaneous confirmation races passed.');

        // Free the fixture court, then create a single three-hour Open Play session.
        for(const row of fixture('stats',f).regular.filter(r=>r.status!=='Cancelled')) success(await post(admins[0],'admin-status',{id:'court:'+row.id,status:'Cancelled',reason:'Concurrency test'}));
        const created=await post(admins[0],'admin-openplay-session',{date:f.date,courtId:f.court,sport:'Pickleball',slotIds:JSON.stringify(f.slots.map(s=>Number(s.id))),maxPlayers:2,ratePerPlayer:150});success(created);
        const offers=await request(players[0],'/api.php?action=openplay-offers&date='+f.date);
        const offer=offers.json.offers.find(o=>o.sessionId===created.json.sessionId);
        assert.ok(offer);
        const op={date:f.date,rateKey:offer.key,seats:'[1]',paymentMethod:f.payment};
        const results=await race([()=>post(players[0],'openplay-session-book',op),()=>post(players[1],'openplay-session-book',op)]);
        const winner=results[0].json.ok?players[0]:players[1];
        conflict(await post(winner,'openplay-session-book',{...op,seats:'[2]'}));
        assert.equal((await post(players[2],'openplay-session-book',{...op,seats:'[1,2]'})).status,422);
        const joined=(await request(winner,'/api.php?action=openplay-offers&date='+f.date)).json.offers.find(o=>o.key===offer.key);
        assert.equal(joined.memberBooked,true);
        await race([()=>post(players[2],'openplay-session-book',{...op,seats:'[2]'}),()=>post(players[3],'openplay-session-book',{...op,seats:'[2]'})]);
        assert.equal(fixture('stats',f).openplay.filter(r=>Number(r.session_id)===offer.sessionId&&r.status!=='Cancelled').length,2);
        conflict(await post(players[2],'book',regular));
        const reservation=fixture('stats',f).openplay.find(r=>Number(r.session_id)===offer.sessionId);
        const opConfirm=await Promise.all(admins.map(c=>post(c,'admin-status',{id:'openplay:'+reservation.id,status:'Booked'})));
        assert.equal(opConfirm.filter(r=>r.json?.ok).length,1);conflict(opConfirm.find(r=>!r.json?.ok));
        success(await post(admins[0],'admin-status',{id:'openplay:'+reservation.id,status:'Cancelled',reason:'Concurrency test'}));
        const releasedPlayer=players[f.members.findIndex(m=>m.id===Number(reservation.member_id))];
        success(await post(releasedPlayer,'openplay-session-book',{...op,seats:JSON.stringify([Number(reservation.seat_number)])}));
        for (const row of fixture('stats',f).openplay.filter(r=>Number(r.session_id)===offer.sessionId&&r.status!=='Cancelled')) {
            success(await post(admins[0],'admin-status',{id:'openplay:'+row.id,status:'Cancelled',reason:'Concurrency test'}));
        }
        await race([()=>post(players[0],'openplay-session-book',op),()=>post(players[0],'openplay-session-book',{...op,seats:'[2]'})]);
        console.log('Open Play: same seat, per-member limit, final capacity, confirmation, cancellation/rejoin and underlying-hour protection passed.');

        const legacy={sessionId:f.legacy,name:'Test',phone:'09171234567',paymentMethod:f.payment};
        success(await post(players[0],'openplay',legacy));conflict(await post(players[0],'openplay',legacy));
        const finalLegacy=await Promise.all([post(players[1],'openplay',legacy),post(players[2],'openplay',legacy)]);
        assert.equal(finalLegacy.filter(r=>r.json?.ok).length,1);conflict(finalLegacy.find(r=>!r.json?.ok));
        console.log('Legacy Open Play: member uniqueness and final capacity race passed.');
    } finally {
        await Promise.allSettled(clients.map(c=>request(c,'/admin/logout.php')));
        fixture('cleanup',f);
    }
}
