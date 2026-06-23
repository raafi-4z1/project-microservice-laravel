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
      └──────────────► AkademikService     http://akademikservice.test
```

| Folder | Domain lokal | Fungsi |
|--------|-------------|--------|
| `Gateway` | `gateway.test` | Auth (OAuth2 Passport), routing, RBAC, audit log |
| `ClassMicroservices` | `classmicroservices.test` | Manajemen ruang kelas |
| `MapelService` | `mapelservice.test` | Manajemen mata pelajaran |
| `GuruService` | `guruservice.test` | Manajemen data guru + foto |
| `SiswaService` | `siswaservice.test` | Manajemen data siswa + foto |
| `AkademikService` | `akademikservice.test` | Pembagian kelas, pengampu mapel, jam & jadwal pelajaran, riwayat akademik, semester aktif |

---

## Prasyarat

- **[Laragon](https://laragon.org/download/)** versi Full (sudah termasuk Apache, MySQL, PHP)
- **PHP ≥ 8.2** — cek versi aktif di Laragon: klik kanan tray → PHP → versi
- **Ekstensi PHP yang wajib aktif:** `pdo_mysql`, `gd`, `mbstring`, `openssl`, `fileinfo`
  - Cek di Laragon: klik kanan tray → PHP → Extensions
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
# Download mkcert-v*-windows-amd64.exe → rename jadi mkcert.exe → taruh di folder yang ada di PATH
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

1. Klik kanan ikon Laragon di system tray → **Apache** → **sites-enabled** → folder akan terbuka
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

4. Simpan file, lalu klik kanan Laragon tray → **Reload** (atau restart Apache)

> **Catatan:** Laragon menangani file `hosts` dan domain `.test` secara otomatis. Kamu tidak perlu mengedit file `hosts` secara manual.

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
CLASS_SERVICE_SECRET=base64:uUTtmBL1ZmUdIOtGSx+2uWQuYg1MdGWnyZb1AC4W/go=

MAPEL_SERVICE_BASE_URL=http://mapelservice.test
MAPEL_SERVICE_SECRET=base64:tV2U1JsoTvOqIgaDJXb1aHrmAhnGW0uvs/tY9h4xuCE=

GURU_SERVICE_BASE_URL=http://guruservice.test
GURU_SERVICE_SECRET=base64:bIah+HgRXoDF2xOZx6VqQHwPAi9Qn8EL+odWRRAC4LA=

SISWA_SERVICE_BASE_URL=http://siswaservice.test
SISWA_SERVICE_SECRET=base64:9sFQ/3POZdTj36SAka4tl76ZOBEw28KCqbUGFch/iPw=

AKADEMIK_SERVICE_BASE_URL=http://akademikservice.test
AKADEMIK_SERVICE_SECRET=base64:+ZnOcffL/GId4hrnVT4YCWG8f/E8woMi8lSlRsOiZBQ=

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
ACCEPTED_SECRETS=base64:uUTtmBL1ZmUdIOtGSx+2uWQuYg1MdGWnyZb1AC4W/go=
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
ACCEPTED_SECRETS=base64:tV2U1JsoTvOqIgaDJXb1aHrmAhnGW0uvs/tY9h4xuCE=
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
ACCEPTED_SECRETS=base64:bIah+HgRXoDF2xOZx6VqQHwPAi9Qn8EL+odWRRAC4LA=
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
ACCEPTED_SECRETS=base64:9sFQ/3POZdTj36SAka4tl76ZOBEw28KCqbUGFch/iPw=
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
ACCEPTED_SECRETS=base64:+ZnOcffL/GId4hrnVT4YCWG8f/E8woMi8lSlRsOiZBQ=
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

## Keamanan Aplikasi

### Registrasi & Kontrol Role

- `/register` hanya dapat diakses oleh **SuperAdmin** atau **Admin** (wajib menyertakan Bearer token)
- **SuperAdmin** dapat mendaftarkan: `Admin`, `Guru`, `Siswa`, `Karyawan`
- **Admin** hanya dapat mendaftarkan: `Guru`, `Siswa`, `Karyawan`
- Role `SuperAdmin` tidak dapat dibuat melalui API — hanya via `php artisan db:seed`

### Proteksi Hapus Akun User (`/users/{id}`)

| Kondisi | Hasil |
|---------|-------|
| Admin menghapus Guru / Siswa / Karyawan | ✅ Diizinkan |
| Admin menghapus Admin lain | ❌ 403 |
| Admin menghapus SuperAdmin | ❌ 403 |
| SuperAdmin menghapus Admin / Guru / Siswa / Karyawan | ✅ Diizinkan |
| Siapapun menghapus SuperAdmin | ❌ 403 (tidak bisa via API) |
| Menghapus akun sendiri | ❌ 403 |

- Hapus bersifat **soft delete** — kolom `deleted_at` terisi, data tetap di database
- Token aktif milik user yang dihapus **langsung dicabut** saat dihapus

### Ubah & Reset Password

| Endpoint | Siapa | Keterangan |
|----------|-------|------------|
| `POST /password` | Semua user | Ganti password **sendiri** — wajib kirim `current_password` |
| `POST /users/{id}/password` | SuperAdmin, Admin | Reset password **user lain** — tidak perlu password lama |

- `new_password`: min 8 karakter, harus mengandung huruf dan angka
- `confirm_password`: harus sama dengan `new_password`
- `new_password` tidak boleh sama dengan `current_password`
- Saat admin reset password orang lain, **semua token aktif target langsung dicabut** (paksa login ulang)
- Admin hanya dapat reset password Guru, Siswa, Karyawan — tidak bisa reset Admin/SuperAdmin
- Password SuperAdmin tidak dapat direset melalui API (hanya via database langsung)

### Token & Sesi

- Token OAuth2 (Bearer) berlaku selama **8 jam**, refresh token **30 hari**
- Login baru **mencabut semua token lama** — tidak ada concurrent session
- Endpoint `/login` dibatasi **5 percobaan per menit** (throttle)
- Endpoint `/oauth/token` (Passport token issuance) juga dibatasi **5 percobaan per menit** — mencegah bypass brute force melalui OAuth endpoint langsung

### Audit Log

Semua aksi tulis (create, update, delete, login, register) dicatat di tabel `audit_logs` di database Gateway.

| Kolom | Isi |
|-------|-----|
| `action` | `login` / `created` / `updated` / `deleted` / `registered` |
| `resource` | `guru` / `siswa` / `mapel` / `kelas` / `user` |
| `resource_id` | ID record yang diubah |
| `performed_by` | Email pelaku aksi |
| `role` | Role pelaku |
| `ip_address` | IP address pengirim request |
| `payload` | Data yang dikirim (foto dan password otomatis disanitasi) |

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
1.  Auth → Login (isi variabel superadmin_email & superadmin_password di tab Variables)
2.  Auth → Register (buat akun Admin / Guru / Siswa baru)
3.  Auth → Ganti Password Sendiri
4.  Manajemen User → GET Semua User → GET User by ID → Reset Password User → Hapus User
5.  Mata Pelajaran → Tambah → GET All → GET by ID → Update → Hapus
6.  Kelas → Tambah → GET All → GET by ID → Update → Hapus
7.  Guru → Tambah (dengan foto) → GET All → GET by ID → Update → Hapus
8.  Siswa → Tambah (dengan foto) → GET All → GET by ID → Update → Hapus
9.  Akademik (Semester) → GET Semester Aktif → Set Semester Aktif → GET Riwayat Semester
10. Akademik (Kelas) → Assign Siswa ke Kelas → GET Siswa di Kelas → Pindah Kelas → GET Riwayat Kelas Siswa → GET Riwayat Kelas
11. Akademik (Pengampu) → Assign Guru Pengampu → GET Mapel Guru → Ganti Guru Pengampu → GET Riwayat Mapel Guru → Hapus Pengampu
12. Akademik (Jam) → Tambah Slot Jam → GET Semua Jam → Update Jam
13. Akademik (Jadwal) → Buat Jadwal → GET Jadwal Kelas → GET Jadwal Guru → Update Jadwal → Hapus Jadwal → GET Riwayat Jadwal
14. Auth → Logout
```

