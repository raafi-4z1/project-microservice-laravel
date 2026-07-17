# GuruService

Microservice untuk manajemen data guru beserta foto profil. Hanya dapat diakses dari **Gateway** melalui autentikasi **HMAC SHA-256** — tidak ada akses langsung dari klien.

**Domain lokal:** `http://guruservice.test` (internal only)  
**Database:** `guru_db`

---

## Konfigurasi Environment

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

---

## Endpoints (via Gateway)

Base URL: `https://gateway.test/api`

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/guru/all` | Semua | List seluruh guru (tanpa foto). Query: `page`, `per_page`, `search` (cari di nama/NIP/email/jabatan) |
| GET | `/guru` | Semua | Detail guru by `idGuru` (query param, termasuk foto). Untuk role Guru/Siswa field pribadi (NIK, alamat, telepon, tanggal lahir, dll.) disaring — hanya SuperAdmin/Admin/Karyawan yang menerima profil lengkap |
| POST | `/guru` | SuperAdmin, Admin | Tambah guru baru + foto (multipart/form-data) |
| POST | `/guru/update` | SuperAdmin, Admin | Update data guru + foto opsional |
| DELETE | `/guru/{id}` | SuperAdmin, Admin | Hapus guru (soft delete) |
| POST | `/guru/kartu/terbitkan` | SuperAdmin, Admin | Terbitkan/ganti kartu absensi (UID prefix `GUR-`) |
| POST | `/guru/kartu/blokir` | SuperAdmin, Admin | Blokir kartu (`status`: `hilang`/`blokir`) |

### Rute internal (dipanggil Gateway, bukan langsung dari klien)

| Method | Route | Dipakai untuk |
|--------|-------|---------------|
| GET | `/guru/lookup-kartu?uid=` | Resolve UID kartu → guru saat scan di terminal |
| POST | `/guru/pin/set` | Set PIN absensi (di-hash) — user-facing: `POST /absensi/pin/atur` |
| POST | `/guru/pin/verify` | Verifikasi NIP+PIN saat absen PIN di terminal |

> Tabel `gurus` memiliki kolom absensi: `kartu_uid`, `kartu_status` (`belum_terbit`/`aktif`/`hilang`/`blokir`), `kartu_diterbitkan_at`, `pin_hash` (hidden). Alur absensi lengkap: lihat [Gateway/README.md](../Gateway/README.md) & [AkademikService/README.md](../AkademikService/README.md).

---

## Request Fields

### POST /guru (tambah guru) — multipart/form-data

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
| `foto` | ✅ | File gambar (lihat [Ketentuan Foto](#ketentuan-foto)) |
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

### POST /guru/update (update guru)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `idGuru` | ✅ | ID guru yang diupdate |
| *field lain* | ❌ | Kirim hanya field yang berubah |
| `foto` | ❌ | Jika dikirim, foto lama otomatis dihapus dan diganti |

---

## Response Fields

**List (`GET /guru/all`):**

| Field | Keterangan |
|-------|------------|
| `idGuru` | ID unik guru |
| `namaLengkap` | Nama lengkap |
| `nip` | Nomor Induk Pegawai |
| `email` | Email |
| `jabatan` | Jabatan |
| `statusKepegawaian` | Status kepegawaian |

Foto **tidak disertakan** di list — gunakan `GET /guru?idGuru={id}` untuk mendapatkan foto.

**Detail (`GET /guru?idGuru=N`):** seluruh field list + `nik`, `foto`, `jenisKelamin`, `tempatLahir`, `tanggalLahir`, `alamat`, `agama`, `statusPernikahan`, `tanggalMasuk`, `pendidikanTerakhir`, `jurusan`, `universitas`, `tahunLulus`, `nomorSKPengangkatan`, `nomorSertifikasi`, `pelatihan`

Field `foto` dikembalikan sebagai `data:image/webp;base64,...` (inline, siap dipakai di `<img src="..."/>`).

---

## Ketentuan Foto

- **Format yang diterima:** JPEG, PNG, JPG
- **Ukuran maksimal:** 2 MB
- **Dimensi minimal sumber:** 360×480 px
- Foto dikonversi otomatis ke rasio **3:4 portrait (360×480 px)** dan disimpan sebagai **WebP** (kualitas 85)
- Disimpan di disk `private` (tidak dapat diakses langsung via URL publik)

---

## Database Index

| Tabel | Index |
|-------|-------|
| `gurus` | `status_kepegawaian`, `jabatan`, `deleted_at` |
