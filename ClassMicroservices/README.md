# ClassMicroservices

Microservice untuk manajemen ruang kelas. Hanya dapat diakses dari **Gateway** melalui autentikasi **HMAC SHA-256** — tidak ada akses langsung dari klien.

**Domain lokal:** `http://classmicroservices.test` (internal only)  
**Database:** `class_db`

---

## Konfigurasi Environment

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

---

## Endpoints (via Gateway)

Base URL: `https://gateway.test/api`

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/class/all` | Semua | List seluruh kelas |
| GET | `/class` | Semua | Detail kelas by `idKelas` (query param) |
| POST | `/class` | SuperAdmin, Admin | Tambah kelas baru |
| POST | `/class/update` | SuperAdmin, Admin | Update data kelas |
| DELETE | `/class/{id}` | SuperAdmin, Admin | Hapus kelas |

---

## Request Fields

### POST /class (tambah kelas)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `noKelas` | ✅ | Nomor urut kelas |
| `tingkat` | ✅ | `1` (X) / `2` (XI) / `3` (XII) |
| `jurusan` | ✅ | `MIPA` / `IPS` |
| `limitSiswa` | ✅ | Kapasitas siswa (1–62) |

`namaKelas` di-generate otomatis dari kombinasi tingkat, jurusan, dan noKelas (contoh: `X MIPA 1`).

### POST /class/update (update kelas)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `idKelas` | ✅ | ID kelas yang diupdate |
| `noKelas` | ❌ | Nomor urut kelas |
| `tingkat` | ❌ | `1` / `2` / `3` |
| `jurusan` | ❌ | `MIPA` / `IPS` |
| `limitSiswa` | ❌ | Kapasitas siswa (1–62) |

---

## Response Fields

**List (`GET /class/all`)** dan **Detail (`GET /class`):**

| Field | Keterangan |
|-------|------------|
| `idKelas` | ID unik kelas |
| `namaKelas` | Nama kelas yang di-generate otomatis |
| `tingkat` | Tingkat kelas (1/2/3) |
| `jurusan` | Jurusan (MIPA/IPS) |
| `limitSiswa` | Kapasitas maksimal siswa |