### Role & Akses

| Role | GET aktif | GET riwayat† | POST / PATCH (write) | DELETE | Register |
|------|-----------|--------------|----------------------|--------|----------|
| SuperAdmin | ✅ | ✅ | ✅ | ✅ | ✅ (Admin/Guru/Siswa/Karyawan) |
| Admin | ✅ | ✅ | ✅ | ✅* | ✅ (Guru/Siswa/Karyawan) |
| Guru / Siswa / Karyawan | ✅ | ❌ 403 | ❌ 403 | ❌ 403 | ❌ 403 |

> † Endpoint riwayat (`/akademik/siswa/{id}/kelas/riwayat`, `/akademik/kelas/{id}/siswa/riwayat`, `/akademik/guru/{id}/mapel/riwayat`, `/akademik/mapel/{id}/guru/riwayat`) hanya dapat diakses oleh **SuperAdmin** dan **Admin** — berisi data soft-deleted yang merupakan catatan historis sekolah.

> \* Proteksi DELETE untuk Admin berlaku pada:
> - **`/users/{id}`** — Admin tidak dapat menghapus **akun user** (hasil `/register`) yang memiliki role Admin atau SuperAdmin
> - **`/guru`** dan **`/siswa`** — Admin tidak dapat menghapus data guru/siswa jika email-nya terdaftar sebagai akun Admin/SuperAdmin di Gateway
>
> Untuk **`/mapel`** dan **`/class`**: tidak ada proteksi berdasarkan siapa yang membuat — semua Admin dapat menghapus data apapun.

