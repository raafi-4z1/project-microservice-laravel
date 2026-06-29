# AkademikService

Microservice untuk manajemen data akademik sekolah — pembagian kelas, pengampu mapel, jam pelajaran, jadwal, pengaturan nilai, input nilai, raport, dan ranking. Hanya dapat diakses dari **Gateway** melalui autentikasi **HMAC SHA-256** — tidak ada akses langsung dari klien.

**Domain lokal:** `http://akademikservice.test` (internal only)  
**Database:** `akademik_db`

---

## Konfigurasi Environment

```env
APP_URL=http://akademikservice.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=akademik_db
DB_USERNAME=root
DB_PASSWORD=

# Harus sama persis dengan AKADEMIK_SERVICE_SECRET di Gateway
ACCEPTED_SECRETS=base64:+ZnOcffL/GId4hrnVT4YCWG8f/E8woMi8lSlRsOiZBQ=
```

---

## Endpoints (via Gateway)

Base URL: `https://gateway.test/api`

### Semester Aktif

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/akademik/semester/aktif` | Semua | Semester yang sedang berjalan |
| POST | `/akademik/semester/aktif` | SuperAdmin, Admin | Set semester aktif baru (menutup yang lama) |
| GET | `/akademik/semester/riwayat` | Semua | Riwayat semua semester, urut terbaru |

### Pembagian Kelas

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| POST | `/akademik/kelas/assign` | SuperAdmin, Admin | Daftarkan siswa ke kelas |
| PATCH | `/akademik/kelas/assign/{id}` | SuperAdmin, Admin | Pindah kelas siswa (dalam semester yang sama) |
| DELETE | `/akademik/kelas/assign/{id}` | SuperAdmin, Admin | Keluarkan siswa dari kelas (soft delete) |
| GET | `/akademik/kelas/{id}/siswa` | Semua | List siswa aktif dalam kelas |
| GET | `/akademik/siswa/{id}/kelas` | Semua | Kelas aktif siswa per semester |
| GET | `/akademik/siswa/belum-terdaftar` | SuperAdmin, Admin | Siswa yang belum masuk kelas manapun |
| GET | `/akademik/kelas/{id}/siswa/riwayat` | SuperAdmin, Admin | Semua siswa pernah di kelas (termasuk yang dipindah) |
| GET | `/akademik/siswa/{id}/kelas/riwayat` | SuperAdmin, Admin | Semua kelas pernah diikuti siswa |

### Pengampu Mapel

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| POST | `/akademik/pengampu` | SuperAdmin, Admin | Tetapkan guru pengampu mapel di kelas |
| PATCH | `/akademik/pengampu/{id}` | SuperAdmin, Admin | Ganti guru pengampu |
| DELETE | `/akademik/pengampu/{id}` | SuperAdmin, Admin | Hapus penugasan pengampu (soft delete) |
| GET | `/akademik/kelas/{id}/pengampu` | Semua | Semua pengampu mapel aktif dalam kelas |
| GET | `/akademik/guru/{id}/mapel` | Semua | Mapel aktif yang diampu guru |
| GET | `/akademik/mapel/{id}/guru` | Semua | Guru aktif pengampu mapel |
| GET | `/akademik/guru/{id}/mapel/riwayat` | SuperAdmin, Admin | Semua mapel pernah diampu guru |
| GET | `/akademik/mapel/{id}/guru/riwayat` | SuperAdmin, Admin | Semua guru pernah mengampu mapel |

### Jam Pelajaran

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/akademik/jam` | Semua | List slot jam pelajaran |
| POST | `/akademik/jam` | SuperAdmin, Admin | Tambah slot jam baru |
| PATCH | `/akademik/jam/{id}` | SuperAdmin, Admin | Update slot jam |
| DELETE | `/akademik/jam/{id}` | SuperAdmin, Admin | Hapus slot jam (gagal jika masih dipakai jadwal) |

### Jadwal Pelajaran

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| POST | `/akademik/jadwal` | SuperAdmin, Admin | Buat jadwal pelajaran baru |
| PATCH | `/akademik/jadwal/{id}` | SuperAdmin, Admin | Update jadwal |
| DELETE | `/akademik/jadwal/{id}` | SuperAdmin, Admin | Hapus jadwal (soft delete) |
| GET | `/akademik/jadwal/pengampu/{id}` | Semua | Jadwal aktif satu pengampu mapel |
| GET | `/akademik/jadwal/kelas/{id}` | Semua | Jadwal aktif seluruh kelas |
| GET | `/akademik/jadwal/guru/{id}` | Semua | Jadwal aktif seluruh guru |
| GET | `/akademik/jadwal/siswa/{id}` | Semua | Jadwal aktif siswa berdasarkan kelas yang diikuti |
| GET | `/akademik/jadwal/pengampu/{id}/riwayat` | SuperAdmin, Admin | Riwayat jadwal pengampu |
| GET | `/akademik/jadwal/kelas/{id}/riwayat` | SuperAdmin, Admin | Riwayat jadwal kelas |
| GET | `/akademik/jadwal/guru/{id}/riwayat` | SuperAdmin, Admin | Riwayat jadwal guru |

