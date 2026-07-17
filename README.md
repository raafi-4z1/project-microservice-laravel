# Microservice Laravel — School Management System

Arsitektur microservice berbasis Laravel 12 dengan API Gateway terpusat. Seluruh request dari klien/frontend masuk melalui **Gateway**, yang kemudian meneruskannya ke masing-masing service menggunakan autentikasi **HMAC SHA-256**.

## Arsitektur

```
Klien / Frontend
      │
      ▼ Bearer Token (Laravel Passport OAuth2)
┌─────────────┐
│   Gateway   │  https://gateway.test
└─────┬───────┘
      │ HMAC SHA-256 (X-Timestamp + X-Signature + Body)
      ├──────────────► ClassMicroservices  http://classmicroservices.test
      ├──────────────► MapelService        http://mapelservice.test
      ├──────────────► GuruService         http://guruservice.test
      ├──────────────► SiswaService        http://siswaservice.test
      ├──────────────► KaryawanService     http://karyawanservice.test
      └──────────────► AkademikService     http://akademikservice.test
```

| Folder | Domain lokal | Fungsi | README |
|--------|-------------|--------|--------|
| `Gateway` | `gateway.test` | Auth (OAuth2 Passport), routing, RBAC, audit log, **terminal absensi** | [Gateway/README.md](Gateway/README.md) |
| `ClassMicroservices` | `classmicroservices.test` | Manajemen ruang kelas | [ClassMicroservices/README.md](ClassMicroservices/README.md) |
| `MapelService` | `mapelservice.test` | Manajemen mata pelajaran | [MapelService/README.md](MapelService/README.md) |
| `GuruService` | `guruservice.test` | Manajemen data guru + foto + kartu/PIN absensi | [GuruService/README.md](GuruService/README.md) |
| `SiswaService` | `siswaservice.test` | Manajemen data siswa + foto + kartu absensi | [SiswaService/README.md](SiswaService/README.md) |
| `KaryawanService` | `karyawanservice.test` | Manajemen data karyawan + foto + kartu/PIN absensi | [KaryawanService/README.md](KaryawanService/README.md) |
| `AkademikService` | `akademikservice.test` | Pembagian kelas, pengampu mapel, jam & jadwal, nilai, raport, ranking, **absensi** (harian, pelajaran, keluar, rekap) | [AkademikService/README.md](AkademikService/README.md) |

---

## Prasyarat

