/* Requires Playwright for development only; does not touch production storage. */
const {chromium}=require('playwright');
const {mkdtempSync,writeFileSync,rmSync}=require('node:fs');
const {tmpdir}=require('node:os');
const {join,resolve}=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const assert=require('node:assert/strict');
const net=require('node:net');
(async()=>{
 const root=resolve(__dirname,'..'),privateDir=mkdtempSync(join(tmpdir(),'hay-browser-'));
 const env={...process.env,HAY_STORAGE:privateDir},php=process.env.PHP_BIN||'php';
 const setup=spawnSync(php,['bin/admin.php','init','admin'],{cwd:root,env,input:'Browser-test-password-42',encoding:'utf8'});
 assert.equal(setup.status,0,setup.stderr);
 // UI fixture only: these are synthetic metrics, not a claim about Windows collection.
 const now=Math.floor(Date.now()/1000);
 writeFileSync(join(privateDir,'snapshot.json'),JSON.stringify({timestamp:now,durationMs:120,system:{status:'healthy',computerName:'TEST-ACER',os:{caption:'Windows test fixture',version:'test'},cpu:27,ram:{used:6e9,total:12e9,percent:50},battery:{percent:72,status:2},disks:[{drive:'C:',size:100e9,free:25e9}],uptimeSeconds:3600,processCount:140,errors:[]},services:{},apache80:{status:'unavailable',detail:'Fixture'},apache8074:{status:'unreachable',detail:'Fixture'},mysql:{status:'unavailable',detail:'Fixture'},history:[{timestamp:now-30,cpu:20,ram:45},{timestamp:now-15,cpu:24,ram:48},{timestamp:now,cpu:27,ram:50}],alerts:[]}));
 const reservation=net.createServer();await new Promise(r=>reservation.listen(0,'127.0.0.1',r));const port=reservation.address().port;await new Promise(r=>reservation.close(r));
 const server=spawn(php,['-d','session.save_path='+privateDir,'-S','127.0.0.1:'+port,'-t',join(root,'public')],{cwd:root,env,stdio:'ignore'});
 let browser;
 try{
  for(let i=0;i<50;i++){try{await fetch('http://127.0.0.1:'+port);break;}catch{await new Promise(r=>setTimeout(r,100));}}
  browser=await chromium.launch({executablePath:process.env.CHROMIUM_BIN||'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
  const page=await browser.newPage({viewport:{width:1366,height:1000}}),errors=[],external=[];
  page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(!r.url().startsWith('http://127.0.0.1:'+port))external.push(r.url());});
  await page.goto('http://127.0.0.1:'+port);
  await page.locator('#loginForm [name=username]').fill('admin');await page.locator('#loginForm [name=password]').fill('Browser-test-password-42');
  await page.locator('#loginForm button').click();await page.locator('#dashboard').waitFor({state:'visible'});
  await page.getByRole('heading',{name:'TEST-ACER',exact:true}).waitFor();
  assert.match(await page.locator('#stats').innerText(),/27\.0%/);
  await page.locator('#add').click();await page.locator('#serviceForm [name=name]').fill('<img src=x onerror=window.pwned=true>');await page.locator('#serviceForm [name=port]').fill('12345');await page.locator('#serviceForm button.primary').click();await page.locator('#serviceDialog').waitFor({state:'hidden'});
  const row=page.locator('#services tr').filter({hasText:'12345'});await row.waitFor();assert.match(await row.innerText(),/<img src=x onerror=window.pwned=true>/);assert.equal(await page.evaluate(()=>window.pwned),undefined);assert.equal(await row.locator('img').count(),0);
  await row.getByRole('button',{name:'Edit',exact:true}).click();await page.locator('#serviceForm [name=name]').fill('Inventory API');await page.locator('#serviceForm [name=type]').selectOption('http');assert.equal(await page.locator('#httpFields').isVisible(),true);await page.locator('#serviceForm [name=enabled]').uncheck();await page.locator('#serviceForm button.primary').click();await page.locator('#serviceDialog').waitFor({state:'hidden'});await page.getByText('Inventory API',{exact:true}).waitFor();
  await page.locator('#theme').click();assert.equal(await page.locator('html').getAttribute('data-theme'),'light');
  await page.locator('details summary').click();await page.locator('#loadAudit').click();await page.locator('#audit li').first().waitFor();
  await page.locator('#checkNow').click();await page.getByText(/Permintaan pemeriksaan dikirim/).waitFor();
  await page.setViewportSize({width:390,height:844});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth),true);
  page.once('dialog',d=>d.accept());await row.getByRole('button',{name:'Hapus',exact:true}).click();await page.getByText('Inventory API',{exact:true}).waitFor({state:'hidden'});
  await page.locator('#logout').click();await page.locator('#login').waitFor({state:'visible'});
  assert.deepEqual(errors,[]);assert.deepEqual(external,[]);
  console.log('PASS browser: login, metrics/graph, safe text/XSS, CRUD, HTTP form, theme, audit, check request, mobile width, logout; no external requests or page errors.');
 }finally{if(browser)await browser.close();server.kill();await new Promise(r=>server.once('exit',r));rmSync(privateDir,{recursive:true,force:true});}
})().catch(e=>{console.error(e);process.exitCode=1;});
