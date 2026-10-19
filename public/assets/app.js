'use strict';
const $ = id => document.getElementById(id);
let csrf = '', loggedIn = false, isAdmin = false, currentServices = [], latest = null, inFlight = false;
const statusLabels = {healthy:'Sehat',degraded:'Bermasalah',unreachable:'Tidak terjangkau',unavailable:'Tidak tersedia',disabled:'Dinonaktifkan',pending:'Menunggu pemeriksaan',partial:'Sebagian tersedia'};
const text = (tag, value, className) => {const el=document.createElement(tag);el.textContent=value;if(className)el.className=className;return el;};
function notice(message='') {$('notice').textContent=message;$('notice').hidden=!message;}
function formatBytes(value) {if(value===null||value===undefined||!Number.isFinite(Number(value)))return 'Tidak tersedia';return (Number(value)/1073741824).toFixed(1)+' GiB';}
function percent(value) {return value===null||value===undefined?'Tidak tersedia':Number(value).toFixed(1)+'%';}
function uptime(s) {if(s===null||s===undefined)return 'Tidak tersedia';return `${Math.floor(s/86400)} hari ${Math.floor(s%86400/3600)} jam ${Math.floor(s%3600/60)} menit`;}
function badge(status) {return text('span',statusLabels[status]||'Belum diperiksa','badge '+status);}
async function api(action, body) {
  const response=await fetch('api.php?action='+encodeURIComponent(action),{credentials:'same-origin',cache:'no-store',headers:body?{'Content-Type':'application/json','X-CSRF-Token':csrf}:{},method:body?'POST':'GET',body:body?JSON.stringify(body):undefined,signal:AbortSignal.timeout(10000)});
  const data=await response.json();
  if(!response.ok) {if(response.status===401 && action!=='login'){loggedIn=false;showAuth();}throw new Error(data.error||'Permintaan gagal.');}
  return data;
}
function showAuth() {$('login').hidden=loggedIn;$('dashboard').hidden=!loggedIn;$('logout').hidden=!loggedIn;if(!loggedIn)$('serviceDialog').close();}
async function session() {const s=await api('session');csrf=s.csrf;loggedIn=!!s.user;isAdmin=s.role==='admin';$('add').hidden=!isAdmin;$('checkNow').hidden=!isAdmin;$('loadAudit').hidden=!isAdmin;$('setupHint').hidden=s.configured;showAuth();}
function addInfo(container,label,value) {container.append(text('dt',label),text('dd',value));}
function render(data) {
  latest=data;currentServices=data.services||[]; const snap=data.snapshot;
  $('services').replaceChildren();
  for(const service of currentServices) {
    const r=!service.enabled?{status:'disabled',detail:'Pemeriksaan dinonaktifkan'}:snap?.services?.[service.id]||{status:'pending',detail:'Menunggu siklus collector berikutnya'};
    const tr=document.createElement('tr');
    const name=document.createElement('td');name.append(text('strong',service.name),text('small','localhost:'+service.port));
    const type=text('td',service.type.toUpperCase()+(service.type==='http'?' '+service.path+' → '+service.expected:''));
    const state=document.createElement('td');state.append(badge(r.status),text('small',r.detail));
    const latency=text('td',r.latency===null||r.latency===undefined?'—':r.latency+' ms');
    const actions=document.createElement('td');const edit=text('button','Edit');edit.addEventListener('click',()=>openForm(service));
    const del=text('button','Hapus','danger');del.addEventListener('click',async()=>{
      if(!confirm('Hapus layanan '+service.name+'?'))return;del.disabled=true;
      try{await api('delete',{id:service.id});notice('Layanan dihapus.');await refresh();}catch(e){notice(e.message);}finally{del.disabled=false;}
    });if(isAdmin)actions.append(edit,del);else actions.textContent='Hanya lihat';tr.append(name,type,state,latency,actions);$('services').append(tr);
  }
  if(!currentServices.length){const tr=document.createElement('tr');const td=text('td','Belum ada layanan. Tambahkan layanan pertama Anda.');td.colSpan=5;tr.append(td);$('services').append(tr);}
  const age=snap?Math.max(0,data.serverTime-snap.timestamp):null;
  $('freshness').textContent=!snap?'Belum ada data':data.stale?'Data lama':'Data terbaru';$('freshness').className='badge '+(!snap||data.stale?'degraded':'healthy');
  $('updated').textContent=snap?'Diperbarui '+new Date(snap.timestamp*1000).toLocaleString('id-ID')+' · '+age+' detik lalu':'Jalankan collector untuk mulai memantau.';
  if(!snap){$('stats').replaceChildren();$('disks').replaceChildren();$('info').replaceChildren();$('coreServices').replaceChildren();$('errors').replaceChildren(text('li','Collector belum menghasilkan data.'));return;}
  $('alerts').replaceChildren();for(const alert of snap.alerts||[])$('alerts').append(text('p',alert,'alert'));
  const sys=snap.system||{}; $('computer').textContent=sys.computerName||'Komputer Windows';$('system').textContent=sys.os?sys.os.caption+' · '+sys.os.version:'Informasi Windows tidak tersedia';
  const disks=Array.isArray(sys.disks)?sys.disks:[];
  const total=disks.reduce((n,d)=>n+d.size,0),used=disks.reduce((n,d)=>n+d.size-d.free,0);
  const cards=[['CPU',percent(sys.cpu),'Beban prosesor'],['RAM',percent(sys.ram?.percent),sys.ram?formatBytes(sys.ram.used)+' / '+formatBytes(sys.ram.total):'Pembacaan tidak tersedia'],['Disk',total?percent(used/total*100):'Tidak tersedia',total?formatBytes(used)+' / '+formatBytes(total):'Tidak ada pembacaan'],['Baterai',percent(sys.battery?.percent),batteryState(sys.battery?.status)]];
  $('stats').replaceChildren();for(const [label,value,detail] of cards){const card=text('article','','panel stat');card.append(text('p',label,'eyebrow'),text('h2',value),text('p',detail));$('stats').append(card);}
  $('disks').replaceChildren();for(const d of disks){const p=d.size>0?(d.size-d.free)/d.size*100:0;const row=text('div','','disk');row.append(text('strong',d.drive+' · '+percent(p)),text('small',formatBytes(d.size-d.free)+' terpakai / '+formatBytes(d.size)));const meter=document.createElement('meter');meter.min=0;meter.max=100;meter.low=50;meter.high=80;meter.optimum=0;meter.value=p;meter.setAttribute('aria-label','Penggunaan '+d.drive);row.append(meter);$('disks').append(row);}if(!disks.length)$('disks').append(text('p','Data disk tidak tersedia.'));
  $('info').replaceChildren();addInfo($('info'),'Uptime Windows',uptime(sys.uptimeSeconds));addInfo($('info'),'Jumlah proses',sys.processCount??'Tidak tersedia');addInfo($('info'),'Waktu pengumpulan',snap.durationMs+' ms');
  $('coreServices').replaceChildren();
  for(const [title,r] of [['Apache · 80',snap.apache80],['Apache · 8074',snap.apache8074],['MySQL · 3306',snap.mysql]]){
    const card=text('article','','panel');card.append(text('h3',title),badge(r.status),text('p',r.detail));
    if(r.metrics){const dl=document.createElement('dl');for(const [key,val] of Object.entries(r.metrics))addInfo(dl,metricLabel(key),formatMetric(key,val));card.append(dl);}$('coreServices').append(card);
  }
  $('errors').replaceChildren();for(const error of sys.errors||[])$('errors').append(text('li',error));if(!sys.errors?.length)$('errors').append(text('li','Tidak ada error pengambilan Windows.'));
  drawHistory(snap.history||[]);
}
function batteryState(s){const map={1:'Menggunakan baterai',2:'Terhubung daya AC',3:'Penuh',4:'Rendah',5:'Kritis',6:'Sedang mengisi',7:'Mengisi · tinggi',8:'Mengisi · rendah',9:'Mengisi · kritis',10:'Status tidak diketahui',11:'Terisi sebagian'};return map[s]||'Status daya tidak tersedia';}
function metricLabel(key){return ({accesses:'Total request',cpu:'CPU Apache (%)',uptime:'Uptime',requestsPerSecond:'Request/detik · rata-rata',busy:'Worker sibuk',idle:'Worker tersedia',trafficKiB:'Total trafik (KiB)',bytesPerSecond:'Byte/detik · rata-rata',Uptime:'Uptime',Threads_connected:'Koneksi terbuka',Threads_running:'Thread berjalan',Slow_queries:'Query lambat · total',Aborted_connects:'Koneksi gagal · total',Bytes_received:'Byte masuk · total',Bytes_sent:'Byte keluar · total'})[key]||key;}
function formatMetric(key,val){if(val===null)return 'Tidak tersedia';if(key.toLowerCase()==='uptime')return uptime(val);return Number(val).toLocaleString('id-ID',{maximumFractionDigits:2});}
function drawHistory(rows){
  const canvas=$('history'),ctx=canvas.getContext('2d'),dpr=window.devicePixelRatio||1,w=canvas.clientWidth,h=200;canvas.width=w*dpr;canvas.height=h*dpr;ctx.scale(dpr,dpr);ctx.clearRect(0,0,w,h);
  const css=getComputedStyle(document.documentElement),muted=css.getPropertyValue('--muted'),cpu=css.getPropertyValue('--primary'),ram=css.getPropertyValue('--green');ctx.font='11px system-ui';
  for(const v of [0,50,100]){const y=170-v*1.4;ctx.strokeStyle=css.getPropertyValue('--border');ctx.beginPath();ctx.moveTo(30,y);ctx.lineTo(w-12,y);ctx.stroke();ctx.fillStyle=muted;ctx.fillText(v+'%',0,y);}
  if(rows.length){const first=rows[0].timestamp,last=rows[rows.length-1].timestamp;for(const [key,color] of [['cpu',cpu],['ram',ram]]){ctx.strokeStyle=color;ctx.lineWidth=2;ctx.beginPath();let previous=null;for(const row of rows){if(row[key]===null||row[key]===undefined){previous=null;continue;}const x=30+(row.timestamp-first)/Math.max(1,last-first)*(w-45),y=170-Math.max(0,Math.min(100,row[key]))*1.4;if(previous===null||row.timestamp-previous>60)ctx.moveTo(x,y);else ctx.lineTo(x,y);previous=row.timestamp;}ctx.stroke();}ctx.fillStyle=muted;ctx.fillText(new Date(first*1000).toLocaleTimeString('id-ID'),30,194);const end=new Date(last*1000).toLocaleTimeString('id-ID');ctx.fillText(end,Math.max(30,w-75),194);}
  $('historyInfo').textContent=rows.length+' sampel · CPU (ungu), RAM (hijau). Data tidak tersedia atau jeda >60 detik memutus garis. Maksimal 720 sampel.';
}
async function refresh(){if(!loggedIn||inFlight)return;inFlight=true;try{render(await api('snapshot'));}catch(e){notice('Pembaruan gagal: '+e.message+' Data terakhir mungkin sudah lama.');$('freshness').textContent='Pembaruan gagal';$('freshness').className='badge degraded';}finally{inFlight=false;}}
function openForm(service){const f=$('serviceForm');f.reset();f.elements.id.value='';if(service)for(const key of ['id','name','port','type','path','expected'])f.elements[key].value=service[key];f.elements.enabled.checked=service?service.enabled:true;$('formTitle').textContent=service?'Edit layanan':'Tambah layanan';$('httpFields').hidden=f.elements.type.value!=='http';$('formError').textContent='';$('serviceDialog').showModal();}
$('serviceForm').elements.type.addEventListener('change',e=>$('httpFields').hidden=e.target.value!=='http');
$('checkNow').addEventListener('click',async()=>{try{await api('check',{});notice('Permintaan pemeriksaan dikirim. Collector harus sedang berjalan; hasil akan diperbarui otomatis.');}catch(e){notice(e.message);}});
$('add').addEventListener('click',()=>openForm());$('cancel').addEventListener('click',()=>$('serviceDialog').close());
$('serviceForm').addEventListener('submit',async e=>{e.preventDefault();const f=e.currentTarget,button=f.querySelector('button.primary');button.disabled=true;const body={name:f.elements.name.value,port:Number(f.elements.port.value),type:f.elements.type.value,path:f.elements.path.value,expected:Number(f.elements.expected.value),enabled:f.elements.enabled.checked};if(f.elements.id.value)body.id=f.elements.id.value;try{await api('save',body);$('serviceDialog').close();notice('Konfigurasi disimpan. Menunggu pemeriksaan collector berikutnya.');await refresh();}catch(err){$('formError').textContent=err.message;}finally{button.disabled=false;}});
$('loginForm').addEventListener('submit',async e=>{e.preventDefault();const form=e.currentTarget,button=form.querySelector('button');button.disabled=true;try{await api('login',{username:form.elements.username.value,password:form.elements.password.value});form.elements.password.value='';await session();notice();await refresh();}catch(err){notice(err.message);}finally{button.disabled=false;}});
$('logout').addEventListener('click',async()=>{try{await api('logout',{});loggedIn=false;latest=null;await session();notice();}catch(e){notice(e.message);}});
$('loadAudit').addEventListener('click',async()=>{try{const rows=await api('audit');$('audit').replaceChildren();for(const row of rows.slice(-30).reverse())$('audit').append(text('li',new Date(row.at).toLocaleString('id-ID')+' · '+row.event));}catch(e){notice(e.message);}});
function applyTheme(value){document.documentElement.dataset.theme=value;try{localStorage.setItem('hay-theme',value);}catch{}if(latest?.snapshot)drawHistory(latest.snapshot.history||[]);}
$('theme').addEventListener('click',()=>applyTheme(document.documentElement.dataset.theme==='light'?'dark':'light'));
try{applyTheme(localStorage.getItem('hay-theme')||'dark');}catch{applyTheme('dark');}
function network(){$('browserNetwork').textContent=navigator.onLine?'Terhubung ke jaringan':'Offline';}window.addEventListener('online',network);window.addEventListener('offline',network);network();
function clock(){$('clock').textContent=new Date().toLocaleString('id-ID');}clock();setInterval(clock,1000);
window.addEventListener('resize',()=>{if(latest?.snapshot)drawHistory(latest.snapshot.history||[]);});
session().then(refresh).catch(e=>notice(e.message));setInterval(refresh,15000);

let lastTouch=0;for(const event of ['pointerdown','keydown'])document.addEventListener(event,()=>{if(loggedIn&&Date.now()-lastTouch>60000){lastTouch=Date.now();api('touch',{}).catch(()=>{});}}, {passive:true});