### Ketentuan Upload Foto (Guru & Siswa)

- Format: **JPEG, PNG, atau JPG**
- Ukuran maksimal: **2 MB**
- Dimensi sumber: minimal **360×480 px**
- Foto dikonversi otomatis ke rasio **3:4 portrait (360×480 px)** dan disimpan sebagai **WebP** (kualitas 85)

---

## API Endpoints (via Gateway)

Base URL: `https://gateway.test/api`

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| POST | `/login` | Publik | Login, max 5x/menit |
| POST | `/logout` | Semua | Cabut token |
| GET | `/user` | Semua | Info user yang sedang login |
| POST | `/password` | Semua | Ganti password sendiri (butuh `current_password`) |
| POST | `/register` | SuperAdmin, Admin | Daftar akun baru (wajib login) |
| GET | `/users` | SuperAdmin, Admin | List semua akun user |
| GET | `/users/{id}` | SuperAdmin, Admin | Detail akun user by ID |
| POST | `/users/{id}/password` | SuperAdmin, Admin | Reset password user lain (token target dicabut) |
| DELETE | `/users/{id}` | SuperAdmin, Admin | Hapus akun user (soft delete) |
| GET | `/mapel/all` | Semua | List mata pelajaran |
| GET | `/mapel` | Semua | Detail mapel by `idPelajaran` |
| POST | `/mapel` | SuperAdmin, Admin | Tambah mapel |
| POST | `/mapel/update` | SuperAdmin, Admin | Update mapel |
| DELETE | `/mapel/{id}` | SuperAdmin, Admin | Hapus mapel |
| GET | `/class/all` | Semua | List kelas |
| GET | `/class` | Semua | Detail kelas by `idKelas` |
| POST | `/class` | SuperAdmin, Admin | Tambah kelas |
| POST | `/class/update` | SuperAdmin, Admin | Update kelas |
| DELETE | `/class/{id}` | SuperAdmin, Admin | Hapus kelas |
| GET | `/guru/all` | Semua | List guru |
| GET | `/guru` | Semua | Detail guru by `idGuru` |
| POST | `/guru` | SuperAdmin, Admin | Tambah guru + foto |
| POST | `/guru/update` | SuperAdmin, Admin | Update guru + foto opsional |
| DELETE | `/guru/{id}` | SuperAdmin, Admin | Hapus guru |
| GET | `/siswa/all` | Semua | List siswa |
| GET | `/siswa` | Semua | Detail siswa by `idSiswa` |
| POST | `/siswa` | SuperAdmin, Admin | Tambah siswa + foto |
| POST | `/siswa/update` | SuperAdmin, Admin | Update siswa + foto opsional |
| DELETE | `/siswa/{id}` | SuperAdmin, Admin | Hapus siswa |
| GET | `/akademik/semester/aktif` | Semua | Tahun ajaran & semester yang sedang berlangsung |
| POST | `/akademik/semester/aktif` | SuperAdmin, Admin | Set semester aktif baru (semester lama otomatis ditutup) |
| GET | `/akademik/semester/riwayat` | Semua | Riwayat semua semester akademik |
| POST | `/akademik/kelas/assign` | SuperAdmin, Admin | Daftarkan siswa ke kelas |
| PATCH | `/akademik/kelas/assign/{id}` | SuperAdmin, Admin | Pindah kelas siswa (dalam semester yang sama) |
| DELETE | `/akademik/kelas/assign/{id}` | SuperAdmin, Admin | Keluarkan siswa dari kelas |
| GET | `/akademik/kelas/{id}/siswa` | Semua | List siswa aktif dalam kelas |
| GET | `/akademik/siswa/{id}/kelas` | Semua | Kelas aktif siswa per semester |
| GET | `/akademik/kelas/{id}/siswa/riwayat` | SuperAdmin, Admin | Semua siswa pernah di kelas (termasuk yang dipindah) |
| GET | `/akademik/siswa/{id}/kelas/riwayat` | SuperAdmin, Admin | Semua kelas pernah diikuti siswa |
| POST | `/akademik/pengampu` | SuperAdmin, Admin | Tetapkan guru pengampu mapel di kelas |
| PATCH | `/akademik/pengampu/{id}` | SuperAdmin, Admin | Ganti guru pengampu mapel |
| DELETE | `/akademik/pengampu/{id}` | SuperAdmin, Admin | Hapus penugasan pengampu |
| GET | `/akademik/guru/{id}/mapel` | Semua | Mapel aktif yang diampu guru |
| GET | `/akademik/mapel/{id}/guru` | Semua | Guru aktif pengampu mapel |
| GET | `/akademik/guru/{id}/mapel/riwayat` | SuperAdmin, Admin | Semua mapel pernah diampu guru |
| GET | `/akademik/mapel/{id}/guru/riwayat` | SuperAdmin, Admin | Semua guru pernah mengampu mapel |
| GET | `/akademik/jam` | Semua | List slot jam pelajaran (ke-1 s.d. ke-10) |
| POST | `/akademik/jam` | SuperAdmin, Admin | Tambah slot jam pelajaran |
| PATCH | `/akademik/jam/{id}` | SuperAdmin, Admin | Update slot jam pelajaran |
| DELETE | `/akademik/jam/{id}` | SuperAdmin, Admin | Hapus slot jam (gagal jika masih dipakai jadwal) |
| POST | `/akademik/jadwal` | SuperAdmin, Admin | Buat jadwal pelajaran (cek bentrok kelas & guru) |
| PATCH | `/akademik/jadwal/{id}` | SuperAdmin, Admin | Update jadwal (hari/jam/ruangan/catatan) |
| DELETE | `/akademik/jadwal/{id}` | SuperAdmin, Admin | Hapus jadwal (soft delete) |
| GET | `/akademik/jadwal/pengampu/{id}` | Semua | Jadwal aktif satu pengampu mapel |
| GET | `/akademik/jadwal/kelas/{id}` | Semua | Jadwal aktif seluruh kelas (filter: `tahun_ajaran`, `semester`) |
| GET | `/akademik/jadwal/guru/{id}` | Semua | Jadwal aktif seluruh guru (filter: `tahun_ajaran`, `semester`) |
| GET | `/akademik/jadwal/pengampu/{id}/riwayat` | SuperAdmin, Admin | Riwayat jadwal pengampu (termasuk yang dihapus) |
| GET | `/akademik/jadwal/kelas/{id}/riwayat` | SuperAdmin, Admin | Riwayat jadwal kelas |
| GET | `/akademik/jadwal/guru/{id}/riwayat` | SuperAdmin, Admin | Riwayat jadwal guru |

