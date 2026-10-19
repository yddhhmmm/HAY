# Hasil validasi

Validasi dilakukan di Linux dengan PHP CLI 8.4.24 dan Chromium headless. Ini bukan pengujian langsung di laptop Acer.

- Sepuluh kelompok tes integrasi Python lulus: akses/session/header, CRUD dan validasi, collector dan stale/fingerprint konfigurasi, pemeriksaan TCP/HTTP tanpa redirect, konkurensi dan korupsi JSON, rate limit, viewer/reset, kedaluwarsa session dan batas folder privat, serta penentuan lokasi data bersama pada struktur XAMPP tanpa konfigurasi Apache, dan wizard setup lokal dengan token/Origin serta penghentian server.
- Tes browser dashboard lulus: login, rendering metrik/grafik dengan fixture sintetis, nama layanan berisi HTML ditampilkan sebagai teks, tambah/edit/hapus layanan, form HTTP, tema, audit, permintaan pemeriksaan, lebar ponsel, logout. Tidak ada page error atau permintaan eksternal.
- Tes browser setup lokal lulus: form password tersembunyi, POST same-origin diterima, akun disimpan tanpa password masuk output CMD, dan server setup berhenti setelah berhasil.
- Semua file PHP lolos pemeriksaan sintaks; JavaScript lolos `node --check`.
- Ekstensi mysqli dimuat terpisah untuk memeriksa jalur koneksi MySQL gagal: mengembalikan `unavailable` tanpa fatal error. Statistik MySQL sukses belum diuji dengan server MySQL/MariaDB nyata.

Belum diverifikasi di Windows: launcher CMD, utilitas whoami/icacls dan ACL Windows, skrip PowerShell 5.1/CIM, PHP XAMPP, konfigurasi Apache/mod_status, Task Scheduler, cookie HTTPS, dan akun monitoring MySQL yang sebenarnya. README memuat langkah pemasangan dan pemeriksaan tersebut.

Tidak ada konfigurasi, password, atau data laptop asli yang dimasukkan ke paket. Port awal mengikuti daftar lama; akun admin harus dibuat melalui CLI.
