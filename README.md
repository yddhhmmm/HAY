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

- Windows 10/11, Windows PowerShell 5.1 dengan CIM, dan XAMPP dengan **PHP 8.1 atau lebih baru** (disarankan versi PHP yang masih menerima pembaruan keamanan).
- Apache dengan `mod_authz_core`, izin `.htaccess` (AllowOverride yang sesuai), dan ekstensi PHP session/JSON. `proc_open` diperlukan pada **PHP CLI collector**, bukan untuk menjalankan perintah dari web.
- Ekstensi `mysqli` hanya untuk statistik MySQL; kegagalannya tidak menghentikan metrik lain.
- Tidak perlu menginstal SQLite, Composer, Node.js, atau database baru.

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

Default web memilih `hay-private` di sebelah document root Apache. Untuk XAMPP standar `C:/xampp/htdocs`, hasilnya `C:/xampp/hay-private`. **Atur lokasi secara eksplisit** agar web dan CLI menggunakan direktori sama.

Tambahkan pada konfigurasi Apache yang sesuai, misalnya `httpd.conf`:

```apache
SetEnv HAY_STORAGE "C:/xampp/hay-private"

<Directory "C:/xampp/htdocs/HAY">
    AllowOverride AuthConfig Options
    Require all denied
</Directory>
<Directory "C:/xampp/htdocs/HAY/public">
    Require local
</Directory>
```

`public/.htaccess` yang disertakan sudah membatasi akses dengan `Require local`. Alternatif yang lebih bersih adalah VirtualHost dengan DocumentRoot langsung ke folder `public`, `Require local`, dan `AllowOverride None`; header keamanan tetap dikirim PHP. Sesuaikan nama/port VirtualHost dengan Apache Anda tanpa mengubah konfigurasi aplikasi lain.

**Jangan** menjalankan server dengan seluruh proyek sebagai document root jika aturan penolakan akses tidak aktif. File sumber, bin, dan tests tidak boleh tersedia lewat HTTP. `.htaccess` tidak berlaku pada server selain Apache.

Folder privat ditolak aplikasi jika terletak di dalam document root. Skrip setup memberi ACL terbatas pada folder privat yang baru dibuat (akun setup, SYSTEM, dan Administrators); ACL folder yang sudah ada tidak ditimpa. Jika Apache/collector memakai akun berbeda, beri izin hanya pada akun tersebut. Periksa NTFS ACL agar hanya akun Windows yang menjalankan Apache/collector dan administrator yang berwenang dapat membaca/menulisnya; `chmod` PHP tidak menggantikan NTFS ACL. Apache/collector tidak perlu dijalankan sebagai Administrator. Izin minimum pada CIM perlu diverifikasi untuk akun collector yang dipakai.

### 2. Buat akun admin

Buka Windows PowerShell, gunakan akun Windows yang akan menjalankan collector:

```powershell
cd C:\xampp\htdocs\HAY
.\bin\setup.ps1
```

Masukkan password **12–72 byte** (gunakan passphrase panjang). Input disembunyikan dan dikirim melalui stdin, bukan argumen proses. Tidak ada password default. Jika lokasi XAMPP berbeda:

```powershell
.\bin\setup.ps1 -Php 'D:\xampp\php\php.exe' -Storage 'D:\xampp\hay-private' -Username 'pemilik'
```

Samakan `Storage` dengan `HAY_STORAGE` Apache. Restart Apache setelah mengubah konfigurasinya.

Jika kebijakan Windows menolak skrip, periksa/tandatangani skrip sesuai kebijakan komputer/organisasi. Jangan mematikan kebijakan verifikasi secara global. File hasil unduhan mungkin memerlukan peninjauan dan penghapusan tanda blokir melalui Properties setelah Anda memeriksa sumbernya.

### 3. Jalankan collector

Di PowerShell:

```powershell
cd C:\xampp\htdocs\HAY
.\bin\start-collector.ps1
```