---

## Retensi Data & Riwayat Akademik

Data akademik dirancang agar **tidak ada yang hilang** saat siswa naik kelas/semester atau guru berganti penugasan.

### Bagaimana data dipertahankan

| Skenario | Mekanisme | Akses historis |
|----------|-----------|---------------|
| Siswa naik semester/kelas | Record baru dibuat untuk semester baru, record lama tetap ada | `GET /akademik/siswa/{id}/kelas/riwayat` |
| Siswa pindah kelas dalam semester | Record lama di-soft-delete, record baru dibuat | `GET /akademik/siswa/{id}/kelas/riwayat` |
| Guru berganti mapel/kelas | Record lama di-soft-delete via PATCH (guru_id diganti) | `GET /akademik/guru/{id}/mapel/riwayat` |
| Pengampu mapel dihapus | Soft delete pengampu + semua jadwal terkait ikut soft-delete | `GET /akademik/jadwal/pengampu/{id}/riwayat` |
| Jadwal direvisi (PATCH hari/jam/ruangan) | Record yang sama di-update langsung (in-place) | `GET /akademik/jadwal/kelas/{id}/riwayat` |
| Siswa / Guru dihapus dari sistem | Soft delete — data tetap, token dicabut | Tabel `siswa_kelas` / `pengampu_mapels` tetap ada |

