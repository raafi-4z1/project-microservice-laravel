# MapelService

Microservice untuk manajemen mata pelajaran. Hanya dapat diakses dari **Gateway** melalui autentikasi **HMAC SHA-256** — tidak ada akses langsung dari klien.

**Domain lokal:** `http://mapelservice.test` (internal only)  
**Database:** `mapel_db`

---

## Konfigurasi Environment

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

---

## Endpoints (via Gateway)

Base URL: `https://gateway.test/api`

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/mapel/all` | Semua | List seluruh mata pelajaran |
| GET | `/mapel` | Semua | Detail mapel by `idPelajaran` (query param) |
| POST | `/mapel` | SuperAdmin, Admin | Tambah mata pelajaran baru |
| POST | `/mapel/update` | SuperAdmin, Admin | Update data mata pelajaran |
| DELETE | `/mapel/{id}` | SuperAdmin, Admin | Hapus mata pelajaran |

---

## Request Fields

### POST /mapel (tambah mapel)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `kode` | ✅ | Kode unik mapel (otomatis diubah ke UPPERCASE) |
| `namaPelajaran` | ✅ | Nama mata pelajaran |
| `keterangan` | ❌ | Deskripsi tambahan |

### POST /mapel/update (update mapel)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `idPelajaran` | ✅ | ID mata pelajaran yang diupdate |
| `kode` | ❌ | Kode unik mapel |
| `namaPelajaran` | ❌ | Nama mata pelajaran |
| `keterangan` | ❌ | Deskripsi tambahan |

---

## Response Fields

**List (`GET /mapel/all`)** dan **Detail (`GET /mapel`):**

| Field | Keterangan |
|-------|------------|
| `idPelajaran` | ID unik mata pelajaran |
| `kode` | Kode mapel (uppercase) |
| `namaPelajaran` | Nama mata pelajaran |
| `keterangan` | Deskripsi tambahan (nullable) |
