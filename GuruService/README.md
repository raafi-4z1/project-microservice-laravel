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
| GET | `/guru/nama` | Semua | **id + nama saja, TERMASUK yang sudah dihapus** (`withTrashed`). Tanpa batas halaman — ringan (2 kolom, tanpa foto/PII). `?ids=1,2,3` menyaring; `ids=` yang tak menyisakan angka valid balas kosong, bukan seluruh tabel. Dipakai klien sebagai cache resolusi id→nama; entitas non-aktif ikut agar nama historis di riwayat tak tampil `#<id>`. **Bukan pengganti `/all` untuk dropdown** |
| GET | `/guru/foto/{{id}}` | sama dengan detail | **Berkas gambar** (image/webp), bukan Base64 dan bukan URL publik. Foto disimpan di disk `private`; memindahkannya ke disk publik berarti foto siapa pun bisa diambil yang menebak URL. `Cache-Control: private, max-age=86400`. Tanpa header Authorization → **401** |
| GET | `/guru` | Semua | Detail guru by `idGuru` (query param, termasuk foto). Untuk semua **non-pengelola** (Guru, Siswa, **karyawan biasa**) field pribadi (NIK, alamat, telepon, tanggal lahir, dll.) disaring — hanya SuperAdmin/Admin/**Administrator Sekolah** yang menerima profil lengkap |
| POST | `/guru` | SuperAdmin, Admin | Tambah guru baru + foto (multipart/form-data). `email`, `nik`, `nip` wajib unik — duplikat dibalas **422**, dan Gateway menolak lebih dulu bila email sudah dipakai akun user lain |
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


**Mode foto pada `/guru/all`.** Tanpa param: tidak ada field `foto`, `per_page` 1–200 (mode cache nama). Dengan `?foto=1`: tiap baris membawa `foto` berupa **URL** ke `/guru/foto/{{id}}`, dan `per_page` dibatasi 1–**25** (default 5) karena foto berat. Di luar batas → **422** dengan pesan yang menyebut batasnya, bukan dipaksa diam-diam.

**Email bisa dipakai ulang.** `gurus.email` unik di level DB sementara modelnya soft-delete, jadi baris yang "dihapus" menahan emailnya. `POST /guru` kini **memulihkan** baris itu alih-alih menolak: respons `201` + `dipulihkan: true`, dan **`id` yang dikembalikan adalah id LAMA** — disengaja, supaya tautan ke riwayat akademik tetap utuh. Data lama ditimpa data baru. Email milik record **aktif** tetap **422**; ``nik`/`nip`` milik record lain (termasuk terhapus) juga tetap **422** — nomor itu milik orang berbeda, menimpanya diam-diam lebih berbahaya daripada menolak.
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