### Response riwayat

Endpoint `/riwayat` mengembalikan field tambahan:
- `deletedAt` — `null` jika record masih aktif, timestamp jika sudah di-soft-delete

### Semester Aktif

Sistem memiliki satu baris "semester aktif" yang menjadi acuan seluruh operasi akademik:
- `POST /akademik/semester/aktif` — set semester baru; semester sebelumnya otomatis ditutup (via DB transaction)
- `GET /akademik/semester/aktif` — ambil semester yang sedang berjalan
- `GET /akademik/semester/riwayat` — semua semester (urut terbaru)

Format `tahun_ajaran`: `YYYY/YYYY` (contoh: `2024/2025`). Semester: `1` atau `2`.

---

## Performa & Database Index

Index database ditambahkan pada tabel yang datanya akan tumbuh besar. Tabel master kecil (ruang_kelas ≤ 30 baris, mapels ≤ 100 baris) tidak diberi index.

| Service | Tabel | Index |
|---------|-------|-------|
| Gateway | `users` | `role`, `deleted_at` |
| Gateway | `audit_logs` | `(resource, resource_id)`, `performed_by`, `created_at` |
| SiswaService | `siswas` | `status`, `tanggal_masuk`, `deleted_at` |
| GuruService | `gurus` | `status_kepegawaian`, `jabatan`, `deleted_at` |
| AkademikService | `siswa_kelas` | `(kelas_id, tahun_ajaran, semester)`, `deleted_at` |
| AkademikService | `pengampu_mapels` | `(guru_id, tahun_ajaran, semester)`, `(kelas_id, tahun_ajaran, semester)`, `deleted_at` |
| AkademikService | `semester_aktif` | `is_aktif`, `(tahun_ajaran, semester)` |
| AkademikService | `jadwal_pelajaran` | `pengampu_mapel_id`, `deleted_at`, `(pengampu_mapel_id, hari, jam_mulai_id)` |