- **[Laragon](https://laragon.org/download/)** versi Full (sudah termasuk Apache, MySQL, PHP)
- **PHP ≥ 8.2** — cek versi aktif di Laragon: klik kanan ikon Laragon di area notifikasi taskbar → PHP → versi
- **Ekstensi PHP yang wajib aktif:** `pdo_mysql`, `gd`, `mbstring`, `openssl`, `fileinfo`
  - Cek di Laragon: klik kanan ikon Laragon di area notifikasi taskbar → PHP → Extensions
- **Composer ≥ 2** — download di [getcomposer.org](https://getcomposer.org)
- **Git**

---

## Instalasi

### 1. Clone Repository

Clone ke folder manapun di komputer kamu (tidak harus di dalam folder `www` Laragon):

```sh
git clone https://github.com/raafi-4z1/project-microservice-laravel.git
cd project-microservice-laravel
```

---

### 2. Konfigurasi Virtual Host di Laragon

Karena project ini punya 5 service dengan domain berbeda, kamu perlu mendaftarkan setiap service secara manual di Laragon. **Gateway dikonfigurasi HTTPS**, service lain tetap HTTP internal.

> **Catatan:** Konfigurasi HTTPS dengan mkcert ini hanya untuk **pengembangan lokal**.

#### 2a. Generate Sertifikat SSL dengan mkcert (sekali saja)

Laragon versi baru tidak menyertakan server certificate siap pakai. Gunakan **mkcert** untuk generate sertifikat lokal yang dipercaya browser secara otomatis.

**Install mkcert:**

Pilih salah satu cara:

```powershell
# Cara 1 – via Scoop (direkomendasikan jika sudah punya Scoop)
scoop install mkcert

# Cara 2 – via Chocolatey
choco install mkcert

# Cara 3 – download manual
# Buka https://github.com/FiloSottile/mkcert/releases
# Download mkcert-v*-windows-amd64.exe → rename jadi mkcert.exe
# Letakkan file di C:\Windows\System32\ (folder ini sudah ada di PATH secara default)
# Atau letakkan di folder lain, lalu tambahkan folder tersebut ke PATH:
# Settings Windows → "Edit the system environment variables" → Environment Variables
# → pilih "Path" di System Variables → Edit → New → masukkan path folder tersebut
```

**Generate sertifikat untuk `gateway.test`:**

```powershell
# Install CA lokal mkcert ke Windows (sekali saja, perlu dijalankan sebagai Administrator)
mkcert -install

# Buat folder untuk menyimpan sertifikat
mkdir C:\laragon\etc\ssl\mkcert

# Generate sertifikat gateway.test
cd C:\laragon\etc\ssl\mkcert
mkcert gateway.test
```

Perintah di atas menghasilkan dua file:
- `gateway.test.pem` — sertifikat
- `gateway.test-key.pem` — private key

> Setelah `mkcert -install`, browser (Chrome/Edge/Firefox) otomatis mempercayai sertifikat yang dibuat mkcert — tidak perlu install manual ke Windows.

#### 2b. Buat file konfigurasi Virtual Host

1. Klik kanan ikon Laragon di area notifikasi taskbar (pojok kanan bawah layar, di sebelah jam) → **Apache** → **sites-enabled** → folder akan terbuka
2. Buat file baru bernama `microservice.conf` di folder tersebut
3. Isi dengan konfigurasi berikut (sesuaikan path ke lokasi project kamu):

```apache
# Ganti "C:/path/to/project-microservice-laravel" dengan path project kamu
# Contoh: C:/Users/NamaKamu/Documents/project-microservice-laravel

# ─── GATEWAY: HTTP → HTTPS redirect ─────────────────────────────────────────
<VirtualHost *:80>
    ServerName gateway.test
    Redirect permanent / https://gateway.test/
</VirtualHost>

# ─── GATEWAY: HTTPS (satu-satunya yang diakses client/browser) ───────────────
<VirtualHost *:443>
    ServerName gateway.test
    DocumentRoot "C:/path/to/project-microservice-laravel/Gateway/public"
    SSLEngine on
    SSLCertificateFile    "C:/laragon/etc/ssl/mkcert/gateway.test.pem"
    SSLCertificateKeyFile "C:/laragon/etc/ssl/mkcert/gateway.test-key.pem"
    <Directory "C:/path/to/project-microservice-laravel/Gateway/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

# ─── SERVICE INTERNAL: hanya localhost (127.0.0.1), tidak bisa diakses dari LAN ──
<VirtualHost 127.0.0.1:80>
    ServerName classmicroservices.test
    DocumentRoot "C:/path/to/project-microservice-laravel/ClassMicroservices/public"
    <Directory "C:/path/to/project-microservice-laravel/ClassMicroservices/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost 127.0.0.1:80>
    ServerName mapelservice.test
    DocumentRoot "C:/path/to/project-microservice-laravel/MapelService/public"
    <Directory "C:/path/to/project-microservice-laravel/MapelService/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost 127.0.0.1:80>
    ServerName guruservice.test
    DocumentRoot "C:/path/to/project-microservice-laravel/GuruService/public"
    <Directory "C:/path/to/project-microservice-laravel/GuruService/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost 127.0.0.1:80>
    ServerName siswaservice.test
    DocumentRoot "C:/path/to/project-microservice-laravel/SiswaService/public"
    <Directory "C:/path/to/project-microservice-laravel/SiswaService/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost 127.0.0.1:80>
    ServerName akademikservice.test
    DocumentRoot "C:/path/to/project-microservice-laravel/AkademikService/public"
    <Directory "C:/path/to/project-microservice-laravel/AkademikService/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

4. Simpan file, lalu klik kanan ikon Laragon di area notifikasi taskbar → **Reload** (atau restart Apache)

#### 2c. Konfigurasi DNS / hosts file

**Menggunakan Laragon:** Laragon memiliki DNS server built-in yang secara otomatis me-resolve semua domain `*.test` ke `127.0.0.1`. Kamu tidak perlu mengedit file `hosts` secara manual — Laragon menanganinya.

Jika domain `.test` tidak terbaca (error "server not found") meskipun sudah di-reload, DNS Laragon mungkin tidak aktif. Tambahkan baris berikut ke file `C:\Windows\System32\drivers\etc\hosts` secara manual (perlu dibuka sebagai Administrator):

```
127.0.0.1    gateway.test
127.0.0.1    classmicroservices.test
127.0.0.1    mapelservice.test
127.0.0.1    guruservice.test
127.0.0.1    siswaservice.test
127.0.0.1    akademikservice.test
```

**Menggunakan web server lain (IIS, XAMPP, Nginx standalone, dll.):** Web server selain Laragon tidak memiliki DNS management otomatis. Kamu **wajib** menambahkan entri hosts di atas secara manual agar domain `.test` dapat dikenali sistem.

---

### 3. Setup Gateway

Buka terminal di folder `Gateway`:

```sh
cd Gateway
composer install
cp .env.example .env
```

Edit file `.env` dan isi bagian berikut:

```env
APP_URL=https://gateway.test

SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gateway_db
DB_USERNAME=root
DB_PASSWORD=

# Secret HMAC per service (nilai di bawah adalah default)
CLASS_SERVICE_BASE_URL=http://classmicroservices.test
CLASS_SERVICE_SECRET=base64:...

MAPEL_SERVICE_BASE_URL=http://mapelservice.test
MAPEL_SERVICE_SECRET=base64:...

GURU_SERVICE_BASE_URL=http://guruservice.test
GURU_SERVICE_SECRET=base64:...

SISWA_SERVICE_BASE_URL=http://siswaservice.test
SISWA_SERVICE_SECRET=base64:...

AKADEMIK_SERVICE_BASE_URL=http://akademikservice.test
AKADEMIK_SERVICE_SECRET=base64:...

# Kredensial akun SuperAdmin pertama (dipakai oleh seeder)
SUPERADMIN_NAME="Nama Admin Kamu"
SUPERADMIN_EMAIL="email_kamu@domain.com"
SUPERADMIN_PASSWORD="MinimalDuabelasKarakter1"
```

> **Penting:**
> - `SUPERADMIN_EMAIL` dan `SUPERADMIN_PASSWORD` wajib diganti — seeder akan **gagal** jika masih menggunakan nilai default atau password kurang dari 12 karakter
> - Ganti juga semua nilai `*_SERVICE_SECRET` dengan secret baru (lihat [Catatan Keamanan HMAC](#catatan-keamanan-hmac))

Jalankan setup:

```sh
php artisan key:generate
php artisan migrate
php artisan passport:keys
php artisan passport:client --personal
php artisan db:seed
```

---

### 4. Setup ClassMicroservices

```sh
cd ../ClassMicroservices
composer install
cp .env.example .env
```

Edit `.env`:

```env
APP_URL=http://classmicroservices.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=class_db
DB_USERNAME=root
DB_PASSWORD=

# Harus sama persis dengan CLASS_SERVICE_SECRET di Gateway
ACCEPTED_SECRETS=base64:...
```

```sh
php artisan key:generate
php artisan migrate
```

---

### 5. Setup MapelService

```sh
cd ../MapelService
composer install
cp .env.example .env
```

Edit `.env`:

```env
APP_URL=http://mapelservice.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mapel_db
DB_USERNAME=root
DB_PASSWORD=

# Harus sama persis dengan MAPEL_SERVICE_SECRET di Gateway
ACCEPTED_SECRETS=base64:...
```

```sh
php artisan key:generate
php artisan migrate
```

---

### 6. Setup GuruService

```sh
cd ../GuruService
composer install
cp .env.example .env
```

Edit `.env`:

```env
APP_URL=http://guruservice.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=guru_db
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=private

# Harus sama persis dengan GURU_SERVICE_SECRET di Gateway
ACCEPTED_SECRETS=base64:...
```

```sh
php artisan key:generate
php artisan migrate
```

---

### 7. Setup SiswaService

```sh
cd ../SiswaService
composer install
cp .env.example .env
```

Edit `.env`:

```env
APP_URL=http://siswaservice.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=siswa_db
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=private

# Harus sama persis dengan SISWA_SERVICE_SECRET di Gateway
ACCEPTED_SECRETS=base64:...
```

```sh
php artisan key:generate
php artisan migrate
```

---

### 8. Setup AkademikService

```sh
cd ../AkademikService
composer install
cp .env.example .env
```

Edit `.env`:

```env
APP_URL=http://akademikservice.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=akademik_db
DB_USERNAME=root
DB_PASSWORD=

# Harus sama persis dengan AKADEMIK_SERVICE_SECRET di Gateway
ACCEPTED_SECRETS=base64:...
```

```sh
php artisan key:generate
php artisan migrate
```

---

### 9. Verifikasi

Buka browser dan akses:
- `https://gateway.test/api/login` — harus memunculkan respons JSON (bukan halaman error 404/500)
- Jika muncul halaman "403 Forbidden" atau "404", cek kembali konfigurasi virtual host dan pastikan Laragon sudah di-reload
- Jika muncul peringatan SSL "Not Secure", pastikan langkah 2a (install CA mkcert) sudah dijalankan

---

## Catatan Keamanan HMAC

Setiap service hanya bisa diakses dari Gateway, bukan langsung dari klien. Mekanismenya:

- Gateway menambahkan header `X-Timestamp` dan `X-Signature` di setiap request ke service
- Signature mencakup: `HMAC-SHA256(secret, timestamp + body)` — untuk GET, query string ikut di-sign; DELETE menggunakan ID di path
- Service memverifikasi signature dengan `ACCEPTED_SECRETS`
- Request lebih dari 5 menit akan ditolak (replay protection)

**Nilai `ACCEPTED_SECRETS` di setiap service harus sama persis dengan nilai `*_SERVICE_SECRET` di Gateway yang terhubung.**

Untuk generate secret baru:

```sh
cd Gateway
php artisan tinker
>>> echo base64_encode(random_bytes(32));
```

---

## Testing dengan Postman

1. Import file `Microservice Laravel.postman_collection.json` ke Postman
2. Buka tab **Variables** collection — pastikan:
   - `baseURL` = `https://gateway.test/api`
   - `superadmin_email` dan `superadmin_password` diisi di kolom **Current Value**
3. Di Settings Postman → centang **"SSL certificate verification" → Off** (karena sertifikat lokal mkcert)
4. Jalankan request **Login** — token tersimpan otomatis ke `{{token}}`
5. Semua request lain sudah menggunakan `{{token}}` secara otomatis

### Urutan test yang direkomendasikan

```
1.  Auth → Login
2.  Auth → Register (buat akun Admin / Guru / Siswa baru)
3.  Mata Pelajaran → Tambah → GET All → GET by ID → Update → Hapus
4.  Kelas → Tambah → GET All → GET by ID → Update → Hapus
5.  Guru → Tambah (dengan foto) → GET All → GET by ID → Update → Hapus
6.  Siswa → Tambah (dengan foto) → GET All → GET by ID → Update → Hapus
7.  Akademik (Semester) → GET Semester Aktif → Set Semester Aktif
8.  Akademik (Kelas) → Assign Siswa ke Kelas → GET Siswa di Kelas → Pindah Kelas
9.  Akademik (Pengampu) → Assign Guru Pengampu → GET Mapel Guru → Ganti Guru Pengampu
10. Akademik (Jam) → Tambah Slot Jam → GET Semua Jam → Update Jam
11. Akademik (Jadwal) → Buat Jadwal → GET Jadwal Kelas → GET Jadwal Guru → Update → Hapus
12. Akademik (Pengaturan Nilai) → Tambah → GET → Update
13. Akademik (Nilai) → Tambah Nilai → Update Nilai → GET by Pengampu → GET by Kelas
14. Akademik (Raport) → GET Raport Siswa → GET Raport Kelas → GET Ranking Kelas
15. Auth → Logout
```

Untuk detail endpoint per service, lihat README masing-masing service:

- [Gateway/README.md](Gateway/README.md) — Auth, User Management, Audit Log, Kartu & Terminal Absensi
- [ClassMicroservices/README.md](ClassMicroservices/README.md) — Ruang Kelas
- [MapelService/README.md](MapelService/README.md) — Mata Pelajaran
- [GuruService/README.md](GuruService/README.md) — Guru + kartu/PIN
- [SiswaService/README.md](SiswaService/README.md) — Siswa + kartu
- [KaryawanService/README.md](KaryawanService/README.md) — Karyawan + kartu/PIN
- [AkademikService/README.md](AkademikService/README.md) — Semester, Kelas, Pengampu, Jam, Jadwal, Nilai, Raport, Absensi (harian/pelajaran/keluar/rekap)

### Testing Otomatis (PowerShell)

Selain Postman, tersedia `run-tests.ps1` — suite end-to-end (186 skenario)
mencakup auth, CRUD semua service, akademik, **absensi** (kartu/QR, keluar,
rekap, jendela PIN, wali kelas, autentikasi terminal), RBAC 4 role, sesi
multi-device, dan validasi cross-service. Kredensial dibaca dari environment variable
(tidak ditulis di file).

```powershell
$env:TEST_ADMIN_PASSWORD = "PasswordSuperAdmin"
powershell -ExecutionPolicy Bypass -File run-tests.ps1
```

Uji **scan kartu di terminal** (opsional) — daftarkan terminal dulu
(`php artisan terminal:register` di Gateway), lalu set:

```powershell
$env:TEST_TERMINAL_ID    = "1"
$env:TEST_TERMINAL_TOKEN = "term_xxx"   # ditampilkan sekali saat register
$env:TEST_TERMINAL_LAT   = "-6.200000"  # untuk terminal mode demo (geofence)
$env:TEST_TERMINAL_LNG   = "106.816666"
```

Tanpa variabel ini, uji scan positif otomatis di-*skip* (uji negatif tanpa
terminal → 401 tetap jalan).

Menguji dari PC lain di LAN — arahkan ke IP server (tambahkan hosts entry
`<IP-server> gateway.test` di PC penguji agar Host header benar):

```powershell
$env:TEST_BASE_URL       = "https://192.168.x.x/api"
$env:TEST_ADMIN_PASSWORD = "PasswordSuperAdmin"
powershell -ExecutionPolicy Bypass -File run-tests.ps1
```

---

## Membangun App Android (klien)

App Android dibangun dari spesifikasi (`android-app-prompt.md`) + panduan fase
(`android-build-phases.md`) memakai Claude Code — tidak ada kode Android di repo
ini. Untuk merakit folder dokumen yang dibutuhkan:

```powershell
# generate dulu referensi DTO (berisi akun test — gitignored)
$env:TEST_ADMIN_PASSWORD = "PasswordSuperAdmin"
powershell -ExecutionPolicy Bypass -File capture-api-samples.ps1

# rakit bundle project Android (buat folder + CLAUDE.md + .gitignore)
powershell -ExecutionPolicy Bypass -File setup-android-project.ps1
```

Menghasilkan `../sim-sekolah-android/` berisi `CLAUDE.md` + `docs/`
(android-app-prompt, android-build-phases, api-sample-responses, Postman,
ui-ux/). Lalu buka di Android Studio dan jalankan fase demi fase dari
`docs/android-build-phases.md`. Catatan: `api-sample-responses.md` berisi akun
test → dikirim lewat jalur lain, jangan commit.

---

## Maintenance / Operasional

### Auto-alpa Absensi (wajib dijadwalkan)

Siswa yang tidak punya catatan absensi pada hari sekolah ditandai `alpa`
otomatis. **Tidak berjalan sendiri** — daftarkan lewat **Windows Task Scheduler**
sebagai tugas harian tiap sore setelah jam pulang (mis. 15:00), menjalankan:

```
php artisan absensi:tandai-alpa
```

dari folder `AkademikService`. Uji dulu dengan `--dry-run` (tidak menyimpan apa pun).

Otomatis melewati akhir pekan dan tanggal yang masuk **periode libur**, serta
tidak menyentuh siswa yang sudah punya catatan (hadir/terlambat/izin/sakit).
Idempotent — aman jalan berkali-kali. Isi kalender libur & periode khusus
(Ramadan/pekan ujian) via `POST /akademik/periode` — lihat
[AkademikService/README.md](AkademikService/README.md).

### Backup Database

`backup-databases.ps1` mem-backup keenam database (nama & kredensial dibaca dari
`.env` tiap service), kompres ke `.zip` bertanggal, lalu bersihkan backup lama.

```powershell
# Harian — pemulihan bencana; retensi 14 hari, auto-hapus. Disimpan di db-backups\
powershell -ExecutionPolicy Bypass -File backup-databases.ps1

# Per semester — arsip permanen; diberi label semester aktif (dibaca dari DB),
# disimpan di db-backups\archive\, TIDAK pernah dihapus otomatis.
# Jalankan saat menutup/ganti semester.
$env:BACKUP_MODE = "semester"; powershell -ExecutionPolicy Bypass -File backup-databases.ps1
```

Opsi: `$env:BACKUP_DIR` (lokasi backup — idealnya drive/lokasi terpisah),
`$env:RETENTION_DAYS` (default 14, mode harian saja).

**Jadwalkan backup harian** via Task Scheduler:

```powershell
schtasks /create /tn "Backup SIM Sekolah" /sc daily /st 02:00 ^
  /tr "powershell -ExecutionPolicy Bypass -File C:\path\ke\backup-databases.ps1"
```

> ⚠️ MySQL harus **berjalan** saat backup. Agar backup terjadwal (mis. 02:00)
> selalu berhasil meski Laragon belum dibuka, jadikan MySQL auto-start:
> Laragon → Menu → **MySQL → Install service** (atau Preferences → auto-start).

Output `db-backups/` berisi data dan sudah di-`.gitignore`.

### Rotasi Log

Log Laravel dikonfigurasi rotasi **harian**, retensi **14 hari** (channel `daily`
di `config/logging.php` semua service). File menjadi `laravel-YYYY-MM-DD.log`
dan terhapus otomatis setelah 14 hari. Ubah retensi via `LOG_DAILY_DAYS` di `.env`
bila perlu.

---

## Referensi

- Inspirasi arsitektur: [ismail17719/apigateway-based-microservices-in-laravel-and-lumen](https://github.com/ismail17719/apigateway-based-microservices-in-laravel-and-lumen)
