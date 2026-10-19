<?php
declare(strict_types=1);
// XAMPP standar: biarkan null. Folder privat otomatis dibuat di sebelah htdocs.
// Lokasi khusus: ubah menjadi path absolut di luar htdocs, contoh 'D:/data/hay-private'.
// Web, setup admin, dan collector membaca pengaturan yang sama.
// HAY_STORAGE (jika sengaja diset) tetap memiliki prioritas lebih tinggi.
return ['storage' => null];
