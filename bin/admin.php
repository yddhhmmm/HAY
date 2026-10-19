<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
try {
    $command=$argv[1]??'';
    if(!in_array($command,['init','reset-password','set-viewer'],true)) throw new RuntimeException('Gunakan init, reset-password, atau set-viewer. Password dibaca dari stdin.');
    if($command==='init'&&loadJson('auth')) throw new RuntimeException('Admin sudah ada. Gunakan reset-password untuk mengganti password.');
    $username=$argv[2]??'admin';
    if(!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/D',$username)) throw new RuntimeException('Username harus 3–40 karakter: huruf, angka, _ . -');
    $password=rtrim(stream_get_contents(STDIN,1025),"\r\n");
    if(strlen($password)<12||strlen($password)>72) throw new RuntimeException('Password harus 12–72 byte.');
    mutateJson('auth',function($auth)use($command,$username,$password){
        if($command==='set-viewer') {
            if(!$auth) throw new RuntimeException('Buat admin terlebih dahulu.');
            if($username===$auth['username']) throw new RuntimeException('Username viewer harus berbeda dari admin.');
            $auth['viewer']=['username'=>$username,'hash'=>password_hash($password,PASSWORD_DEFAULT)];
            $auth['version']=bin2hex(random_bytes(16));return $auth;
        }
        if(isset($auth['viewer'])&&$auth['viewer']['username']===$username) throw new RuntimeException('Username admin tidak boleh sama dengan viewer.');
        $next=['username'=>$username,'hash'=>password_hash($password,PASSWORD_DEFAULT),'version'=>bin2hex(random_bytes(16))];
        if(isset($auth['viewer'])) $next['viewer']=$auth['viewer'];
        return $next;
    });
    mutateJson('services',fn($rows)=>$rows,defaultServices());
    mutateJson('attempts',fn()=>[]);
    audit($command==='init'?'admin_created':($command==='set-viewer'?'viewer_saved':'password_reset'));
    fwrite(STDOUT,"Akun disimpan. Lokasi privat: ".storage()."\n");
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
