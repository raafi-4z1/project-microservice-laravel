# Gateway

Service utama yang menjadi pintu masuk seluruh request. Menangani autentikasi OAuth2 (Laravel Passport), otorisasi berbasis role, routing ke service internal via HMAC, dan audit log.

**Domain lokal:** `https://gateway.test`  
**Database:** `gateway_db`

---

## Konfigurasi Environment

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

# Secret HMAC — harus sama dengan ACCEPTED_SECRETS di tiap service
CLASS_SERVICE_BASE_URL=http://classmicroservices.test
CLASS_SERVICE_SECRET=base64:...

MAPEL_SERVICE_BASE_URL=http://mapelservice.test
MAPEL_SERVICE_SECRET=base64:...

GURU_SERVICE_BASE_URL=http://guruservice.test
GURU_SERVICE_SECRET=base64:...

SISWA_SERVICE_BASE_URL=http://siswaservice.test
SISWA_SERVICE_SECRET=base64:...

KARYAWAN_SERVICE_BASE_URL=http://karyawanservice.test
KARYAWAN_SERVICE_SECRET=base64:...

AKADEMIK_SERVICE_BASE_URL=http://akademikservice.test
AKADEMIK_SERVICE_SECRET=base64:...

# Akun SuperAdmin awal (dipakai seeder — wajib diganti)
SUPERADMIN_NAME="Nama Admin Kamu"
SUPERADMIN_EMAIL="email@domain.com"
SUPERADMIN_PASSWORD="MinimalDuabelasKarakter1"
```

---

## Endpoints

Base URL: `https://gateway.test/api`

### Auth

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| POST | `/login` | Publik | Login (per device via `device_name`), max 5x/menit |
| POST | `/refresh` | Semua | Tukar token yang masih valid dengan token baru (perpanjang sesi), max 5x/menit |
| POST | `/logout` | Semua | Cabut token aktif (device ini saja) |
| POST | `/logout-all` | Semua | Cabut semua sesi aktif di semua device |
| POST | `/register` | SuperAdmin, Admin | Daftar akun baru (wajib login) |
| GET | `/user` | Semua | Profil diri sendiri — hanya mengembalikan data milik user yang sedang login |
| POST | `/password` | Semua | Ganti password sendiri, max 5x/menit |