Biarkan terminal berjalan. Collector mengambil satu siklus setiap kurang lebih 15 detik; jika pengumpulan lebih lama, siklus tidak ditumpuk. `Ctrl+C` menghentikannya. Hanya satu collector diperbolehkan untuk direktori penyimpanan yang sama.

Untuk pengujian satu siklus:

```powershell
$env:HAY_STORAGE = 'C:\xampp\hay-private'
& 'C:\xampp\php\php.exe' '.\bin\collector.php'
```

Buka `http://localhost/HAY/public/` lalu login. Verifikasi nama komputer, uptime, CPU/RAM, drive, baterai (jika tersedia), dan waktu pembaruan. Pembacaan pertama dapat memerlukan waktu; jika CIM gagal, panel catatan menunjukkan kelompok data yang tidak tersedia.

Pada browser tanpa dukungan `AbortSignal.timeout`, gunakan browser modern yang didukung (Chrome/Edge/Firefox versi terkini).

### 4. Otomatis mulai setelah Windows menyala

Buat task di **Windows Task Scheduler**:

- Trigger: saat login akun collector; jika perlu sebelum login, gunakan akun layanan dengan hak minimum dan sesuaikan hak CIM/folder.
- Program: `C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe`
- Arguments: `-NoProfile -NonInteractive -File "C:\xampp\htdocs\HAY\bin\start-collector.ps1"`
- Start in: `C:\xampp\htdocs\HAY`
- Pilih **Do not start a new instance**, aktifkan restart setelah gagal, dan sesuaikan batas waktu task agar loop tidak dihentikan setelah beberapa hari.
- Jangan centang “Run with highest privileges” tanpa alasan kebutuhan izin yang terverifikasi.

Jika menggunakan lokasi nonstandar, tambahkan `-Php "..." -Storage "..."` pada argumen skrip. Task harus menggunakan lokasi dan akun dengan izin yang sama. Jika collector berhenti, dashboard tetap dapat dibuka dan menandai snapshot sebagai data lama.

## Pengelolaan layanan

Klik **Tambah layanan**, isi nama, port, TCP atau HTTP, path lokal dan kode HTTP (untuk HTTP). Target selalu `127.0.0.1` atau `::1`. Port duplikat ditolak, termasuk ketika entri lama nonaktif; edit/aktifkan entri tersebut.

- TCP sehat: port menerima koneksi, belum membuktikan aplikasi berfungsi.
- HTTP sehat: respons memiliki kode sesuai harapan. Payload JSON tidak diperiksa.
- HTTP 3xx tidak diikuti; akan dianggap bermasalah jika kode yang diharapkan berbeda.
- Pemeriksaan menggunakan HTTP biasa; layanan HTTPS-only belum didukung.
- Path menerima karakter ASCII terbatas; header, URL absolut, dan path traversal ditolak.
- Perubahan menunggu siklus collector berikutnya. **Periksa sekarang** mengantrekan satu siklus lebih awal pada collector aktif, dengan batas satu permintaan setiap 10 detik; bukan menjalankan shell melalui web.
- Setelah edit, hasil konfigurasi lama tidak dianggap sebagai hasil konfigurasi baru.

Form hanya untuk admin. Untuk viewer opsional:

```powershell
.\bin\setup.ps1 -Viewer -Username 'pengamat'
```

Viewer dapat melihat data, tetapi tidak mengubah konfigurasi, meminta pemeriksaan, atau membaca audit. Perubahan akun mengakhiri session lama. Hanya satu admin dan satu viewer opsional didukung.

Reset password melalui terminal lokal:

```powershell
.\bin\setup.ps1 -ResetPassword -Username 'admin'
```

Gunakan nama admin yang Anda inginkan. Pembuatan/reset viewer mengganti satu akun viewer yang ada. Lupa password tidak memerlukan endpoint pemulihan publik.

## Apache dan MySQL

### Apache

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
