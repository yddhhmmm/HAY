# HAY · Dashboard Monitoring Windows

Learn and Do That — dashboard PHP untuk Windows/XAMPP, konfigurasi JSON, tanpa SQLite dan tanpa framework/CDN.

## Fitur

- Login admin dan viewer opsional; hash password, proteksi CSRF, pembatasan login, session idle interaksi 30 menit / maksimum 8 jam (polling tidak memperpanjang idle), dan invalidasi session setelah perubahan akun.
- Tambah/edit/hapus/aktif-nonaktif layanan lokal; pemeriksaan TCP atau kode HTTP, maksimal 20 port unik.
- Tujuan koneksi dikunci ke IPv4/IPv6 loopback; tidak menerima URL bebas, tidak mengikuti redirect, timeout dan ukuran respons dibatasi.
- CIM melalui Windows PowerShell menggantikan WMIC. CPU, RAM, drive lokal, baterai, uptime aktual, OS, dan jumlah proses. Metrik gagal tidak dijadikan nol.
- Collector terpisah: beberapa browser membaca snapshot yang sama; sampling tetap berjalan saat browser ditutup.
- Grafik maksimum 720 sampel CPU/RAM (sekitar 3 jam pada interval 15 detik), penanda data lama setelah 60 detik, dan notifikasi dalam dashboard setelah tiga pemeriksaan berturut-turut untuk layanan gagal / CPU atau RAM >=90% / disk >=90%.
- Apache mod_status pada port 80 dan 8074; MySQL 3306 dengan kredensial monitoring opsional.
- Konfigurasi dan audit JSON privat, backup konfigurasi terakhir, stable lock untuk read/write dan atomic rename; tidak ada kredensial awal bawaan.

## Persyaratan

- Windows 10/11, Windows PowerShell 5.1 dengan CIM, dan XAMPP dengan **PHP 7.3, 7.4, atau PHP 8.x**.
- Apache dengan `mod_authz_core`, izin `.htaccess` (AllowOverride yang sesuai), dan ekstensi PHP session/JSON. `proc_open` diperlukan pada **PHP CLI collector**, bukan untuk menjalankan perintah dari web.
- Ekstensi `mysqli` hanya untuk statistik MySQL; kegagalannya tidak menghentikan metrik lain.
- Tidak perlu menginstal SQLite, Composer, Node.js, atau database baru.

PHP 7.3 dan 7.4 sudah tidak menerima pembaruan keamanan resmi. Dukungan kompatibilitas aplikasi tidak memperbaiki celah pada runtime PHP itu sendiri; pertahankan akses lokal bawaan dan gunakan runtime yang masih didukung jika nantinya perlu layanan jaringan yang lebih luas.

Untuk memperbarui versi lama, salin **seluruh file kode**, termasuk `src/compat.php`, `bin/check-php.php`, dan launcher CMD. Jangan hanya mengganti `bootstrap.php` atau `index.php`. Data `hay-private` tetap dipertahankan. Tidak diperlukan perubahan konfigurasi Apache untuk penyesuaian versi PHP ini.

## Pemasangan XAMPP

Versi ini adalah pengganti tiga file lama dengan struktur baru. Jangan menimpa instalasi lama sebelum memiliki backup. Jangan menyalin `static_cache.json` lama: collector membaca ulang data Windows.

### 1. Salin proyek dan siapkan lokasi privat

Ekstrak folder `HAY` ke:

```text
C:\xampp\htdocs\HAY\
    public\             halaman yang boleh diakses
    src\                backend; bukan halaman publik
    bin\                utilitas CLI dan PowerShell
    tests\              tes pengembangan
```

Data runtime **di luar htdocs**:

```text
C:\xampp\hay-private\
    auth.json           hash password, bukan password asli
    services.json       daftar layanan
    services.json.bak   konfigurasi sebelum perubahan terakhir
    snapshot.json       hasil pengumpulan terakhir
    history.json        maksimum 720 sampel
    audit.json          maksimum 500 catatan
    *.lock              kunci baca/tulis dan collector
```

**Tidak perlu mengedit `httpd.conf` untuk lokasi penyimpanan pada XAMPP standar.** Kode mencari folder `htdocs` di atas lokasi proyek, lalu memilih `hay-private` di sebelahnya. Web, setup akun, dan collector membaca aturan yang sama.

Contoh:

| Folder proyek | Data privat otomatis |
|---|---|
| `C:/xampp/htdocs/HAY` | `C:/xampp/hay-private` |
| `D:/xampp/htdocs/HAY` | `D:/xampp/hay-private` |
| `C:/xampp/htdocs/tools/HAY` | `C:/xampp/hay-private` |

Folder dan file JSON dibuat otomatis ketika setup/collector dijalankan; Anda tidak perlu membuat atau mengisi JSON satu per satu. Folder `hay-private` sengaja di luar `htdocs` supaya file login dan hasil monitoring tidak memiliki URL publik.

