<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require __DIR__.'/../src/accounts.php';
try {
    $command=$argv[1]??'';
    if($command==='storage-path') {fwrite(STDOUT,storageLocation().PHP_EOL);exit;}
    if(!in_array($command,['init','reset-password','set-viewer'],true)) throw new RuntimeException('Gunakan init, reset-password, atau set-viewer. Password dibaca dari stdin.');
    $password=rtrim(stream_get_contents(STDIN,1025),"\r\n");
    saveAccount($command,$argv[2]??'admin',$password);
    fwrite(STDOUT,"Akun disimpan. Lokasi privat: ".storage()."\n");
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
