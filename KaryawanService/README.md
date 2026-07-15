# KaryawanService

Microservice manajemen data karyawan/staf sekolah (TU, keamanan, kebersihan, dll.) beserta **kartu absensi**. Hanya dapat diakses dari **Gateway** melalui autentikasi **HMAC SHA-256** — tidak ada akses langsung dari klien.

**Domain lokal:** `http://karyawanservice.test` (internal only)
**Database:** `microservice-karyawan`

---

## Konfigurasi Environment

```env
APP_URL=http://karyawanservice.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=microservice-karyawan
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=private

# Harus sama persis dengan KARYAWAN_SERVICE_SECRET di Gateway
ACCEPTED_SECRETS=base64:...
```

---

## Endpoints (via Gateway)

Base URL: `https://gateway.test/api`

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/karyawan/all` | Semua | List karyawan (paginated, `?search=` nama/NIP/email/jabatan) |
| GET | `/karyawan?idKaryawan={id}` | SuperAdmin, Admin, Karyawan | Detail karyawan |
| POST | `/karyawan` | SuperAdmin, Admin | Tambah karyawan (foto opsional) |
| POST | `/karyawan/update` | SuperAdmin, Admin | Update (kirim `idKaryawan` + field berubah) |
| DELETE | `/karyawan/{id}` | SuperAdmin, Admin | Hapus (soft delete) |
| POST | `/karyawan/kartu/terbitkan` | SuperAdmin, Admin | Terbitkan/ganti kartu absensi (UID prefix `KAR-`) |
| POST | `/karyawan/kartu/blokir` | SuperAdmin, Admin | Blokir kartu (`status`: `hilang`/`blokir`) |

**Internal (dipanggil Gateway, tidak diekspos ke klien):**

| Method | Endpoint | Keterangan |
|--------|----------|------------|
| GET | `/karyawan/lookup?email=` | Resolve `karyawan_id` dari email (untuk absensi/RBAC) |
| GET | `/karyawan/lookup-kartu?uid=` | Resolve kartu absensi (scan) → karyawan |
| POST | `/karyawan/pin/set` | Set PIN absensi (di-hash) — user-facing: `POST /absensi/pin/atur` |
| POST | `/karyawan/pin/verify` | Verifikasi NIP+PIN saat absen PIN di terminal |

> Tabel `karyawans` memiliki kolom absensi: `kartu_uid`, `kartu_status` (`belum_terbit`/`aktif`/`hilang`/`blokir`), `kartu_diterbitkan_at`, `pin_hash` (hidden). Alur absensi lengkap: lihat [Gateway/README.md](../Gateway/README.md) & [AkademikService/README.md](../AkademikService/README.md).

Saat `POST /karyawan`, Gateway otomatis membuat akun user role `Karyawan` (password default = email → wajib ganti saat login pertama), sama seperti alur Guru/Siswa.

---

## Request Fields

### POST /karyawan (multipart/form-data)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `email` | ✅ | Email unik (penghubung ke akun user) |
| `nip` | ✅ | Nomor induk pegawai, unik (maks 20 karakter) |
| `namaLengkap` | ✅ | Nama lengkap |
| `jabatan` | ✅ | Mis. "Staf TU", "Keamanan", "Kebersihan" |
| `statusKepegawaian` | ❌ | PNS / Honorer / dll. |
| `jenisKelamin` | ❌ | `Laki-Laki` / `Perempuan` |
| `noTelp` | ❌ | Nomor telepon |
| `alamat` | ❌ | Alamat |
| `foto` | ❌ | JPEG/PNG/JPG maks 2 MB, min 360×480 px |

**Response:** `idKaryawan`, `namaLengkap`, `nip`, `email`, `jabatan`, `statusKepegawaian`, `jenisKelamin`, `noTelp`, `alamat`, `foto` (data-URI base64), `kartuStatus`.

---

## Kartu Absensi

| Kolom | Isi |
|-------|-----|
| `kartu_uid` | UID opaque acak (prefix `KAR-`), di-encode ke barcode/QR kartu |
| `kartu_status` | `belum_terbit` / `aktif` / `hilang` / `blokir` |
| `kartu_diterbitkan_at` | Waktu kartu aktif diterbitkan |
| `pin_hash` | PIN absensi (bcrypt) — fallback lupa kartu; **bukan** password akun |

Penerbitan kartu (generate UID + QR), blokir, dan PIN dikelola lewat alur absensi di Gateway (bukan CRUD biasa). Lihat `absensi-schema.md` di root project.

---

## Setup (sekali)

```sh
cd KaryawanService
composer install
copy .env.example .env      # lalu isi APP_KEY & ACCEPTED_SECRETS
php artisan key:generate
php artisan migrate
```

Daftarkan vhost `karyawanservice.test` (localhost-only, `127.0.0.1:80`) seperti service internal lain — lihat README root.
