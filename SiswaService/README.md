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
| GET | `/siswa/all` | SuperAdmin, Admin, Adm. Sekolah, Guru, Siswa | List seluruh siswa (tanpa foto). Query: `page`, `per_page`, `search` (cari di nama/NISN; untuk viewer **Siswa** pencarian dibatasi ke **nama saja** — lihat catatan oracle di bawah). **Viewer Siswa menerima proyeksi publik** (`idSiswa`, `namaLengkap`, `jenisKelamin`, `status`) — tanpa NISN/tempat/tanggal lahir. Karyawan biasa **403** |
| GET | `/siswa/nama` | SuperAdmin, Admin, Adm. Sekolah, Guru, Siswa | **id + nama saja, TERMASUK yang sudah dihapus** (`withTrashed`). Tanpa batas halaman — ringan (2 kolom, tanpa foto/PII). `?ids=1,2,3` menyaring; `ids=` yang tak menyisakan angka valid balas kosong, bukan seluruh tabel. Dipakai klien sebagai cache resolusi id→nama; entitas non-aktif ikut agar nama historis di riwayat tak tampil `#<id>`. **Bukan pengganti `/all` untuk dropdown** |
| GET | `/siswa/foto/{{id}}` | sama dengan detail | **Berkas gambar** (image/webp), bukan Base64 dan bukan URL publik. Foto disimpan di disk `private`; memindahkannya ke disk publik berarti foto siapa pun bisa diambil yang menebak URL. `Cache-Control: private, max-age=86400`. Tanpa header Authorization → **401** |
| GET | `/siswa` | SuperAdmin, Admin, Adm. Sekolah, Guru, Siswa | Detail siswa by `idSiswa` (query param, termasuk foto). **Viewer Siswa menerima proyeksi publik** (`idSiswa`, `namaLengkap`, `jenisKelamin`, `status`, `foto`) — tanpa NISN/tanggal lahir/alamat/telepon/data orang tua. Karyawan biasa **403** |
| GET | `/siswa/saya` | Siswa | Profil **diri sendiri** (bentuk sama dengan `/siswa`, termasuk `foto`). `idSiswa` diresolve dari email token, bukan input klien. **Tidak ikut disaring** — ini profil diri sendiri, jadi lengkap; `/siswa?idSiswa=` untuk siswa lain hanya versi publik |
| POST | `/siswa` | SuperAdmin, Admin | Tambah siswa baru + foto (multipart/form-data). `email` & `nisn` wajib unik — duplikat dibalas **422**, dan Gateway menolak lebih dulu bila email sudah dipakai akun user lain |
| POST | `/siswa/update` | SuperAdmin, Admin | Update data siswa + foto opsional |
| DELETE | `/siswa/{id}` | SuperAdmin, Admin | Hapus siswa (soft delete) |
| POST | `/siswa/kartu/terbitkan` | SuperAdmin, Admin | Terbitkan/ganti kartu absensi (UID prefix `SIS-`) |
| POST | `/siswa/kartu/blokir` | SuperAdmin, Admin | Blokir kartu (`status`: `hilang`/`blokir`) |


**Mode foto pada `/siswa/all`.** Tanpa param: tidak ada field `foto`, `per_page` 1–200 (mode cache nama). Dengan `?foto=1`: tiap baris membawa `foto` berupa **URL** ke `/siswa/foto/{{id}}`, dan `per_page` dibatasi 1–**25** (default 5) karena foto berat. Di luar batas → **422** dengan pesan yang menyebut batasnya, bukan dipaksa diam-diam.

**Email bisa dipakai ulang.** `siswas.email` unik di level DB sementara modelnya soft-delete, jadi baris yang "dihapus" menahan emailnya. `POST /siswa` kini **memulihkan** baris itu alih-alih menolak: respons `201` + `dipulihkan: true`, dan **`id` yang dikembalikan adalah id LAMA** — disengaja, supaya tautan ke riwayat akademik tetap utuh. Data lama ditimpa data baru. Email milik record **aktif** tetap **422**; `nisn` milik record lain (termasuk terhapus) juga tetap **422** — nomor itu milik orang berbeda, menimpanya diam-diam lebih berbahaya daripada menolak.
**Internal (dipanggil Gateway, tidak diekspos ke klien):**

| Method | Endpoint | Keterangan |
|--------|----------|------------|
| GET | `/siswa/lookup-kartu?uid=` | Resolve UID kartu → siswa saat scan di terminal |
| POST | `/siswa/by-ids` | Lookup **batch** siswa (`{ids:[...]}`, maks 1000) → `[{idSiswa, namaLengkap, nisn, status}]` |

> `by-ids` dipakai Gateway untuk melengkapi `namaLengkap` pada respons akademik/absensi yang hanya berisi id (mis. daftar siswa saat absensi pelajaran, rekap kelas). Menggantikan pola lama "ambil SEMUA siswa lalu petakan" yang mentransfer seluruh tabel hanya untuk mencari puluhan nama — beban turun sebanding jumlah siswa sekolah.

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
