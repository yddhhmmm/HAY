<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
// Jalankan perintah Windows yang tetap melalui helper kompatibilitas, tanpa input dari browser.
function windowsCommand(array $command): string {
    $p=hayProcOpen($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($p)) throw new RuntimeException('Utilitas Windows gagal dijalankan.');
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($p)!==0) throw new RuntimeException('Pengaturan izin folder gagal: '.trim($err));
    return trim($out);
}
$process=null;$log=null;$done=null;$exitCode=0;
try {
    if(!is_callable('proc_open')) throw new RuntimeException('proc_open diperlukan untuk setup lokal.');
    $mode=$argv[1]??'init';
    if(!in_array($mode,['init','reset-password','set-viewer'],true)) throw new RuntimeException('Gunakan init, reset-password, atau set-viewer.');
    $new=!is_dir(storageLocation());$path=storage();
    if($new && PHP_OS_FAMILY==='Windows') {
        try {
            $system=getenv('SystemRoot')?:'C:\\Windows';
            $who=windowsCommand([$system.'\\System32\\whoami.exe','/user','/fo','csv','/nh']);
            if(!preg_match('/S-1-[0-9-]+/',$who,$m)) throw new RuntimeException('SID akun Windows tidak ditemukan.');
            windowsCommand([$system.'\\System32\\icacls.exe',$path,'/inheritance:r','/grant:r','*'.$m[0].':(OI)(CI)M','*S-1-5-18:(OI)(CI)F','*S-1-5-32-544:(OI)(CI)F']);
        } catch(Throwable $e) {@rmdir($path);throw $e;}
    }
    if(!$new) fwrite(STDOUT,"Folder privat sudah ada; periksa bahwa ACL hanya mengizinkan akun berwenang.\n");
    if($mode==='init'&&loadJson('auth')) throw new RuntimeException('Admin sudah ada. Untuk menggantinya, jalankan setup.cmd reset-password.');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
    if(!$socket) throw new RuntimeException('Port setup lokal tidak tersedia.');
    $host=stream_socket_get_name($socket,false);fclose($socket);
    $token=bin2hex(random_bytes(32));$done=$path.'/.setup-'.bin2hex(random_bytes(16));$expires=time()+600;
    putenv('HAY_SETUP_TOKEN='.$token);putenv('HAY_SETUP_MODE='.$mode);putenv('HAY_SETUP_HOST='.$host);putenv('HAY_SETUP_EXPIRES='.$expires);putenv('HAY_SETUP_DONE='.$done);
    $log=tempnam($path,'.setup-log-');
    $process=hayProcOpen([PHP_BINARY,'-S',$host,'-t',realpath(HAY_ROOT.'/public'),__DIR__.'/setup-router.php'],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($process)) throw new RuntimeException('Server setup tidak dapat dimulai.');
    fclose($pipes[0]);
    $ready=false;
    for($i=0;$i<30;$i++){$state=proc_get_status($process);if(!$state['running'])break;$fp=@stream_socket_client('tcp://'.$host,$errno,$errstr,.1);if($fp){fclose($fp);$ready=true;break;}usleep(100000);}
    if(!$ready)throw new RuntimeException('Server setup gagal mulai. '.file_get_contents($log));
    fwrite(STDOUT,"\nBuka URL berikut di browser laptop ini:\nhttp://".$host.'/?token='.$token."\n\nJangan bagikan URL ini. Isi password di browser, bukan di CMD.\nBiarkan jendela ini terbuka; Ctrl+C untuk membatalkan.\n");
    $completed=false;
    while(time()<$expires){clearstatcache(true,$done);if(file_exists($done)){sleep(2);fwrite(STDOUT,"\nAkun berhasil disimpan. Jalankan start-collector.cmd.\n");$completed=true;break;}if(!proc_get_status($process)['running'])throw new RuntimeException('Server setup berhenti: '.file_get_contents($log));usleep(250000);}
    if(!$completed) throw new RuntimeException('Waktu setup habis. Jalankan setup.cmd lagi.');
} catch(Throwable $e){fwrite(STDERR,"ERROR: ".$e->getMessage()."\n");$exitCode=1;}
finally{if(is_resource($process)){proc_terminate($process);proc_close($process);}if($log&&file_exists($log))unlink($log);if($done&&file_exists($done))unlink($done);}

exit($exitCode);
