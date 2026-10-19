<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require __DIR__.'/../src/metrics.php';
$lock=fopen(storage().'/collector.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)) {fwrite(STDERR,"Collector lain masih berjalan.\n");exit(1);}
$loop=in_array('--loop',$argv,true);
do {
    $start=microtime(true); $request=loadJson('request'); $token=$request['token']??null;
    try {$data=collectSnapshot();fwrite(STDOUT,gmdate('c')." selesai: ".$data['durationMs']." ms\n");}
    catch(Throwable $e) {fwrite(STDERR,"Pengumpulan gagal: ".$e->getMessage()."\n");if(!$loop)exit(1);}
    if($loop) do { usleep(500000); $next=loadJson('request'); if(($next['token']??null)!==$token) break; } while(microtime(true)-$start<15);
} while($loop);