**Jika ingin lokasi lain, cukup edit `src/settings.php`:**

```php
return ['storage' => 'D:/data/hay-private'];
```

Gunakan path absolut di luar document root; biarkan `null` untuk deteksi otomatis. Setelah mengubahnya, hentikan collector, pindahkan data privat lama jika ingin mempertahankannya, lalu mulai ulang collector. Semua komponen akan membaca pengaturan ini. Variabel `HAY_STORAGE` hanya override opsional untuk instalasi lanjutan; jangan menyetnya ke lokasi berbeda pada Apache dan CLI. Jika pernah menambahkan SetEnv HAY_STORAGE dari panduan versi awal, hapus override lama atau samakan dengan lokasi yang diinginkan; tidak perlu menambahkannya pada instalasi baru.

`.htaccess` sudah disertakan: folder proyek ditutup dari HTTP kecuali `public`, dan akses public dibatasi ke komputer lokal. XAMPP standar biasanya sudah mengizinkan `.htaccess`. Jika aturan ini dinonaktifkan oleh konfigurasi server yang Anda ubah sendiri, kode PHP tidak dapat mengaktifkannya; konfigurasi Apache perlu diperbaiki atau gunakan document root langsung ke `public`. Jangan menonaktifkan proteksi folder sumber untuk mengatasi error 403.

Folder privat ditolak aplikasi jika terletak di dalam document root. Skrip setup memberi ACL terbatas pada folder privat yang baru dibuat (akun setup, SYSTEM, dan Administrators); ACL folder yang sudah ada tidak ditimpa. Jika Apache/collector memakai akun berbeda, beri izin hanya pada akun tersebut. Periksa NTFS ACL agar hanya akun yang berwenang dapat membaca/menulisnya. Apache/collector tidak perlu dijalankan sebagai Administrator. Izin CIM perlu diverifikasi untuk akun collector yang dipakai.

### 2. Buat akun melalui CMD (tanpa membuka PowerShell)

Buka folder `C:\xampp\htdocs\HAY\bin` di File Explorer. Klik dua kali **`setup.cmd`**, atau buka CMD di folder tersebut dan jalankan:

```bat
setup.cmd
```

Launcher mencari `php.exe` dari folder XAMPP di atas lokasi proyek, kemudian `C:\xampp`, lalu PATH. Jika tidak ditemukan, di CMD yang sama atur lokasi PHP:

```bat
set "HAY_PHP=D:\lokasi-xampp\php\php.exe"
setup.cmd
```

Lokasi folder proyek di drive D tidak berarti XAMPP juga di D. Gunakan lokasi executable PHP yang sebenarnya, minimal versi 7.3; launcher menampilkan versi aktif sebelum setup.

CMD menampilkan **URL setup sementara**. Salin URL tersebut ke browser di laptop yang sama. Isi username (default `admin`), password **12–72 byte**, dan konfirmasi password, lalu simpan. Input password tersembunyi di browser, tidak dimasukkan sebagai argumen CMD. Jangan bagikan URL setup, password, atau screenshot yang memuat token URL.

Server setup hanya mendengarkan `127.0.0.1`, memakai token acak sekali pakai, membatasi Host/Origin, maksimal aktif 10 menit, dan berhenti setelah berhasil. Ini terpisah dari Apache dan tidak membutuhkan pengaturan `httpd.conf`. Biarkan jendela CMD terbuka sampai muncul pesan berhasil; jika terjadi error, jendela berhenti pada `pause` agar pesannya dapat dibaca. `Ctrl+C` membatalkan setup.

Akun sudah ada? Gunakan akun lama atau jalankan berikut **di CMD**, lalu isi form yang baru:

```bat
setup.cmd reset-password
```

Akun viewer opsional:

```bat
setup.cmd set-viewer
```

Folder privat dan file JSON dibuat otomatis. NTFS ACL otomatis hanya diterapkan pada folder privat yang **baru** dibuat, menggunakan utilitas Windows `whoami` dan `icacls`. Folder yang sudah ada tidak diubah ACL-nya. Jangan menjalankan setup sebagai Administrator kecuali ada kebutuhan izin yang terverifikasi.

### 3. Mulai collector melalui CMD

Dari folder `bin`, klik dua kali **`start-collector.cmd`**, atau di CMD:

```bat
start-collector.cmd
```

Biarkan jendelanya terbuka. Pesan selesai akan muncul berkala. Tekan `Ctrl+C` untuk berhenti. Error ditampilkan sebelum jendela ditutup. Tidak perlu menjalankan file `.ps1` secara manual.

