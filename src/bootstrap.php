<?php
declare(strict_types=1);
const HAY_ROOT = __DIR__ . '/..';
function storage(): string {
    static $path;
    if ($path !== null) return $path;
    $doc = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $default = $doc !== '' ? dirname($doc) . '/hay-private' : dirname(realpath(HAY_ROOT)) . '/hay-private';
    $candidate = getenv('HAY_STORAGE') ?: $default;
    if (!is_dir($candidate) && !mkdir($candidate, 0700, true) && !is_dir($candidate)) throw new RuntimeException('Penyimpanan tidak dapat dibuat.');
    $resolved = realpath($candidate);
    if (!$resolved) throw new RuntimeException('Lokasi penyimpanan tidak valid.');
    if ($doc && ($root = realpath($doc))) {
        $a = strtolower(str_replace('\\', '/', $resolved)); $b = strtolower(rtrim(str_replace('\\', '/', $root), '/'));
        if ($a === $b || str_starts_with($a, $b . '/')) throw new RuntimeException('HAY_STORAGE wajib di luar document root.');
    }
    return $path = $resolved;
}
function loadJson(string $name, mixed $default = []): mixed {
    $file = storage() . '/' . $name . '.json';
    if (!file_exists($file)) return $default;
    $handle = fopen(storage() . '/' . $name . '.lock', 'c');
    if (!$handle || !flock($handle, LOCK_SH)) throw new RuntimeException('Gagal membaca penyimpanan.');
    try { $value=json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); if($name==='services') validateServicesDocument($value); return $value; }
    finally { flock($handle, LOCK_UN); fclose($handle); }
}
function mutateJson(string $name, callable $fn, mixed $default = [], bool $backup = false): mixed {
    $lock = fopen(storage() . '/' . $name . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Penyimpanan sedang sibuk.');
    $tmp = null;
    try {
        $file = storage() . '/' . $name . '.json';
        $old = file_exists($file) ? file_get_contents($file) : null;
        $current=$old !== null ? json_decode($old, true, 512, JSON_THROW_ON_ERROR) : $default;
        if($name==='services') validateServicesDocument($current);
        $value = $fn($current);
        if($name==='services') validateServicesDocument($value);
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $tmp = tempnam(storage(), '.write-');
        if (file_put_contents($tmp, $json) !== strlen($json)) throw new RuntimeException('Gagal menulis konfigurasi.');
        chmod($tmp, 0600);
        if ($backup && $old !== null && file_put_contents($file . '.bak', $old, LOCK_EX) !== strlen($old)) throw new RuntimeException('Backup gagal.');
        if (!rename($tmp, $file)) throw new RuntimeException('Gagal mengganti konfigurasi.');
        return $value;
    } finally { if ($tmp && file_exists($tmp)) unlink($tmp); flock($lock, LOCK_UN); fclose($lock); }
}
function defaultServices(): array {
    return array_map(fn($port) => ['id' => bin2hex(random_bytes(8)), 'name' => 'Layanan ' . $port, 'port' => $port, 'type' => 'tcp', 'path' => '/health', 'expected' => 200, 'enabled' => true], [2025,2026,9000,9001,9002,9003,9004]);
}
function validateService(array $input): array {
    if(!is_string($input['name']??null)||!is_string($input['path']??'/health')||!is_bool($input['enabled']??true)) throw new InvalidArgumentException('Tipe input layanan tidak valid.');
    $name = trim($input['name']);
    $port = filter_var($input['port'] ?? null, FILTER_VALIDATE_INT);
    $type = $input['type'] ?? '';
    $path = (string)($input['path'] ?? '/health');
    $expected = filter_var($input['expected'] ?? 200, FILTER_VALIDATE_INT);
    if ($name === '' || strlen($name) > 80 || preg_match('/[\x00-\x1f\x7f]/', $name) || !preg_match('//u', $name)) throw new InvalidArgumentException('Nama wajib diisi, maksimal 80 byte.');
    if (!$port || $port < 1 || $port > 65535) throw new InvalidArgumentException('Port harus 1–65535.');
    if (!in_array($type, ['tcp','http'], true)) throw new InvalidArgumentException('Jenis pemeriksaan tidak valid.');
    if (!preg_match('~^/(?!/)[A-Za-z0-9/_\.\-?=&%]*$~D', $path) || strlen($path) > 200 || str_contains($path, '..')) throw new InvalidArgumentException('Path lokal tidak valid.');
    if ($expected < 200 || $expected > 599) throw new InvalidArgumentException('Kode HTTP harus 200–599.');
    return ['name'=>$name,'port'=>$port,'type'=>$type,'path'=>$path,'expected'=>$expected,'enabled'=>($input['enabled'] ?? true) === true];
}
function validateServicesDocument(mixed $rows): void {
    if(!is_array($rows)||!array_is_list($rows)||count($rows)>20) throw new RuntimeException('Struktur services.json tidak valid.');
    $ids=[];$ports=[];
    foreach($rows as $row) {
        if(!is_array($row)||!is_string($row['id']??null)||!preg_match('/^[a-f0-9]{16}$/D',$row['id'])) throw new RuntimeException('ID dalam services.json tidak valid.');
        $checked=validateService($row);
        if(!is_int($row['port'])||isset($ids[$row['id']])||isset($ports[$checked['port']])) throw new RuntimeException('ID/port dalam services.json duplikat atau tidak valid.');
        $ids[$row['id']]=true;$ports[$checked['port']]=true;
    }
}
function audit(string $event, array $context = []): void {
    mutateJson('audit', function($rows) use ($event,$context) {
        $rows[] = ['at'=>gmdate('c'),'event'=>$event,'context'=>$context];
        return array_slice($rows, -500);
    });
}
function webInit(): void {
    header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY'); header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if ($secure) header('Strict-Transport-Security: max-age=31536000');
    ini_set('display_errors', '0'); ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
    session_name('HAYSESSID');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);
    if(!session_start()) throw new RuntimeException('Session tidak dapat dimulai. Periksa session.save_path.');
    if (isset($_SESSION['last']) && time() - $_SESSION['last'] > 1800) $_SESSION = [];
    if (isset($_SESSION['created']) && time() - $_SESSION['created'] > 28800) $_SESSION = [];
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    if (isset($_SESSION['user'])) {
        $auth = loadJson('auth');
        if (!hash_equals((string)($auth['version'] ?? ''), (string)($_SESSION['version'] ?? ''))) $_SESSION = ['csrf'=>bin2hex(random_bytes(32))];
        elseif(!in_array($_GET['action']??'', ['snapshot','session'],true)) $_SESSION['last'] = time();
    }
}
function reply(mixed $value, int $code = 200): never {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($value, JSON_THROW_ON_ERROR); exit;
}
