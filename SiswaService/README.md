# SiswaService

Microservice untuk manajemen data siswa beserta foto profil. Hanya dapat diakses dari **Gateway** melalui autentikasi **HMAC SHA-256** — tidak ada akses langsung dari klien.

**Domain lokal:** `http://siswaservice.test` (internal only)  
**Database:** `siswa_db`

---

## Konfigurasi Environment

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

---

## Endpoints (via Gateway)

Base URL: `https://gateway.test/api`

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/siswa/all` | Semua | List seluruh siswa (tanpa foto). Query: `page`, `per_page`, `search` (cari di nama/NISN) |
| GET | `/siswa` | SuperAdmin, Admin, Guru, Karyawan | Detail siswa by `idSiswa` (query param, termasuk foto). Role Siswa diblokir — berisi data pribadi (alamat, kontak orang tua) |
| POST | `/siswa` | SuperAdmin, Admin | Tambah siswa baru + foto (multipart/form-data) |
| POST | `/siswa/update` | SuperAdmin, Admin | Update data siswa + foto opsional |
| DELETE | `/siswa/{id}` | SuperAdmin, Admin | Hapus siswa (soft delete) |
| POST | `/siswa/kartu/terbitkan` | SuperAdmin, Admin | Terbitkan/ganti kartu absensi (UID prefix `SIS-`) |
| POST | `/siswa/kartu/blokir` | SuperAdmin, Admin | Blokir kartu (`status`: `hilang`/`blokir`) |

**Internal (dipanggil Gateway, tidak diekspos ke klien):**

| Method | Endpoint | Keterangan |
|--------|----------|------------|
| GET | `/siswa/lookup-kartu?uid=` | Resolve UID kartu → siswa saat scan di terminal |

> Tabel `siswas` memiliki kolom absensi: `kartu_uid`, `kartu_status` (`belum_terbit`/`aktif`/`hilang`/`blokir`), `kartu_diterbitkan_at`. Siswa absen dengan kartu (tanpa PIN). Alur lengkap: lihat [Gateway/README.md](../Gateway/README.md) & [AkademikService/README.md](../AkademikService/README.md).

---

## Request Fields

### POST /siswa (tambah siswa) — multipart/form-data

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
| `foto` | ✅ | File gambar (lihat [Ketentuan Foto](#ketentuan-foto)) |
| `agama` | ❌ | Agama |
| `namaAyah` | ❌ | Nama ayah |
| `pekerjaanAyah` | ❌ | Pekerjaan ayah |
| `pekerjaanIbu` | ❌ | Pekerjaan ibu |
| `noTelpAyah` | ❌ | No. telepon ayah (angka) |
| `noTelpIbu` | ❌ | No. telepon ibu (angka) |
| `namaWali` | ❌ | Nama wali |
| `hubunganWali` | ❌ | Hubungan dengan wali |
| `noTelpWali` | ❌ | No. telepon wali (angka) |

### POST /siswa/update (update siswa)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `idSiswa` | ✅ | ID siswa yang diupdate |
| *field lain* | ❌ | Kirim hanya field yang berubah |
| `foto` | ❌ | Jika dikirim, foto lama otomatis dihapus dan diganti |

---

## Response Fields

**List (`GET /siswa/all`):**

| Field | Keterangan |
|-------|------------|
| `idSiswa` | ID unik siswa |
| `namaLengkap` | Nama lengkap |
| `nisn` | Nomor Induk Siswa Nasional |
| `jenisKelamin` | Jenis kelamin |
| `tempatLahir` | Kota tempat lahir |
| `tanggalLahir` | Tanggal lahir |
| `tanggalMasuk` | Tanggal masuk sekolah |
| `status` | Status siswa (aktif/alumni) |

Foto **tidak disertakan** di list — gunakan `GET /siswa?idSiswa={id}` untuk mendapatkan foto.

**Detail (`GET /siswa?idSiswa=N`):** seluruh field list + `email`, `foto`, `alamat`, `agama`, `statusDate`, `namaAyah`, `namaIbu`, `pekerjaanAyah`, `pekerjaanIbu`, `noTelpAyah`, `noTelpIbu`, `namaWali`, `hubunganWali`, `noTelpWali`

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
| `siswas` | `status`, `tanggal_masuk`, `deleted_at` |
