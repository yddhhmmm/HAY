<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
try {
    webInit();
    $action = $_GET['action'] ?? 'session';
    $method = $_SERVER['REQUEST_METHOD'];
    $input = [];
    if ($method !== 'GET') {
        if ($method !== 'POST') reply(['error'=>'Metode tidak diizinkan.'],405);
        if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) reply(['error'=>'Gunakan JSON.'],415);
        $body = file_get_contents('php://input', false, null, 0, 8193);
        if (strlen($body) > 8192) reply(['error'=>'Permintaan terlalu besar.'],413);
        $input = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($input)) reply(['error'=>'JSON tidak valid.'],400);
        if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) reply(['error'=>'Token keamanan tidak valid. Muat ulang halaman.'],403);
    }
    if ($action === 'session' && $method === 'GET') reply(['user'=>$_SESSION['user'] ?? null,'csrf'=>$_SESSION['csrf'],'configured'=>!empty(loadJson('auth')),'role'=>$_SESSION['role']??null]);
    if ($action === 'login' && $method === 'POST') {
        $auth = loadJson('auth');
        if (!$auth) reply(['error'=>'Jalankan setup admin melalui terminal terlebih dahulu.'],503);
        $key = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local');
        $allowed = false;
        mutateJson('attempts', function($all) use($key,&$allowed) {
            $now=time(); foreach($all as $k=>$v) if($v['since'] < $now-900) unset($all[$k]);
            $row=$all[$key] ?? ['since'=>$now,'count'=>0];
            $allowed=$row['count'] < 5; if($allowed) $row['count']++;
            $all[$key]=$row; return $all;
        });
        if (!$allowed) { header('Retry-After: 900'); reply(['error'=>'Terlalu banyak percobaan. Tunggu 15 menit.'],429); }
        $username=(string)($input['username']??'');
        $account=isset($auth['viewer'])&&hash_equals($auth['viewer']['username'],$username)?$auth['viewer']:$auth;
        $provided=(string)($input['password']??'');
        $validPassword=password_verify($provided,$account['hash']) && strlen($provided)<=72;
        if (!hash_equals($account['username'], $username) || !$validPassword) {audit('login_failed');reply(['error'=>'Nama pengguna atau password salah.'],401);}
        $role=$account['username']===$auth['username']?'admin':'viewer';
        mutateJson('attempts',function($all)use($key){unset($all[$key]);return $all;});
        session_regenerate_id(true);
        $_SESSION=['user'=>$account['username'],'role'=>$role,'version'=>$auth['version'],'created'=>time(),'last'=>time(),'csrf'=>bin2hex(random_bytes(32))];
        audit('login'); reply(['ok'=>true]);
    }
    if (!isset($_SESSION['user'])) reply(['error'=>'Silakan login.'],401);
    if ($action === 'logout' && $method === 'POST') { $_SESSION=[]; session_destroy(); reply(['ok'=>true]); }
    if ($action === 'touch' && $method === 'POST') reply(['ok'=>true]);
    if ($action === 'snapshot' && $method === 'GET') {
        $snapshot=loadJson('snapshot',null); $services=loadJson('services');
        if($snapshot) foreach($services as $service) {
            $r=$snapshot['services'][$service['id']]??null;
            if(!$r || !hash_equals(hash('sha256',json_encode($service)), $r['fingerprint']??'')) unset($snapshot['services'][$service['id']]);
        }
        reply(['snapshot'=>$snapshot,'services'=>$services,'serverTime'=>time(),'stale'=>$snapshot===null || time()-($snapshot['timestamp'] ?? 0)>60]);
    }
    if ($_SESSION['role']!=='admin') reply(['error'=>'Akses admin diperlukan.'],403);
    if ($action === 'check' && $method === 'POST') {
        $accepted=false;
        mutateJson('request',function($row)use(&$accepted){
            if(time()-($row['timestamp']??0)<10) return $row;
            $accepted=true;return ['timestamp'=>time(),'token'=>bin2hex(random_bytes(8))];
        });
        if(!$accepted) reply(['error'=>'Tunggu 10 detik sebelum meminta pemeriksaan lagi.'],429);
        reply(['ok'=>true]);
    }
    if ($action === 'audit' && $method === 'GET') reply(loadJson('audit'));
    if ($action === 'save' && $method === 'POST') {
        $service=validateService($input); $id=$input['id'] ?? null;
        if ($id !== null && (!is_string($id) || !preg_match('/^[a-f0-9]{16}$/D',$id))) throw new InvalidArgumentException('ID tidak valid.');
        mutateJson('services',function($rows)use($service,$id){
            $found=false;
            foreach($rows as $row) {
                if ($row['id']===$id) $found=true;
                if ($row['id']!==$id && $row['port']===$service['port']) throw new InvalidArgumentException('Port sudah terdaftar.');
            }
            if ($id!==null && !$found) throw new InvalidArgumentException('Layanan tidak ditemukan.');
            if ($id===null && count($rows)>=20) throw new InvalidArgumentException('Maksimal 20 layanan.');
            $new=$service+['id'=>$id ?? bin2hex(random_bytes(8))];
            if ($id===null) $rows[]=$new;
            else foreach($rows as &$row) if($row['id']===$id) $row=$new;
            return $rows;
        },[],true);
        audit('service_saved',['id'=>$id,'port'=>$service['port']]); reply(['ok'=>true]);
    }
    if ($action === 'delete' && $method === 'POST') {
        $id=$input['id'] ?? '';
        if(!is_string($id)||!preg_match('/^[a-f0-9]{16}$/D',$id)) throw new InvalidArgumentException('ID tidak valid.');
        mutateJson('services',function($rows)use($id){
            $next=array_values(array_filter($rows,fn($r)=>$r['id']!==$id));
            if(count($next)===count($rows)) throw new InvalidArgumentException('Layanan tidak ditemukan.');
            return $next;
        },[],true);
        audit('service_deleted',['id'=>$id]); reply(['ok'=>true]);
    }
    reply(['error'=>'Endpoint tidak ditemukan.'],404);
} catch(InvalidArgumentException|JsonException $e) { reply(['error'=>$e instanceof JsonException ? 'Format JSON tidak valid atau file penyimpanan rusak.' : $e->getMessage()],400); }
catch(Throwable $e) { error_log('HAY API: '.$e->getMessage()); reply(['error'=>'Terjadi kesalahan server. Periksa log PHP dan izin folder privat.'],500); }