---

## Konvensi Penamaan Field

| Layer | Konvensi | Contoh |
|-------|----------|--------|
| Kolom database | `snake_case` deskriptif | `nama_pelajaran`, `limit_siswa`, `jenis_kelamin` |
| Body request & response API | `camelCase` | `namaPelajaran`, `limitSiswa`, `jenisKelamin` |
| Field ID di response | prefix nama resource | `idPelajaran`, `idKelas`, `idGuru`, `idSiswa` |

### Request Fields — Mata Pelajaran (`/mapel`)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `kode` | ✅ store | Kode unik mapel (auto-UPPERCASE) |
| `namaPelajaran` | ✅ store | Nama mata pelajaran |
| `keterangan` | ❌ | Deskripsi tambahan |
| `idPelajaran` | ✅ update/delete | ID mata pelajaran |

**Response:** `idPelajaran`, `kode`, `namaPelajaran`, `keterangan`

### Request Fields — Kelas (`/class`)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `noKelas` | ✅ store | Nomor urut kelas |
| `tingkat` | ✅ store | `1` (X) / `2` (XI) / `3` (XII) |
| `jurusan` | ✅ store | `MIPA` / `IPS` |
| `limitSiswa` | ✅ store | Kapasitas siswa (1–62) |
| `idKelas` | ✅ update/delete | ID kelas |
| `namaKelas` | ❌ | Override nama kelas secara manual |

**Response:** `idKelas`, `namaKelas`, `tingkat`, `jurusan`, `limitSiswa`

### Response Fields — Guru (`/guru`)

**List (`GET /guru/all`):** `idGuru`, `namaLengkap`, `nip`, `email`, `jabatan`, `statusKepegawaian`

> Foto **tidak disertakan** di list — gunakan `GET /guru?idGuru={id}` untuk mendapatkan foto.

**Detail (`GET /guru?idGuru=N`):** seluruh field list + `nik`, `foto`, `jenisKelamin`, `tempatLahir`, `tanggalLahir`, `alamat`, `agama`, `statusPernikahan`, `tanggalMasuk`, `pendidikanTerakhir`, `jurusan`, `universitas`, `tahunLulus`, `nomorSKPengangkatan`, `nomorSertifikasi`, `pelatihan`

> `foto` dikembalikan sebagai `data:image/webp;base64,...` (inline, siap dipakai di `<img src="..."/>`).

### Response Fields — Siswa (`/siswa`)

**List (`GET /siswa/all`):** `idSiswa`, `namaLengkap`, `nisn`, `jenisKelamin`, `tempatLahir`, `tanggalLahir`, `tanggalMasuk`, `status`

> Foto **tidak disertakan** di list — gunakan `GET /siswa?idSiswa={id}` untuk mendapatkan foto.

**Detail (`GET /siswa?idSiswa=N`):** seluruh field list + `email`, `foto`, `alamat`, `agama`, `statusDate`, `namaAyah`, `namaIbu`, `pekerjaanAyah`, `pekerjaanIbu`, `noTelpAyah`, `noTelpIbu`, `namaWali`, `hubunganWali`, `noTelpWali`

> `foto` dikembalikan sebagai `data:image/webp;base64,...` (inline, siap dipakai di `<img src="..."/>`).

### Request Fields — Guru (`/guru`)