### Pengaturan Nilai

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/akademik/pengaturan-nilai` | SuperAdmin, Admin | List pengaturan bobot nilai |
| POST | `/akademik/pengaturan-nilai` | SuperAdmin, Admin | Buat pengaturan bobot nilai baru |
| PATCH | `/akademik/pengaturan-nilai/{id}` | SuperAdmin, Admin | Update bobot nilai |

### Nilai

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| POST | `/akademik/nilai` | SuperAdmin, Admin, Guru | Tambah nilai siswa |
| PATCH | `/akademik/nilai/{id}` | SuperAdmin, Admin, Guru | Update nilai siswa |
| DELETE | `/akademik/nilai/{id}` | SuperAdmin, Admin, Guru | Hapus record nilai |
| GET | `/akademik/nilai/pengampu/{id}` | SuperAdmin, Admin, Guru, Karyawan | Nilai seluruh siswa untuk satu pengampu mapel |
| GET | `/akademik/nilai/kelas/{id}` | SuperAdmin, Admin, Guru, Karyawan | Semua nilai dalam satu kelas |
| GET | `/akademik/nilai/siswa/{id}` | SuperAdmin, Admin, Guru, Karyawan | Semua nilai satu siswa |
| GET | `/akademik/nilai/saya` | Siswa | Nilai diri sendiri (khusus role Siswa) |

### Raport & Ranking

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/akademik/raport/siswa/{id}` | SuperAdmin, Admin, Guru, Karyawan | Raport satu siswa (semua mapel) |
| GET | `/akademik/raport/kelas/{id}` | SuperAdmin, Admin, Guru, Karyawan | Raport seluruh siswa dalam kelas |
| GET | `/akademik/raport/saya` | Siswa | Raport diri sendiri (khusus role Siswa) |
| GET | `/akademik/nilai/ranking/kelas/{id}` | SuperAdmin, Admin, Guru, Karyawan | Ranking siswa dalam kelas |
| GET | `/akademik/nilai/ranking/saya` | Siswa | Posisi ranking diri sendiri (khusus role Siswa) |

---

## Request Fields

### POST /akademik/semester/aktif

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `tahun_ajaran` | ✅ | Format `YYYY/YYYY`, contoh `2024/2025` |
| `semester` | ✅ | `1` atau `2` |
| `tanggal_mulai` | ✅ | Format `YYYY-MM-DD` |
| `tanggal_selesai` | ❌ | Format `YYYY-MM-DD`, harus setelah `tanggal_mulai` |

**Response:** `idSemesterAktif`, `tahunAjaran`, `semester`, `tanggalMulai`, `tanggalSelesai`, `isAktif`

### POST /akademik/kelas/assign

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `siswa_id` | ✅ | ID siswa dari SiswaService |
| `kelas_id` | ✅ | ID kelas dari ClassMicroservices |
| `tahun_ajaran` | ✅ | Format `YYYY/YYYY` |
| `semester` | ✅ | `1` atau `2` |

**Response:** `idSiswaKelas`, `siswaId`, `kelasId`, `tahunAjaran`, `semester`

**Response riwayat** (tambahan): `deletedAt` — `null` jika aktif, timestamp jika sudah dipindah

### GET /akademik/siswa/belum-terdaftar

Query params (opsional, default ke semester aktif): `tahun_ajaran`, `semester`

**Response:** `tahun_ajaran`, `semester`, `total_siswa`, `total_terdaftar`, `total_belum`, `siswa: [...]`

### POST /akademik/pengampu

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `guru_id` | ✅ | ID guru dari GuruService |
| `mapel_id` | ✅ | ID mapel dari MapelService |
| `kelas_id` | ✅ | ID kelas dari ClassMicroservices |
| `tahun_ajaran` | ✅ | Format `YYYY/YYYY` |
| `semester` | ✅ | `1` atau `2` |

**Response:** `idPengampuMapel`, `guruId`, `mapelId`, `kelasId`, `tahunAjaran`, `semester`

**Response riwayat** (tambahan): `deletedAt` — `null` jika aktif, timestamp jika sudah diganti gurunya

### POST /akademik/jam

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `ke` | ✅ | Urutan jam (1–10), unik |
| `jam_mulai` | ✅ | Format `HH:MM`, contoh `07:00` |
| `jam_selesai` | ✅ | Format `HH:MM`, harus setelah `jam_mulai` |

**Response:** `idJam`, `ke`, `jamMulai`, `jamSelesai`

