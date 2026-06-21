# AkademikService

Microservice Laravel 12 untuk manajemen data akademik sekolah. Hanya dapat diakses dari **Gateway** melalui autentikasi **HMAC SHA-256** — tidak ada akses langsung dari klien.

## Tanggung Jawab

| Modul | Keterangan |
|-------|-----------|
| **Pembagian Kelas** | Mencatat siswa masuk ke kelas mana per tahun ajaran & semester |
| **Pengampu Mapel** | Mencatat guru mengajar mapel apa di kelas mana per semester |
| **Riwayat Akademik** | Menyimpan histori perpindahan kelas siswa dan pergantian guru pengampu |
| **Semester Aktif** | Acuan tahun ajaran & semester yang sedang berlangsung untuk seluruh sistem |

---

## Database

**Driver:** MySQL (database terpisah `microservice-akademik`)

| Tabel | Keterangan |
|-------|-----------|
| `siswa_kelas` | Pembagian kelas — satu baris per siswa per semester |
| `pengampu_mapels` | Pengampu mapel — satu baris per kombinasi guru+mapel+kelas per semester |
| `semester_aktif` | Satu baris aktif (`is_aktif = true`) sebagai acuan sistem |

Semua tabel menggunakan **soft deletes** (`deleted_at`) untuk retensi data historis.

---

## Endpoints

Base URL internal: `http://akademikservice.test/api`

> Semua request harus menyertakan header `X-Timestamp` dan `X-Signature` (HMAC SHA-256) yang digenerate oleh Gateway.

### Semester Aktif

| Method | Path | Keterangan |
|--------|------|-----------|
| GET | `/semester/aktif` | Semester yang sedang berjalan |
| POST | `/semester/aktif` | Set semester aktif baru (menutup yang lama via DB transaction) |
| GET | `/semester/riwayat` | Semua semester, urut terbaru |

**Body POST:**
```json
{
  "tahun_ajaran": "2024/2025",
  "semester": "2",
  "tanggal_mulai": "2025-01-06",
  "tanggal_selesai": "2025-06-20"
}
```

### Pembagian Kelas

| Method | Path | Keterangan |
|--------|------|-----------|
| POST | `/kelas/assign` | Daftarkan siswa ke kelas |
| PATCH | `/kelas/assign/{id}` | Pindah kelas (dalam semester yang sama) |
| DELETE | `/kelas/assign/{id}` | Soft delete — keluarkan siswa dari kelas |
| GET | `/kelas/{kelas_id}/siswa` | Siswa aktif di kelas (filter: tahun_ajaran, semester) |
| GET | `/siswa/{siswa_id}/kelas` | Kelas siswa saat ini (filter: tahun_ajaran, semester) |
| GET | `/kelas/{kelas_id}/siswa/riwayat` | Semua siswa pernah di kelas — termasuk soft-deleted |
| GET | `/siswa/{siswa_id}/kelas/riwayat` | Semua kelas pernah diikuti siswa |

**Catatan PATCH pindah kelas:** Gateway mengirim `kelas_id` + `limit_siswa` (diambil dari ClassMicroservices). Service memvalidasi kapasitas kelas tujuan sebelum memindahkan.

### Pengampu Mapel

| Method | Path | Keterangan |
|--------|------|-----------|
| POST | `/pengampu` | Tetapkan guru sebagai pengampu mapel |
| PATCH | `/pengampu/{id}` | Ganti guru pengampu |
| DELETE | `/pengampu/{id}` | Soft delete — hapus penugasan |
| GET | `/guru/{guru_id}/mapel` | Mapel aktif yang diampu guru |
| GET | `/mapel/{mapel_id}/guru` | Guru aktif pengampu mapel |
| GET | `/guru/{guru_id}/mapel/riwayat` | Semua mapel pernah diampu guru |
| GET | `/mapel/{mapel_id}/guru/riwayat` | Semua guru pernah mengampu mapel |

---

## Retensi Data

Data tidak pernah dihapus permanen. Mekanisme:

- **Naik semester/kelas:** Record lama tetap. Record baru dibuat untuk semester/kelas baru.
- **Pindah kelas dalam semester:** Record lama di-soft-delete, record baru dibuat.
- **Ganti guru pengampu:** `guru_id` di-update in-place (bukan delete+create) untuk menghindari pelanggaran unique constraint pada `(mapel_id, kelas_id, tahun_ajaran, semester)`.

Endpoint `/riwayat` menggunakan `withTrashed()` dan mengembalikan field `deletedAt` (`null` = aktif).

---

## Autentikasi HMAC

Middleware `AuthenticateAccessMiddleware` memverifikasi setiap request:

- **GET / DELETE:** HMAC dihitung dari `http_build_query($request->query())`
- **POST / PATCH:** HMAC dihitung dari `$request->getContent()` (JSON body)
- Request lebih dari 5 menit ditolak (replay protection)

Secret dikonfigurasi via `ACCEPTED_SECRETS` di `.env`.

---

## Setup

```sh
composer install
cp .env.example .env
```

`.env` minimal:
```env
APP_URL=http://akademikservice.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=microservice-akademik
DB_USERNAME=root
DB_PASSWORD=

ACCEPTED_SECRETS=base64:+ZnOcffL/GId4hrnVT4YCWG8f/E8woMi8lSlRsOiZBQ=
```

```sh
php artisan key:generate
php artisan migrate
```

> Nilai `ACCEPTED_SECRETS` harus sama persis dengan `AKADEMIK_SERVICE_SECRET` di Gateway.
