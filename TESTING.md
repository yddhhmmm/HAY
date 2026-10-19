# Hasil validasi

Validasi dilakukan di Linux dengan PHP CLI 8.4.24 dan Chromium headless. Ini bukan pengujian langsung di laptop Acer.

- Delapan kelompok tes integrasi Python lulus: akses/session/header, CRUD dan validasi, collector dan stale/fingerprint konfigurasi, pemeriksaan TCP/HTTP tanpa redirect, konkurensi dan korupsi JSON, rate limit, viewer/reset, serta kedaluwarsa session dan batas folder privat.
- Tes browser lulus: login, rendering metrik/grafik dengan fixture sintetis, nama layanan berisi HTML ditampilkan sebagai teks, tambah/edit/hapus layanan, form HTTP, tema, audit, permintaan pemeriksaan, lebar ponsel, logout. Tidak ada page error atau permintaan eksternal.
- Semua file PHP lolos pemeriksaan sintaks; JavaScript lolos `node --check`.
- Ekstensi mysqli dimuat terpisah untuk memeriksa jalur koneksi MySQL gagal: mengembalikan `unavailable` tanpa fatal error. Statistik MySQL sukses belum diuji dengan server MySQL/MariaDB nyata.

Belum diverifikasi di Windows: skrip PowerShell 5.1/CIM dan ACL, PHP XAMPP, konfigurasi Apache/mod_status, Task Scheduler, cookie HTTPS, dan akun monitoring MySQL yang sebenarnya. README memuat langkah pemasangan dan pemeriksaan tersebut.

Tidak ada konfigurasi, password, atau data laptop asli yang dimasukkan ke paket. Port awal mengikuti daftar lama; akun admin harus dibuat melalui CLI.