**Catatan:** collector masih menggunakan Windows PowerShell/CIM **di belakang layar** untuk membaca metrik Windows. Launcher CMD menghilangkan kebutuhan membuka terminal PowerShell dan menjalankan skrip setup PowerShell, bukan menghapus ketergantungan CIM. Jika kebijakan Windows memblokir skrip metrik, dashboard menampilkan bahwa metrik tidak tersedia; jangan menonaktifkan kebijakan keamanan secara global.

Untuk pengujian satu siklus, dari folder proyek:

```bat
"C:\xampp\php\php.exe" bin\collector.php
```

Buka `http://localhost/HAY/public/` lalu login. Jika Apache menggunakan port berbeda, sesuaikan URL. Tunggu 15–30 detik, lalu periksa nama komputer, uptime, CPU/RAM, drive, dan baterai jika tersedia. Status waktu pembaruan harus berubah selama collector berjalan. Jika gagal, salin pesan error CMD tanpa token atau password.

Launcher `.ps1` lama tetap disediakan untuk pengguna yang memang memilih PowerShell. Gunakan browser modern (Chrome/Edge/Firefox terkini).

### 4. Otomatis mulai setelah Windows menyala

Buat task di **Windows Task Scheduler** untuk langsung menjalankan PHP, tanpa PowerShell:

- Trigger: saat login akun collector; jika perlu sebelum login, gunakan akun layanan dengan hak minimum dan sesuaikan hak CIM/folder.
- Program: `C:\xampp\php\php.exe`
- Arguments: `"C:\xampp\htdocs\HAY\bin\collector.php" --loop`
- Start in: `C:\xampp\htdocs\HAY`
- Pilih **Do not start a new instance**, aktifkan restart setelah gagal, dan sesuaikan batas waktu task agar loop tidak dihentikan setelah beberapa hari.
- Jangan centang “Run with highest privileges” tanpa kebutuhan izin yang terverifikasi.

Gunakan executable dan lokasi proyek yang sebenarnya. Lokasi data mengikuti `src/settings.php` / deteksi otomatis; override `HAY_STORAGE` tetap berlaku jika sengaja diatur. Jika collector berhenti, dashboard menandai snapshot sebagai data lama.

## Pengelolaan layanan

Klik **Tambah layanan**, isi nama, port, TCP atau HTTP, path lokal dan kode HTTP (untuk HTTP). Target selalu `127.0.0.1` atau `::1`. Port duplikat ditolak, termasuk ketika entri lama nonaktif; edit/aktifkan entri tersebut.

- TCP sehat: port menerima koneksi, belum membuktikan aplikasi berfungsi.
- HTTP sehat: respons memiliki kode sesuai harapan. Payload JSON tidak diperiksa.
- HTTP 3xx tidak diikuti; akan dianggap bermasalah jika kode yang diharapkan berbeda.
- Pemeriksaan menggunakan HTTP biasa; layanan HTTPS-only belum didukung.
- Path menerima karakter ASCII terbatas; header, URL absolut, dan path traversal ditolak.
- Perubahan menunggu siklus collector berikutnya. **Periksa sekarang** mengantrekan satu siklus lebih awal pada collector aktif, dengan batas satu permintaan setiap 10 detik; bukan menjalankan shell melalui web.
- Setelah edit, hasil konfigurasi lama tidak dianggap sebagai hasil konfigurasi baru.

Form hanya untuk admin. Akun viewer dibuat melalui `bin/setup.cmd set-viewer`. Viewer dapat melihat data, tetapi tidak mengubah konfigurasi, meminta pemeriksaan, atau membaca audit. Hanya satu admin dan satu viewer opsional didukung. Perubahan akun mengakhiri session lama. Reset admin menggunakan `bin/setup.cmd reset-password`; reset/pembuatan viewer mengganti satu akun viewer yang ada.

## Apache dan MySQL

### Apache (opsional untuk statistik lengkap)

Pengaturan ini hanya diperlukan jika ingin statistik Apache lengkap; login, CPU/RAM, penyimpanan JSON dan pengelolaan layanan tetap dapat digunakan tanpa mengaktifkannya.

Aktifkan `mod_status` dan `ExtendedStatus On` pada masing-masing instance Apache yang dipantau, lalu batasi endpointnya:

```apache
ExtendedStatus On
<Location "/server-status">
    SetHandler server-status
    Require local
</Location>
```

Endpoint: `http://localhost/server-status?auto` dan port `8074`. Hanya aktifkan pada instance yang memang ada. `Tidak tersedia` dapat berarti endpoint dibatasi/tidak dikonfigurasi, bukan Apache mati. Request/detik dan byte/detik dari Apache merupakan rata-rata statistiknya, bukan pengukuran jaringan Windows.

### MySQL 3306

MySQL **bukan penyimpanan dashboard**. Tanpa kredensial, collector hanya memeriksa port dan menampilkan penjelasan bahwa statistik belum dikonfigurasi. Tidak ada lagi koneksi otomatis menggunakan root tanpa password.

