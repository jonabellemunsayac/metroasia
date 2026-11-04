const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
if(process.env.RUN_DATABASE_CONCURRENCY_TESTS==='1') run().catch(e=>{console.error(e);process.exitCode=1;});
else console.log('Set RUN_DATABASE_CONCURRENCY_TESTS=1 to run.');
async function run(){
 const base=process.env.TEST_APP_URL||'http://localhost/multisportspass';
 function fixture(mode,data={}){const r=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[path.join(__dirname,'concurrency-fixtures.php'),mode],{input:JSON.stringify(data)+'\n',encoding:'utf8'});assert.equal(r.status,0,r.stderr);return r.stdout?JSON.parse(r.stdout):null;}
 const f=fixture('init'), clients=[];
 function client(){const c={cookie:''};clients.push(c);return c;}
 async function request(c,url,data){const r=await fetch(base+url,{method:data?'POST':'GET',headers:{Cookie:c.cookie,...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined,redirect:'manual'});for(const cookie of r.headers.getSetCookie())if(cookie.startsWith('PHPSESSID='))c.cookie=cookie.split(';')[0];return {status:r.status,text:await r.text()};}
 const login=(c,a)=>request(c,'/login.php',{email:a.email,password:f.password});
 const status=c=>request(c,'/api.php?action=session-status');
 try{
  for(const [kind,account,page] of [['player',f.members[0],'/ui/member.php'],['admin',f.admins[0],'/admin/dashboard.php']]){
   const a=client(),b=client();assert.equal((await login(a,account)).status,302);assert.equal((await status(a)).status,200);
   assert.equal((await login(b,account)).status,302);
   const stale=await request(a,'/api.php?action=book',{date:'invalid'});assert.equal(stale.status,401);assert.equal(JSON.parse(stale.text).message,'Your account has been logged out because it was accessed from another device.');
   for(const action of ['admin-status','admin-payment-channel','admin-receipt-upload','openplay'])assert.equal((await request(a,'/api.php?action='+action,{id:'1'})).status,401);
   assert.equal((await request(a,page)).status,302);
   assert.match((await request(a,'/login.php')).text,/Your account has been logged out because it was accessed from another device\./);
   await request(a,'/admin/logout.php');assert.equal((await status(b)).status,200);
   assert.equal((await request(b,page)).status,200);assert.equal((await request(b,page)).status,200);
   const racers=Array.from({length:5},client);assert.ok((await Promise.all(racers.map(c=>login(c,account)))).every(r=>r.status===302));
   const results=await Promise.all(racers.map(status));assert.equal(results.filter(r=>r.status===200).length,1);assert.equal((await status(b)).status,401);
   const winner=racers[results.findIndex(r=>r.status===200)];await request(winner,'/admin/logout.php');assert.equal((await status(winner)).status,401);
   console.log(kind+': takeover, denied mutations, notification, refresh, stale logout, five simultaneous logins passed.');
  }
  const c=client();await login(c,f.members[3]);fixture('expire',f);assert.equal((await status(c)).status,401);
 }finally{await Promise.allSettled(clients.map(c=>request(c,'/admin/logout.php')));fixture('cleanup',f);}
}
