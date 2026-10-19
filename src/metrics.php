<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
function windowsMetrics(): array {
    if (PHP_OS_FAMILY !== 'Windows') return ['status'=>'unavailable','errors'=>['Metrik Windows hanya tersedia di Windows.']];
    if (!is_callable('proc_open')) return ['status'=>'unavailable','errors'=>['proc_open tidak tersedia.']];
    $out=tempnam(storage(),'.cim-'); $err=tempnam(storage(),'.err-');
    $exe=(getenv('SystemRoot') ?: 'C:\\Windows').'\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
    $process=hayProcOpen([$exe,'-NoLogo','-NoProfile','-NonInteractive','-File',realpath(__DIR__.'/../bin/windows-metrics.ps1')],[0=>['pipe','r'],1=>['file',$out,'w'],2=>['file',$err,'w']],$pipes,null,null,['bypass_shell'=>true]);
    if (!is_resource($process)) { unlink($out); unlink($err); return ['status'=>'unavailable','errors'=>['PowerShell tidak dapat dijalankan.']]; }
    fclose($pipes[0]); $start=microtime(true); $timed=false;
    do { $state=proc_get_status($process); if(!$state['running']) break; if(microtime(true)-$start>10) {proc_terminate($process);$timed=true;break;} usleep(50000); } while(true);
    proc_close($process);
    $raw=file_get_contents($out); unlink($out); unlink($err);
    try { if($timed) throw new RuntimeException(); $result=json_decode(preg_replace('/^\xEF\xBB\xBF/','',$raw),true,64,JSON_THROW_ON_ERROR); if(!is_array($result)||!isset($result['status'])) throw new RuntimeException(); return $result; }
    catch(Throwable $ignored) { return ['status'=>'unavailable','errors'=>[$timed?'Pengambilan CIM timeout.':'Output PowerShell tidak valid. Periksa kebijakan eksekusi dan CIM.']]; }
}
function tcpProbe(int $port): array {
    foreach(['127.0.0.1','[::1]'] as $host) {
        $start=microtime(true); $fp=@stream_socket_client("tcp://$host:$port",$errno,$error,0.25,STREAM_CLIENT_CONNECT);
        if($fp) {fclose($fp);return ['status'=>'healthy','latency'=>round((microtime(true)-$start)*1000,1),'detail'=>'Koneksi TCP berhasil'];}
    }
    return ['status'=>'unreachable','latency'=>null,'detail'=>'Koneksi TCP gagal'];
}
function localHttp(int $port, string $path): array {
    foreach(['127.0.0.1','[::1]'] as $host) {
        $start=microtime(true); $fp=@stream_socket_client("tcp://$host:$port",$errno,$error,0.25,STREAM_CLIENT_CONNECT);
        if(!$fp) continue;
        stream_set_timeout($fp,0,750000);
        // Host dan path ditentukan/ divalidasi aplikasi. Tidak memakai proxy atau mengikuti redirect.
        fwrite($fp,"GET $path HTTP/1.0\r\nHost: localhost:$port\r\nConnection: close\r\n\r\n");
        $response=''; $deadline=microtime(true)+0.75;
        while(!feof($fp) && strlen($response)<65536 && microtime(true)<$deadline) {
            $chunk=fread($fp,min(8192,65536-strlen($response))); if($chunk===false||$chunk==='') break; $response.=$chunk;
        }
        $meta=stream_get_meta_data($fp); fclose($fp);
        if($meta['timed_out']) return ['status'=>'degraded','code'=>null,'body'=>'','latency'=>null,'detail'=>'Respons HTTP timeout'];
        if(!preg_match('~^HTTP/\d(?:\.\d)?\s+(\d{3})~',$response,$m)) return ['status'=>'degraded','code'=>null,'body'=>'','latency'=>null,'detail'=>'Respons HTTP tidak valid'];
        $parts=explode("\r\n\r\n",$response,2);
        return ['status'=>'healthy','code'=>(int)$m[1],'body'=>$parts[1]??'','latency'=>round((microtime(true)-$start)*1000,1),'detail'=>'HTTP '.$m[1]];
    }
    return ['status'=>'unreachable','code'=>null,'body'=>'','latency'=>null,'detail'=>'Tidak dapat terhubung'];
}
function apacheMetrics(int $port): array {
    $r=localHttp($port,'/server-status?auto');
    if($r['status']!=='healthy'||$r['code']!==200) return ['status'=>$r['status']==='unreachable'?'unreachable':'unavailable','detail'=>'Endpoint mod_status tidak tersedia'];
    $map=[]; foreach(explode("\n",$r['body']) as $line) if(hayContains($line,':')) {[$k,$v]=explode(':',$line,2);$map[trim($k)]=trim($v);}
    if(!isset($map['Total Accesses'],$map['Uptime'])) return ['status'=>'unavailable','detail'=>'Format mod_status tidak lengkap; periksa ExtendedStatus'];
    $fields=['accesses'=>'Total Accesses','cpu'=>'CPULoad','uptime'=>'Uptime','requestsPerSecond'=>'ReqPerSec','busy'=>'BusyWorkers','idle'=>'IdleWorkers','trafficKiB'=>'Total kBytes','bytesPerSecond'=>'BytesPerSec'];
    $metrics=[];foreach($fields as $k=>$field) $metrics[$k]=isset($map[$field])&&is_numeric($map[$field])?(float)$map[$field]:null;
    return ['status'=>'healthy','detail'=>'Statistik Apache tersedia','metrics'=>$metrics];
}
function mysqlMetrics(): array {
    if(!extension_loaded('mysqli')) return ['status'=>'unavailable','detail'=>'Ekstensi mysqli belum aktif'];
    $user=getenv('HAY_MYSQL_USER'); $pass=getenv('HAY_MYSQL_PASSWORD');
    if(!$user) { $probe=tcpProbe(3306); return ['status'=>$probe['status']==='healthy'?'unavailable':'unreachable','detail'=>$probe['status']==='healthy'?'Port terbuka; akun monitoring belum dikonfigurasi':'Port MySQL tidak terjangkau']; }
    mysqli_report(MYSQLI_REPORT_OFF); $db=mysqli_init();
    try {
        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT,2);
        if(defined('MYSQLI_OPT_READ_TIMEOUT')) $db->options(MYSQLI_OPT_READ_TIMEOUT,2);
        if(!@$db->real_connect('127.0.0.1',$user,$pass?:'',null,3306)) return ['status'=>'unavailable','detail'=>'Koneksi akun monitoring gagal'];
        $result=$db->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime','Threads_connected','Threads_running','Slow_queries','Aborted_connects','Bytes_received','Bytes_sent')");
        if(!$result) return ['status'=>'unavailable','detail'=>'Query statistik MySQL gagal'];
        $metrics=[];while($row=$result->fetch_assoc())$metrics[$row['Variable_name']]=(float)$row['Value'];
        return ['status'=>'healthy','detail'=>'Statistik MySQL tersedia','metrics'=>$metrics];
    } catch(Throwable $ignored) {return ['status'=>'unavailable','detail'=>'Pemeriksaan MySQL gagal'];} finally {try {if($db instanceof mysqli) $db->close();} catch(Throwable $ignored) { /* Koneksi yang belum terinisialisasi tidak boleh menghentikan collector. */ }}
}
function collectSnapshot(): array {
    $started=microtime(true); $system=windowsMetrics(); $services=loadJson('services'); $results=[];
    foreach($services as $service) {
        if(!$service['enabled']) $r=['status'=>'disabled','detail'=>'Pemeriksaan dinonaktifkan','latency'=>null];
        elseif(microtime(true)-$started>40) $r=['status'=>'unavailable','detail'=>'Batas waktu siklus tercapai','latency'=>null];
        elseif($service['type']==='tcp')$r=tcpProbe($service['port']);
        else { $r=localHttp($service['port'],$service['path']); unset($r['body']); if($r['status']==='healthy'&&$r['code']!==$service['expected']) {$r['status']='degraded';$r['detail']='HTTP '.$r['code'].'; diharapkan '.$service['expected'];} }
        // Fingerprint mencegah hasil konfigurasi lama ditampilkan sebagai hasil konfigurasi baru.
        $results[$service['id']]=$r+['fingerprint'=>hash('sha256',json_encode($service))];
    }
    $snapshot=['timestamp'=>time(),'system'=>$system,'services'=>$results,'apache80'=>apacheMetrics(80),'apache8074'=>apacheMetrics(8074),'mysql'=>mysqlMetrics()];
    $conditions=[]; $names=array_column($services,'name','id');
    foreach($results as $id=>$r) if(in_array($r['status'],['degraded','unreachable'],true)) $conditions['service:'.$id]='Layanan '.($names[$id]??$id).' gagal tiga pemeriksaan berturut-turut.';
    if(($system['cpu']??0)>=90) $conditions['cpu']='CPU minimal 90% selama tiga pemeriksaan.';
    if(($system['ram']['percent']??0)>=90) $conditions['ram']='RAM minimal 90% selama tiga pemeriksaan.';
    foreach($system['disks']??[] as $disk) if($disk['size']>0&&($disk['size']-$disk['free'])/$disk['size']>=0.9) $conditions['disk:'.$disk['drive']]='Disk '.$disk['drive'].' minimal 90% penuh selama tiga pemeriksaan.';
    $counters=mutateJson('alert-state',function($previous)use($conditions){$next=[];foreach($conditions as $key=>$message)$next[$key]=['count'=>min(3,($previous[$key]['count']??0)+1),'message'=>$message];return $next;});
    $snapshot['alerts']=array_values(array_map(function($r) { return $r['message']; },array_filter($counters,function($r) { return $r['count']>=3; })));
    $history=mutateJson('history',function($rows)use($snapshot){$rows[]=['timestamp'=>$snapshot['timestamp'],'cpu'=>$snapshot['system']['cpu']??null,'ram'=>$snapshot['system']['ram']['percent']??null];return array_slice($rows,-720);});
    $snapshot['history']=$history; $snapshot['durationMs']=round((microtime(true)-$started)*1000);
    mutateJson('snapshot',function()use($snapshot) { return $snapshot; }); return $snapshot;
}
