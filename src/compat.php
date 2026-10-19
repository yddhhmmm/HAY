<?php
declare(strict_types=1);
// Helper bernama khusus HAY, tanpa mengganti fungsi global bawaan PHP 8.
function hayStartsWith(string $text, string $prefix): bool {
    return $prefix === '' || strncmp($text, $prefix, strlen($prefix)) === 0;
}
function hayContains(string $text, string $part): bool {
    return $part === '' || strpos($text, $part) !== false;
}
function hayArrayIsList(array $rows): bool {
    $index=0;
    foreach($rows as $key=>$value) if($key !== $index++) return false;
    return true;
}
// Quoting Windows mengikuti aturan argv C runtime, bukan escaping cmd.exe.
// bypass_shell=true wajib: %, !, &, dan karakter shell lainnya tetap literal.
function hayWindowsArgument(string $arg): string {
    if(strpos($arg,"\0")!==false) throw new InvalidArgumentException('Argumen proses mengandung NUL.');
    $out='"';$slashes=0;
    for($i=0,$length=strlen($arg);$i<$length;$i++) {
        $c=$arg[$i];
        if($c==='\\') {$slashes++;continue;}
        if($c==='"') $out.=str_repeat('\\',$slashes*2+1).'"';
        else $out.=str_repeat('\\',$slashes).$c;
        $slashes=0;
    }
    return $out.str_repeat('\\',$slashes*2).'"';
}
function hayProcessCommand(array $args, bool $windows): string {
    if(!$args) throw new InvalidArgumentException('Perintah proses kosong.');
    $parts=[];
    foreach($args as $arg) {
        if(!is_string($arg)||strpos($arg,"\0")!==false) throw new InvalidArgumentException('Argumen proses tidak valid.');
        $parts[]=$windows?hayWindowsArgument($arg):escapeshellarg($arg);
    }
    // exec membuat PID shell POSIX diganti PID program agar timeout/stop tepat sasaran.
    return ($windows?'':'exec ').implode(' ',$parts);
}
function hayProcOpen(array $command, array $descriptors, &$pipes, $cwd=null, $env=null, array $options=[]) {
    $options['bypass_shell']=true;
    $native=PHP_VERSION_ID>=70400?$command:hayProcessCommand($command,PHP_OS_FAMILY==='Windows');
    return proc_open($native,$descriptors,$pipes,$cwd,$env,$options);
}
