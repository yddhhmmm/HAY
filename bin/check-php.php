<?php
// Dibuat dengan sintaks sederhana agar pesan versi tetap terbaca pada PHP lama.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (PHP_VERSION_ID < 70300) {
    fwrite(STDERR, 'PHP aktif: '.PHP_VERSION.'. HAY memerlukan minimal PHP 7.3.'.PHP_EOL);
    exit(1);
}
foreach (array('json_encode', 'json_decode', 'session_start', 'filter_var') as $required) {
    if (!function_exists($required)) {
        fwrite(STDERR, 'Fungsi wajib tidak tersedia: '.$required.'. Periksa modul PHP JSON/session/filter.'.PHP_EOL);
        exit(1);
    }
}
fwrite(STDOUT, 'PHP '.PHP_VERSION.' - kompatibel dengan HAY.'.PHP_EOL);
