const {spawnSync}=require('child_process');const assert=require('assert/strict');
if (process.env.RUN_PAGE_CONTENT_TEST !== '1') { console.log('Set RUN_PAGE_CONTENT_TEST=1 to test against local Apache/MySQL.'); process.exit(0); }
const base=process.env.TEST_APP_URL || 'http://localhost/multisportspass';
function fixture(mode,data={}){let r=spawnSync('php',['tests/admin-navigation-fixtures.php',mode],{input:JSON.stringify(data),encoding:'utf8'});assert.equal(r.status,0,r.stderr);return r.stdout?JSON.parse(r.stdout):null;}
(async()=>{const account=fixture('init');let cookie='',csrf='',faqIds=[],policyIds=[];const stamp='content-test-'+Date.now();
async function req(path,data){let r=await fetch(base+path,{method:data?'POST':'GET',redirect:'manual',headers:{Cookie:cookie,...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie())if(c.startsWith('PHPSESSID='))cookie=c.split(';')[0];const html=await r.text();assert.ok(!/Fatal error|Warning:|SQLSTATE/.test(html),html.slice(0,500));return {r,html};}
try{
assert.equal((await req('/ui/login.php',{email:account.email,password:account.password})).r.status,302);
let page=await req('/admin/faqs.php');assert.equal(page.r.status,200);csrf=page.html.match(/name="csrf" value="([^"]+)"/)[1];assert.match(page.html,/<details class="admin-quick-links" open>/);
for(let n=0;n<2;n++){assert.equal((await req('/admin/faqs.php',{csrf,id:0,action:'save',question:stamp+n,contentHtml:'<h2>Answer</h2><p><b>Bold</b></p><ol><li>Step</li></ol><a href="javascript:alert(1)">unsafe</a>'})).r.status,302);}
page=await req('/admin/faqs.php');for(const n of [0,1]){let pos=page.html.indexOf(stamp+n);faqIds.push(page.html.slice(pos).match(/href="\?edit=(\d+)"/)[1]);}
assert.equal((await req('/admin/faqs.php',{csrf,id:faqIds[1],action:'up'})).r.status,302);
let pub=await req('/ui/faqs.php');assert.ok(pub.html.indexOf(stamp+1)<pub.html.indexOf(stamp+0));assert.ok(!pub.html.includes('javascript:alert'));
await req('/admin/faqs.php',{csrf,id:faqIds[0],action:'save',question:stamp+'edited',contentHtml:'<p>Updated answer</p>'});assert.match((await req('/ui/faqs.php')).html,/Updated answer/);
let invalid=await req('/admin/faqs.php',{csrf:'invalid',id:faqIds[0],action:'delete'});assert.match(invalid.html,/session expired/);
for(const name of ['rules','terms-conditions','data-privacy']){let p=await req('/admin/'+name+'.php');assert.equal(p.r.status,200);let saved=await req('/admin/'+name+'.php',{csrf,id:0,action:'save',title:stamp,version:stamp,status:'Draft',contentHtml:'<h2>Heading</h2><p style="font-family:serif" onclick="alert(1)">Text</p><ul><li>Bullet</li></ul>'});let id=saved.html.match(/name="id" value="(\d+)"/)[1];assert.notEqual(id,'0');policyIds.push([name,id]);assert.ok(!saved.html.includes('onclick="alert(1)'));}
for(const path of ['rules','terms-and-condition','privacy-policy','faqs'])assert.equal((await req('/ui/'+path+'.php')).r.status,200);
assert.match((await req('/ui/rules.php')).html,/Strictly no refunds/);
console.log('PASS: authenticated page management, FAQ add/edit/reorder, CSRF rejection, sanitization, public pages, Quick Links.');
}finally{for(const id of faqIds)await req('/admin/faqs.php',{csrf,id,action:'delete'});for(const [name,id]of policyIds)await req('/admin/'+name+'.php',{csrf,id,action:'delete'});fixture('cleanup',account);}
})().catch(e=>{console.error(e);process.exitCode=1;});
