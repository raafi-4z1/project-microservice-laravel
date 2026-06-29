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
| POST | `/login` | Publik | Login, max 5x/menit |
| POST | `/logout` | Semua | Cabut token aktif |
| POST | `/register` | SuperAdmin, Admin | Daftar akun baru (wajib login) |
| GET | `/user` | Semua | Profil diri sendiri — hanya mengembalikan data milik user yang sedang login |
| POST | `/password` | Semua | Ganti password sendiri |

### Manajemen User

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/users` | SuperAdmin, Admin | List semua akun user |
| GET | `/users/{id}` | SuperAdmin, Admin | Detail akun user by ID |
| POST | `/users/{id}/password` | SuperAdmin, Admin | Reset password user lain (token target dicabut) |
| DELETE | `/users/{id}` | SuperAdmin, Admin | Hapus akun user (soft delete) |

---

## Request Fields

### POST /login

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `email` | ✅ | Email akun |
| `password` | ✅ | Password akun |

Response menyertakan `access_token` yang disimpan otomatis ke `{{token}}` oleh Postman.

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

## Token & Sesi

- Token OAuth2 (Bearer) berlaku **8 jam**, refresh token **30 hari**
- Login baru **mencabut semua token lama** — tidak ada concurrent session
- Endpoint `/login` dibatasi **5 percobaan per menit**
- Endpoint `/oauth/token` juga dibatasi **5 percobaan per menit** (mencegah bypass brute force)

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