### Manajemen User

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/users` | SuperAdmin, Admin | List semua akun user. Query: `page`, `per_page`, `role` (filter exact), `search` (cari di nama/email) |
| GET | `/users/{id}` | SuperAdmin, Admin | Detail akun user by ID |
| POST | `/users/{id}/password` | SuperAdmin, Admin | Reset password user lain (token target dicabut) |
| DELETE | `/users/{id}` | SuperAdmin, Admin | Hapus akun user (soft delete) |

### Kartu Absensi

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| POST | `/siswa/kartu/terbitkan` · `/guru/kartu/terbitkan` · `/karyawan/kartu/terbitkan` | SuperAdmin, Admin | Terbitkan/ganti kartu (UID opaque berprefix SIS-/GUR-/KAR-) |
| POST | `/siswa/kartu/blokir` · `/guru/kartu/blokir` · `/karyawan/kartu/blokir` | SuperAdmin, Admin | Blokir kartu (`status`: `hilang`/`blokir`) |
| GET | `/kartu/qr?data=<uid>` | SuperAdmin, Admin | Render QR kartu sebagai `image/svg+xml` |

### Absensi (Scan & PIN via Terminal)

Endpoint di bawah **tidak** memakai Bearer token — melainkan autentikasi **terminal** (header `X-Terminal-Id` + `X-Terminal-Token`). Lihat bagian [Terminal Absensi](#terminal-absensi).

| Method | Endpoint | Auth | Keterangan |
|--------|----------|------|------------|
| POST | `/absensi/scan` | Terminal | Scan kartu masuk (siswa gerbang / guru & karyawan). Resolve UID→subjek via prefix, catat absensi. Idempotent per hari |
| POST | `/absensi/pin/absen` | Terminal | Absen via NIP+PIN saat lupa kartu (butuh jendela PIN aktif) |
| POST | `/absensi/pin/atur` | Guru, Karyawan | Pegawai mengatur PIN sendiri (4-6 digit) |
| POST | `/absensi/pin/buka` | SuperAdmin, Admin | Buka jendela PIN untuk seorang pegawai (time-boxed, sekali pakai) |

> Absensi per pelajaran, izin keluar, dan rekap berada di prefix `/akademik/absensi/*` — lihat [AkademikService/README.md](../AkademikService/README.md).

---

## Request Fields

### POST /login

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `email` | ✅ | Email akun |
| `password` | ✅ | Password akun |
| `device_name` | ❌ | Identitas perangkat, contoh: `web` / `android` (default: `web`, maks 50 karakter) |

Response menyertakan `access_token` yang disimpan otomatis ke `{{token}}` oleh Postman, plus field `mustChangePassword` (lihat bagian Password Default di bawah).

### POST /register

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `name` | ✅ | Nama lengkap |
| `email` | ✅ | Email unik |
| `password` | ✅ | Min 8 karakter, harus mengandung huruf dan angka |
| `confirm_password` | ✅ | Harus sama dengan `password` |
| `role` | ✅ | `Admin` / `Guru` / `Siswa` / `Karyawan` |

### POST /password (ganti password sendiri)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `current_password` | ✅ | Password saat ini |
| `new_password` | ✅ | Min 8 karakter, harus mengandung huruf dan angka |
| `confirm_password` | ✅ | Harus sama dengan `new_password` |

### POST /users/{id}/password (reset password user lain)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `new_password` | ✅ | Min 8 karakter, harus mengandung huruf dan angka |
| `confirm_password` | ✅ | Harus sama dengan `new_password` |

Semua token aktif milik target user langsung dicabut saat password direset.

---

## Role & Akses

| Role | GET | Write (POST/PATCH) | DELETE | Register |
|------|-----|--------------------|--------|----------|
| SuperAdmin | ✅ | ✅ | ✅ | Admin, Guru, Siswa, Karyawan |
| Admin | ✅ | ✅ | ✅* | Guru, Siswa, Karyawan |
| Guru / Siswa / Karyawan | ✅ (data sendiri) | ✅ (password sendiri) | ❌ | ❌ |

Role `SuperAdmin` tidak dapat dibuat melalui API — hanya via `php artisan db:seed`.

### Proteksi DELETE /users/{id}

| Kondisi | Hasil |
|---------|-------|
| Admin hapus Guru / Siswa / Karyawan | ✅ Diizinkan |
| Admin hapus Admin lain | ❌ 403 |
| Admin hapus SuperAdmin | ❌ 403 |
| SuperAdmin hapus Admin / Guru / Siswa / Karyawan | ✅ Diizinkan |
| Siapapun hapus SuperAdmin | ❌ 403 (tidak bisa via API) |
| Menghapus akun sendiri | ❌ 403 |

Hapus bersifat **soft delete** — kolom `deleted_at` terisi, data tetap di database. Token aktif milik user yang dihapus langsung dicabut.

### Proteksi Reset Password

- Admin hanya dapat reset password Guru, Siswa, Karyawan — tidak bisa reset Admin/SuperAdmin
- Password SuperAdmin tidak dapat direset via API
- `new_password` tidak boleh sama dengan password lama

---

## Password Default & Wajib Ganti Password

Akun guru/siswa dibuat **otomatis** saat data guru/siswa didaftarkan, dengan
password awal = alamat emailnya sendiri. Karena password default itu mudah
ditebak, akun tersebut ditandai `must_change_password` dan **wajib mengganti
password saat login pertama**:

- Response `POST /login` menyertakan `mustChangePassword: true/false`
- Selama flag masih `true`, semua endpoint diblokir **403** dengan
  `data: {"mustChangePassword": true}` — kecuali `POST /password`, `GET /user`,
  `POST /logout`, dan `POST /logout-all`
- Setelah `POST /password` sukses, flag terhapus dan akses normal kembali
- Akun yang dibuat via `POST /register` (password dipilih admin) dan SuperAdmin
  tidak terkena kewajiban ini; migration mem-backfill akun lama yang masih
  memakai password default

---

## Token & Sesi

- Token OAuth2 (Bearer) berlaku **8 jam**
- `POST /refresh` menukar token yang **masih valid** dengan token baru 8 jam
  (token lama langsung dicabut, `device_name` diwarisi) — klien dapat memperpanjang sesi tanpa login ulang.
  Token yang sudah kedaluwarsa tidak bisa di-refresh; user harus login kembali
- **Satu sesi aktif per device**: login baru hanya mencabut token lama dari
  `device_name` yang sama. Login di `web` dan `android` bisa berjalan bersamaan;
  login ulang di `android` hanya menendang sesi `android` yang lama
- `POST /logout-all` mencabut **semua** sesi di semua device — gunakan jika akun
  dicurigai dipakai orang lain
- Endpoint `/login`, `/refresh`, `/password`, dan `/oauth/token` dibatasi **5 percobaan per menit**

---

## Terminal Absensi

Absen via scan/PIN hanya diterima dari **terminal terdaftar** (perangkat sekolah di gerbang/ruang guru/TU), bukan dari perangkat pribadi. Autentikasi terminal terpisah dari Bearer token user.

**Header wajib:** `X-Terminal-Id` dan `X-Terminal-Token` (token dicek terhadap hash SHA-256 di DB).

**Mode terminal:**

| Mode | Cara verifikasi lokasi | Untuk |
|------|------------------------|-------|
| `produksi` | IP request harus di `ip_allowlist` (LAN sekolah) | perangkat tetap di sekolah |
| `demo` | `lat`/`lng` di body harus di dalam geofence (radius) terminal | simulasi tanpa jaringan sekolah |

**Daftarkan terminal** (token hanya ditampilkan sekali):

```bash
# Produksi (allowlist IP/CIDR LAN)
php artisan terminal:register --nama="Gerbang Utama" --lokasi=gerbang --mode=produksi --ip="192.168.1.0/24"

# Demo (geofence)
php artisan terminal:register --nama="Gerbang Utama" --lokasi=gerbang --mode=demo --lat=-6.200000 --lng=106.816666 --radius=150
```

**Jendela PIN (lupa kartu):** admin membuka jendela time-boxed (default 10 menit, dari `durasi_pin_window_menit` pengaturan absensi) untuk seorang pegawai; pegawai lalu absen dengan NIP+PIN di terminal. Jendela **sekali pakai** — hangus setelah dipakai atau kedaluwarsa.

---

## Audit Log

Semua aksi tulis (create, update, delete, login, register) dicatat di tabel `audit_logs`.

| Kolom | Isi |
|-------|-----|
| `action` | `login` / `created` / `updated` / `deleted` / `registered` |
| `resource` | `guru` / `siswa` / `mapel` / `kelas` / `user` / dst. |
| `resource_id` | ID record yang diubah |
| `performed_by` | Email pelaku aksi |
| `role` | Role pelaku |
| `ip_address` | IP address pengirim request |
| `payload` | Data yang dikirim (foto dan password otomatis disanitasi) |

---

## Database Index

| Tabel | Index |
|-------|-------|
| `users` | `role`, `deleted_at` |
| `audit_logs` | `(resource, resource_id)`, `performed_by`, `created_at` |
