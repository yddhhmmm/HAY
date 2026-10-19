<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
function saveAccount(string $command, string $username, string $password): void {
    if(!in_array($command,['init','reset-password','set-viewer'],true)) throw new InvalidArgumentException('Mode akun tidak valid.');
    if(!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/D',$username)) throw new InvalidArgumentException('Username harus 3–40 karakter: huruf, angka, _ . -');
    if(strlen($password)<12||strlen($password)>72) throw new InvalidArgumentException('Password harus 12–72 byte.');
    mutateJson('auth',function($auth)use($command,$username,$password){
        if($command==='init'&&$auth) throw new InvalidArgumentException('Admin sudah ada. Pilih reset-password jika ingin menggantinya.');
        if($command==='set-viewer') {
            if(!$auth) throw new InvalidArgumentException('Buat admin terlebih dahulu.');
            if($username===$auth['username']) throw new InvalidArgumentException('Username viewer harus berbeda dari admin.');
            $auth['viewer']=['username'=>$username,'hash'=>password_hash($password,PASSWORD_DEFAULT)];
            $auth['version']=bin2hex(random_bytes(16));return $auth;
        }
        if(isset($auth['viewer'])&&$auth['viewer']['username']===$username) throw new InvalidArgumentException('Username admin tidak boleh sama dengan viewer.');
        $next=['username'=>$username,'hash'=>password_hash($password,PASSWORD_DEFAULT),'version'=>bin2hex(random_bytes(16))];
        if(isset($auth['viewer'])) $next['viewer']=$auth['viewer'];
        return $next;
    });
    mutateJson('services',fn($rows)=>$rows,defaultServices());
    mutateJson('attempts',fn()=>[]);
    audit($command==='init'?'admin_created':($command==='set-viewer'?'viewer_saved':'password_reset'));
}