### POST /akademik/jadwal

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `pengampu_mapel_id` | ✅ | ID dari POST /akademik/pengampu |
| `hari` | ✅ | `Senin` / `Selasa` / `Rabu` / `Kamis` / `Jumat` |
| `jam_mulai_id` | ✅ | ID jam mulai (dari GET /akademik/jam) |
| `jam_selesai_id` | ✅ | ID jam selesai, harus ke-nya lebih besar dari `jam_mulai_id` |
| `ruangan` | ❌ | Nama ruangan/lab (maks 50 karakter) |
| `catatan` | ❌ | Catatan tambahan (maks 500 karakter) |

Validasi bentrok otomatis: kelas yang sama tidak boleh punya 2 mapel yang overlap pada hari + jam yang sama; guru yang sama tidak boleh mengajar di 2 tempat yang overlap dalam satu semester.

**Response:** `idJadwal`, `pengampuMapelId`, `guruId`, `mapelId`, `kelasId`, `tahunAjaran`, `semester`, `hari`, `jamMulaiId`, `jamSelesaiId`, `keMulai`, `keSelesai`, `pukul` (contoh: `07:00:00 - 08:30:00`), `ruangan`, `catatan`

**Response riwayat** (tambahan): `deletedAt` — `null` jika aktif, timestamp jika sudah dihapus

### POST /akademik/pengaturan-nilai

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `tahun_ajaran` | ✅ | Format `YYYY/YYYY` |
| `semester` | ✅ | `1` atau `2` |
| `bobot_harian` | ✅ | Bobot nilai harian (0–100) |
| `bobot_uts` | ✅ | Bobot nilai UTS (0–100) |
| `bobot_uas` | ✅ | Bobot nilai UAS (0–100) |

Total `bobot_harian + bobot_uts + bobot_uas` harus = 100. Unik per `tahun_ajaran + semester`.

**Response:** `idPengaturan`, `tahunAjaran`, `semester`, `bobotHarian`, `bobotUts`, `bobotUas`

### POST /akademik/nilai

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `siswa_kelas_id` | ✅ | ID dari GET /akademik/kelas/{id}/siswa |
| `pengampu_mapel_id` | ✅ | ID dari POST /akademik/pengampu |
| `nilai_harian` | ❌ | Nilai harian (0–100) |
| `nilai_uts` | ❌ | Nilai UTS (0–100) |
| `nilai_uas` | ❌ | Nilai UAS (0–100) |

`nilai_akhir` dihitung otomatis jika ketiga komponen (nilai_harian, nilai_uts, nilai_uas) sudah terisi, menggunakan bobot dari pengaturan nilai semester yang bersangkutan.

**PATCH /akademik/nilai/{id}:** kirim hanya field yang berubah (`nilai_harian`, `nilai_uts`, dan/atau `nilai_uas`).

**Response:** `idNilai`, `siswaKelasId`, `pengampuMapelId`, `nilaiHarian`, `nilaiUts`, `nilaiUas`, `nilaiAkhir` (null jika belum semua komponen terisi)

### GET Raport

Query params (opsional): `tahun_ajaran`, `semester`

- Raport menggabungkan nilai semua mapel untuk siswa/kelas, beserta rata-rata dan bobot yang dipakai
- Role Siswa hanya dapat mengakses `/akademik/raport/saya`

### GET Ranking

Query params (opsional): `tahun_ajaran`, `semester`

**Response:** array siswa diurutkan dari rata-rata `nilai_akhir` tertinggi, dengan field tambahan `rankingSiswa` dan `totalSiswa`

---

## Retensi Data & Riwayat

Data akademik dirancang agar tidak ada yang hilang saat siswa naik kelas/semester atau guru berganti penugasan.

| Skenario | Mekanisme | Akses historis |
|----------|-----------|----------------|
| Siswa naik semester/kelas | Record baru dibuat, record lama tetap | `/akademik/siswa/{id}/kelas/riwayat` |
| Siswa pindah kelas dalam semester | Record lama di-soft-delete, record baru dibuat | `/akademik/siswa/{id}/kelas/riwayat` |
| Guru berganti mapel/kelas | Record di-update in-place (guru_id diganti) | `/akademik/guru/{id}/mapel/riwayat` |
| Pengampu mapel dihapus | Soft delete pengampu + semua jadwal terkait ikut soft-delete | `/akademik/jadwal/pengampu/{id}/riwayat` |

Endpoint `/riwayat` mengembalikan field tambahan `deletedAt` (`null` = aktif, timestamp = sudah dihapus/dipindah).

---

## Database Index

| Tabel | Index |
|-------|-------|
| `siswa_kelas` | `(kelas_id, tahun_ajaran, semester)`, `deleted_at` |
| `pengampu_mapels` | `(guru_id, tahun_ajaran, semester)`, `(kelas_id, tahun_ajaran, semester)`, `deleted_at` |
| `semester_aktif` | `is_aktif`, `(tahun_ajaran, semester)` |
| `jadwal_pelajaran` | `pengampu_mapel_id`, `deleted_at`, `(pengampu_mapel_id, hari, jam_mulai_id)` |