**Store (POST multipart/form-data):**

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `email` | ✅ | Email unik |
| `nik` | ✅ | Nomor Induk Kependudukan (maks 16 digit) |
| `nip` | ✅ | Nomor Induk Pegawai (maks 16 digit) |
| `namaLengkap` | ✅ | Nama lengkap |
| `telephone` | ✅ | Nomor telepon |
| `jenisKelamin` | ✅ | `Laki-Laki` atau `Perempuan` |
| `tempatLahir` | ✅ | Kota tempat lahir |
| `tanggalLahir` | ✅ | Format `YYYY-MM-DD` |
| `alamat` | ✅ | Alamat lengkap |
| `foto` | ✅ | File gambar JPEG/PNG/JPG, maks 2 MB, min 360×480 px |
| `statusKepegawaian` | ✅ | Contoh: `PNS`, `Honorer` |
| `tanggalMasuk` | ✅ | Format `YYYY-MM-DD` |
| `jabatan` | ✅ | Jabatan guru |
| `pendidikanTerakhir` | ✅ | Contoh: `S1`, `S2` |
| `jurusan` | ✅ | Jurusan pendidikan |
| `universitas` | ✅ | Nama universitas |
| `tahunLulus` | ✅ | Tahun lulus |
| `agama` | ❌ | Agama |
| `statusPernikahan` | ❌ | Status pernikahan |
| `nomorSKPengangkatan` | ❌ | Nomor SK (angka) |
| `nomorSertifikasi` | ❌ | Nomor sertifikasi (angka) |
| `pelatihan` | ❌ | Info pelatihan |

**Update (POST multipart/form-data atau JSON):**

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `idGuru` | ✅ | ID guru yang diupdate |
| *field lain* | ❌ | Kirim hanya field yang berubah |
| `foto` | ❌ | Jika dikirim, foto lama otomatis dihapus |

### Request Fields — Siswa (`/siswa`)

**Store (POST multipart/form-data):**

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `email` | ✅ | Email unik |
| `nisn` | ✅ | Nomor Induk Siswa Nasional (angka) |
| `namaLengkap` | ✅ | Nama lengkap |
| `telephone` | ✅ | Nomor telepon |
| `jenisKelamin` | ✅ | `Laki-Laki` atau `Perempuan` |
| `tempatLahir` | ✅ | Kota tempat lahir |
| `tanggalLahir` | ✅ | Format `YYYY-MM-DD` |
| `tanggalMasuk` | ✅ | Format `YYYY-MM-DD` |
| `alamat` | ✅ | Alamat lengkap |
| `namaIbu` | ✅ | Nama ibu kandung |
| `foto` | ✅ | File gambar JPEG/PNG/JPG, maks 2 MB, min 360×480 px |
| `agama` | ❌ | Agama |
| `namaAyah` | ❌ | Nama ayah |
| `pekerjaanAyah` | ❌ | Pekerjaan ayah |
| `pekerjaanIbu` | ❌ | Pekerjaan ibu |
| `noTelpAyah` | ❌ | No. telepon ayah (angka) |
| `noTelpIbu` | ❌ | No. telepon ibu (angka) |
| `namaWali` | ❌ | Nama wali |
| `hubunganWali` | ❌ | Hubungan dengan wali |
| `noTelpWali` | ❌ | No. telepon wali (angka) |

**Update (POST multipart/form-data atau JSON):**

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `idSiswa` | ✅ | ID siswa yang diupdate |
| *field lain* | ❌ | Kirim hanya field yang berubah |
| `foto` | ❌ | Jika dikirim, foto lama otomatis dihapus |

### Request Fields — Akademik Kelas (`/akademik/kelas/assign`)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `siswa_id` | ✅ store | ID siswa dari SiswaService |
| `kelas_id` | ✅ store/patch | ID kelas dari ClassMicroservices |
| `tahun_ajaran` | ✅ store | Format `YYYY/YYYY`, contoh `2024/2025` |
| `semester` | ✅ store | `1` atau `2` |

**Response siswa_kelas:** `idSiswaKelas`, `siswaId`, `kelasId`, `tahunAjaran`, `semester`

**Response riwayat** (tambahan): `deletedAt` — `null` jika aktif, timestamp jika sudah dipindah

