<?php
// Router hanya untuk server sementara yang dimulai oleh setup-console.php, bukan endpoint Apache.
declare(strict_types=1);
if(PHP_SAPI!=='cli-server') {http_response_code(404);exit;}
require __DIR__.'/../src/accounts.php';
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');header('X-Frame-Options: DENY');
$nonce=base64_encode(random_bytes(24));
header("Content-Security-Policy: default-src 'none'; style-src 'nonce-$nonce'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$token=getenv('HAY_SETUP_TOKEN')?:''; $mode=getenv('HAY_SETUP_MODE')?:'';
$origin='http://'.(getenv('HAY_SETUP_HOST')?:'');
$done=getenv('HAY_SETUP_DONE')?:'';
ini_set('display_errors','0');
if(strlen($token)!==64 || !is_string($_GET['token']??null) || !hash_equals($token,$_GET['token']) || ($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1' || ($_SERVER['HTTP_HOST']??'')!==getenv('HAY_SETUP_HOST') || parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)!=='/') {http_response_code(403);exit('Akses setup ditolak. Gunakan URL dari jendela CMD.');}
if(time()>(int)getenv('HAY_SETUP_EXPIRES') || !$done || file_exists($done)) {http_response_code(410);exit('Sesi setup sudah berakhir.');}
$error='';$success=false;$username=$mode==='set-viewer'?'viewer':'admin';
if($_SERVER['REQUEST_METHOD']==='POST') {
    if(($_SERVER['HTTP_ORIGIN']??'')!==$origin || !hayStartsWith($_SERVER['CONTENT_TYPE']??'','application/x-www-form-urlencoded')) {http_response_code(403);exit('Asal permintaan tidak valid.');}
    if((int)($_SERVER['CONTENT_LENGTH']??0)>4096){http_response_code(413);exit('Permintaan terlalu besar.');}
    try {
        if(!is_string($_POST['username']??null)||!is_string($_POST['password']??null)||!is_string($_POST['confirm']??null)) throw new InvalidArgumentException('Form tidak lengkap.');
        $username=$_POST['username'];
        if(!hash_equals($_POST['password'],$_POST['confirm'])) throw new InvalidArgumentException('Konfirmasi password tidak sama.');
        saveAccount($mode,$username,$_POST['password']);
        if(file_put_contents($done,'done',LOCK_EX)===false) throw new RuntimeException('Penanda setup tidak dapat ditulis.');
        $success=true;
    } catch(InvalidArgumentException $e) {$error=$e->getMessage();}
    catch(Throwable $e) {error_log('HAY setup: '.$e->getMessage());$error='Setup gagal. Periksa pesan error di CMD dan izin folder privat.';http_response_code(500);}
} elseif($_SERVER['REQUEST_METHOD']!=='GET') {http_response_code(405);exit;}
function e(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Setup akun HAY</title><style nonce="<?=e($nonce)?>">body{background:#101625;color:#eef1fa;font:16px/1.6 system-ui;max-width:460px;margin:50px auto;padding:20px}input,button{display:block;box-sizing:border-box;width:100%;padding:12px;margin:8px 0 20px;border-radius:8px;border:1px solid #697a9f;font:inherit}button{background:#b7a6ff;cursor:pointer}p{color:#c1cbe1}.error{color:#ffb9b9}</style></head><body><h1>Setup akun HAY</h1>
<?php if($success): ?><h2>Akun berhasil disimpan</h2><p>Anda dapat menutup halaman ini, kemudian menjalankan start-collector.cmd dan membuka dashboard.</p>
<?php else: ?><p>Mode: <?=e($mode)?>. Halaman lokal ini hanya aktif selama setup berjalan, maksimal 10 menit.</p><p class="error" role="alert"><?=e($error)?></p><form method="post" action="/?token=<?=e($token)?>"><label>Username<input name="username" value="<?=e($username)?>" autocomplete="username" maxlength="40" required></label><label>Password (12–72 byte)<input name="password" type="password" autocomplete="new-password" required></label><label>Ulangi password<input name="confirm" type="password" autocomplete="new-password" required></label><button>Simpan akun</button></form><?php endif; ?></body></html>