Untuk statistik lengkap, buat akun monitoring lokal dengan password kuat, tanpa akses tabel aplikasi. Mulai dari izin USAGE dan uji `SHOW GLOBAL STATUS`; kebutuhan izin bisa berbeda menurut versi MySQL/MariaDB. Jangan memberikan ALL PRIVILEGES untuk monitoring.

Berikan `HAY_MYSQL_USER` dan `HAY_MYSQL_PASSWORD` pada **proses collector** melalui pengaturan environment yang dilindungi. Jangan menaruh password di folder public, argumen task, Git, atau tangkapan layar. Variabel Apache tidak otomatis tersedia pada PHP CLI/Task Scheduler. Setelah konfigurasi berubah, restart collector. Jika akun gagal, dashboard menampilkan kegagalan statistik tanpa membocorkan password.

## Keamanan operasional

- Untuk satu laptop: pertahankan akses lokal. Untuk akses perangkat lain, gunakan VPN privat / pembatasan firewall dan HTTPS. Jangan langsung membuka dashboard atau server-status ke internet.
- Konfigurasi akses Apache bersifat per instalasi. **Uji** file `/HAY/src/bootstrap.php`, `/HAY/bin/windows-metrics.ps1`, dan folder privat ditolak/tidak tersedia lewat browser.
- HTTPS diperlukan agar password/session aman saat melewati jaringan. Cookie Secure otomatis aktif ketika Apache menyatakan HTTPS. Header proxy tidak dipercaya otomatis; konfigurasi TLS/reverse proxy harus dibuat dengan benar oleh administrator.
- CSP membatasi script/style ke file lokal. Tidak ada dependensi CDN, `eval`, atau HTML dari nama layanan pengguna.
- Login dibatasi lima percobaan per alamat IP dalam 15 menit, termasuk username yang salah. Setelah limit tercapai, tunggu atau reset melalui CLI; pembatasan dapat memengaruhi pengguna yang berbagi IP.
- JSON rusak tidak otomatis ditimpa. Hentikan collector, buat salinan seluruh folder privat, kemudian pulihkan `services.json.bak` ke `services.json` jika valid. Backup hanya mencakup versi konfigurasi layanan sebelumnya, bukan backup penuh semua data.
- Cadangkan direktori privat secara terpisah dengan ACL yang sesuai. Simpan hash autentikasi secara privat. Jangan menghapus `.lock` saat proses masih berjalan.
- Histori/audit dibatasi agar file tidak terus tumbuh. Tidak ada notifikasi email/Telegram, sensor suhu/GPU/kipas, penyimpanan histori jangka panjang, atau pengukuran kecepatan internet dalam versi ini.
- Tidak ada klaim kebal serangan. PHP, XAMPP, Windows, dan pengaturan jaringan tetap harus diperbarui dan dipelihara.

## Pengujian pengembangan

Tidak diperlukan Python pada laptop hanya untuk menjalankan aplikasi. Python hanya untuk tes otomatis:

```bash
PHP_BIN=php python3 tests/test_dashboard.py
node --check public/assets/app.js
```

Tes memakai penyimpanan sementara dan server PHP di loopback, tidak menyentuh konfigurasi produksi. Mencakup login/logout, CSRF, CRUD/validasi, backup, JSON rusak, konkurensi penulisan, stale data, perubahan konfigurasi, TCP/HTTP tanpa redirect, rate limit, viewer, dan invalidasi session.

Pengujian Linux tidak membuktikan CIM, PowerShell 5.1, NTFS, Task Scheduler, atau aturan Apache XAMPP berfungsi. Verifikasi checklist pemasangan di Acer diperlukan sebelum penggunaan rutin.

Tes browser opsional: `node tests/browser-smoke.cjs`, memerlukan Playwright dan Chromium pada mesin pengembangan. Gunakan `PHP_BIN` dan `CHROMIUM_BIN` jika executable tidak berada pada lokasi default. Fixture metrik dalam tes browser bersifat sintetis dan bukan hasil pembacaan laptop.

Tes browser wizard setup: `node tests/setup-browser.cjs` (Playwright/Chromium). Alur setup menggunakan server PHP loopback sementara; pengujian Linux tidak menggantikan uji launcher `.cmd` dan ACL di Windows.

Pengujian memakai executable PHP yang dipilih beserta konfigurasi PHP.ini-nya; pastikan JSON dan session aktif seperti pada XAMPP standar. Pada PHP 7.3, helper proses menggunakan quoting argumen Windows dengan `bypass_shell=true`; pada POSIX memakai argumen ter-escape dan `exec` agar penghentian server setup tepat sasaran. Pada PHP 7.4+ digunakan argument array native.
