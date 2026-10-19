# Hasil validasi

Validasi dilakukan di Linux, bukan langsung pada laptop Acer / XAMPP Windows.

| Runtime | 11 kelompok tes integrasi | Browser dashboard | Browser setup | Lint 12 file PHP / pemeriksa versi |
|---|---|---|---|---|
| PHP 7.3.33 | Lulus | Lulus | Lulus | Lulus |
| PHP 7.4.33 | Lulus | Lulus | Lulus | Lulus |
| PHP 8.4.24 | Lulus (regresi) | Tidak diulang pada revisi kompatibilitas ini | Tidak diulang pada revisi kompatibilitas ini | Sintaks aplikasi diperiksa |

- Integrasi: akses/session/header, CRUD dan validasi, collector dan stale/fingerprint konfigurasi, TCP/HTTP tanpa redirect, konkurensi/korupsi JSON, rate limit, viewer/reset, idle session/folder privat, lokasi data XAMPP, wizard lokal token/Origin/penghentian server, serta helper kompatibilitas dan penerusan argumen proses secara literal.
- Browser dashboard: login, rendering metrik/grafik fixture sintetis, nama layanan berisi HTML tampil sebagai teks, tambah/edit/hapus layanan, form HTTP, tema, audit, permintaan pemeriksaan, lebar ponsel, dan logout. Tidak ada page error atau permintaan eksternal.
- Browser setup: form password tersembunyi, POST same-origin diterima, akun disimpan tanpa password masuk output CMD, dan server setup berhenti setelah berhasil.
- JSON dan session aktif pada kedua runtime PHP 7. Tes memakai konfigurasi PHP.ini runtime terpilih. Pada Debian PHP 7.4 JSON dimuat sebagai modul; XAMPP standar sudah menyertakan dukungan JSON.
- PHP 7.3.33 dibangun untuk pengujian dari repositori resmi php/php-src, tag php-7.3.33, commit c796a801599b13943c2413589e339c39d1d4bb47. Runtime pengujian tidak disertakan dalam paket aplikasi.
- Jalur MySQL gagal pernah diuji dengan mysqli pada PHP 8.4: mengembalikan unavailable tanpa fatal error. Statistik MySQL sukses belum diuji dengan server MySQL/MariaDB nyata pada matriks ini.

Belum diverifikasi langsung di Windows: launcher CMD, quoting proses Windows native, whoami/icacls dan ACL, PowerShell 5.1/CIM, PHP XAMPP/Apache/mod_status, Task Scheduler, cookie HTTPS, dan akun monitoring MySQL yang sebenarnya. README memuat langkah pemasangan dan pemeriksaan tersebut.

Paket tidak berisi konfigurasi privat, password pengguna, atau data laptop asli. Password di tests adalah fixture sementara, bukan akun bawaan. Port awal mengikuti daftar lama; akun admin harus dibuat melalui CLI/setup lokal.

PHP 7.3 dan 7.4 telah berakhir masa dukungan keamanan resminya. Kompatibilitas aplikasi tidak menggantikan pembaruan keamanan runtime; gunakan akses lokal bawaan.
