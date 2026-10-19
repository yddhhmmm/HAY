// Setup form / Origin validation in a real browser; private temporary storage only.
const {chromium}=require('playwright');
const {mkdtempSync,rmSync,readFileSync}=require('node:fs');
const {spawn}=require('node:child_process');
const {join,resolve}=require('node:path');
const {tmpdir}=require('node:os');
const assert=require('node:assert/strict');
(async()=>{
 const storage=mkdtempSync(join(tmpdir(),'hay-setup-browser-'));
 const child=spawn(process.env.PHP_BIN||'php',['-n',resolve(__dirname,'../bin/setup-console.php')],{env:{...process.env,HAY_STORAGE:storage},stdio:['ignore','pipe','pipe']});
 let browser,output='';
 try {
  const exited=new Promise(resolve=>child.once('exit',code=>resolve(code)));
  const url=await new Promise((resolve,reject)=>{
   const timer=setTimeout(()=>reject(new Error('Setup did not start')),10000);
   child.stdout.on('data',data=>{output+=data.toString();const match=output.match(/http:\/\/127\.0\.0\.1:\d+\/\?token=[a-f0-9]{64}/);if(match){clearTimeout(timer);resolve(match[0]);}});
   child.once('error',error=>{clearTimeout(timer);reject(error);});
  });
  browser=await chromium.launch({executablePath:process.env.CHROMIUM_BIN||'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
  const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(url);
  await page.locator('[name=username]').fill('browseradmin');
  await page.locator('[name=password]').fill('Browser-wizard-password-42');
  await page.locator('[name=confirm]').fill('Browser-wizard-password-42');
  const [response]=await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'),page.getByRole('button',{name:'Simpan akun'}).click()]);
  assert.equal(response.status(),200,'Setup POST gagal: '+await response.text());
  await page.getByRole('heading',{name:'Akun berhasil disimpan',exact:true}).waitFor();
  assert.equal(await exited,0);
  assert.equal(JSON.parse(readFileSync(join(storage,'auth.json'))).username,'browseradmin');
  assert.equal(output.includes('Browser-wizard-password-42'),false);
  assert.deepEqual(errors,[]);
  console.log('PASS setup browser: masked form, same-origin POST accepted, account saved, server shutdown, no password in console.');
 }finally{if(browser)await browser.close();if(child.exitCode===null){child.kill();await new Promise(resolve=>child.once('exit',resolve));}rmSync(storage,{recursive:true,force:true});}
})().catch(error=>{console.error(error);process.exitCode=1;});