### Request Fields — Akademik Pengampu (`/akademik/pengampu`)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `guru_id` | ✅ store/patch | ID guru dari GuruService |
| `mapel_id` | ✅ store | ID mapel dari MapelService |
| `kelas_id` | ✅ store | ID kelas dari ClassMicroservices |
| `tahun_ajaran` | ✅ store | Format `YYYY/YYYY` |
| `semester` | ✅ store | `1` atau `2` |

**Response pengampu_mapel:** `idPengampuMapel`, `guruId`, `mapelId`, `kelasId`, `tahunAjaran`, `semester`

**Response riwayat** (tambahan): `deletedAt` — `null` jika aktif, timestamp jika sudah diganti gurunya

### Request Fields — Semester Aktif (`/akademik/semester/aktif`)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `tahun_ajaran` | ✅ | Format `YYYY/YYYY`, contoh `2024/2025` |
| `semester` | ✅ | `1` atau `2` |
| `tanggal_mulai` | ✅ | Format `YYYY-MM-DD` |
| `tanggal_selesai` | ❌ | Format `YYYY-MM-DD`, harus setelah `tanggal_mulai` (opsional) |

**Response semester_aktif:** `idSemesterAktif`, `tahunAjaran`, `semester`, `tanggalMulai`, `tanggalSelesai`, `isAktif`

### Request Fields — Jam Pelajaran (`/akademik/jam`)

**Store (POST JSON):**

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `ke` | ✅ | Urutan jam (1–10), unik |
| `jam_mulai` | ✅ | Format `HH:MM`, contoh `07:00` |
| `jam_selesai` | ✅ | Format `HH:MM`, harus setelah `jam_mulai` |

**Update (PATCH JSON):** kirim hanya field yang berubah. Slot tidak dapat dihapus jika masih digunakan jadwal (aktif maupun riwayat).

**Response:** `idJam`, `ke`, `jamMulai`, `jamSelesai`

### Request Fields — Jadwal Pelajaran (`/akademik/jadwal`)

**Store (POST JSON):**

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `pengampu_mapel_id` | ✅ | ID dari `POST /akademik/pengampu` |
| `hari` | ✅ | `Senin` / `Selasa` / `Rabu` / `Kamis` / `Jumat` (Sabtu tidak berlaku) |
| `jam_mulai_id` | ✅ | ID jam mulai (dari `GET /akademik/jam`) |
| `jam_selesai_id` | ✅ | ID jam selesai, harus ke-nya lebih besar dari `jam_mulai_id` |
| `ruangan` | ❌ | Nama ruangan / lab (maks 50 karakter) |
| `catatan` | ❌ | Catatan tambahan untuk jadwal ini (maks 500 karakter) |

**Validasi bentrok (otomatis):**
- Kelas yang sama tidak boleh punya 2 mapel pada hari + jam yang overlap dalam satu semester
- Guru yang sama tidak boleh mengajar di 2 tempat pada hari + jam yang overlap dalam satu semester

**Update (PATCH JSON):** kirim hanya field yang berubah (`hari`, `jam_mulai_id`, `jam_selesai_id`, `ruangan`, `catatan`). Field `pengampu_mapel_id` tidak dapat diubah — hapus dan buat ulang jika pengampu berubah.

**Response jadwal:** `idJadwal`, `pengampuMapelId`, `guruId`, `mapelId`, `kelasId`, `tahunAjaran`, `semester`, `hari`, `jamMulaiId`, `jamSelesaiId`, `keMulai`, `keSelesai`, `pukul` (contoh: `07:00:00 - 08:30:00`), `ruangan`, `catatan`

**Response riwayat** (tambahan): `deletedAt` — `null` jika aktif, timestamp jika sudah dihapus

---

## Referensi

- Inspirasi arsitektur: [ismail17719/apigateway-based-microservices-in-laravel-and-lumen](https://github.com/ismail17719/apigateway-based-microservices-in-laravel-and-lumen)
