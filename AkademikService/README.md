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
ACCEPTED_SECRETS=base64:...
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

### Wali Kelas

Satu kelas punya satu wali per semester. Dipakai untuk enforcement persetujuan izin keluar (pulang awal).

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| POST | `/akademik/wali` | SuperAdmin, Admin | Tetapkan wali kelas (`guru_id`, `kelas_id`, `tahun_ajaran`, `semester`) |
| PATCH | `/akademik/wali/{id}` | SuperAdmin, Admin | Ganti guru wali |
| DELETE | `/akademik/wali/{id}` | SuperAdmin, Admin | Batalkan penugasan wali (soft delete) |
| GET | `/akademik/kelas/{id}/wali` | Semua | Wali aktif satu kelas |
| GET | `/akademik/guru/{id}/wali` | Semua | Kelas yang diwali seorang guru |

### Periode Khusus (Ramadan / ujian / libur / kegiatan)

Rentang tanggal yang mengubah aturan akademik **sementara**, lalu **otomatis kembali normal** — tidak perlu dikembalikan manual.

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/akademik/periode` | Semua | Daftar periode (filter: `tahun_ajaran`, `semester`, `jenis`) |
| GET | `/akademik/periode/aktif?tanggal=` | Semua | Periode yang berlaku pada tanggal (default hari ini WIB) |
| POST | `/akademik/periode` | SuperAdmin, Admin | Buat periode |
| PATCH | `/akademik/periode/{id}` | SuperAdmin, Admin | Ubah periode |
| DELETE | `/akademik/periode/{id}` | SuperAdmin, Admin | Hapus (soft delete) |

**Field:** `nama`, `tahun_ajaran`, `semester`, `jenis` (`ramadan`\|`ujian`\|`libur`\|`khusus`), `berlaku_dari`, `berlaku_sampai` (sama = 1 hari), `kbm_normal` (opsional), `keterangan`.
**Response:** `idPeriode`, `nama`, `tahunAjaran`, `semester`, `jenis`, `berlakuDari`, `berlakuSampai`, `kbmNormal`, `keterangan`.

> **Aturan resolusi:** kalau beberapa periode bertumpuk pada satu tanggal, yang menang adalah **rentang terpendek** (paling spesifik). Jadi libur 1 hari di tengah Ramadan otomatis mengalahkan Ramadan.
>
> `kbm_normal` default: `false` untuk `ujian` & `libur` (KBM berhenti → absensi pelajaran nonaktif dengan pesan periode), `true` untuk `ramadan` & `khusus`.

### Pengaturan Absensi

Ambang terlambat dll. Baris `periode_id = null` = default semester; `periode_id` terisi = override selama periode itu (mis. Ramadan).

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/akademik/pengaturan-absensi/efektif?tanggal=` | Semua | Aturan yang **benar-benar berlaku** pada tanggal (sudah hitung periode) |
| GET | `/akademik/pengaturan-absensi` | SuperAdmin, Admin | Daftar pengaturan |
| POST | `/akademik/pengaturan-absensi` | SuperAdmin, Admin | Buat (409 bila kombinasi semester+periode sudah ada) |
| PATCH | `/akademik/pengaturan-absensi/{id}` | SuperAdmin, Admin | Ubah jam/ambang |
| DELETE | `/akademik/pengaturan-absensi/{id}` | SuperAdmin, Admin | Hapus |

**Field:** `tahun_ajaran`, `semester`, `periode_id` (opsional), `jam_masuk_sekolah`, `batas_terlambat_siswa`, `jam_masuk_pegawai`, `batas_terlambat_pegawai`, `durasi_pin_window_menit`.
**Response efektif:** `tanggal`, `periode`, `sumber` (`periode`\|`default_semester`\|`default_sistem`), `pengaturan{...}`.

> Belum ada baris sama sekali → sistem memakai default bawaan (**07:20** untuk siswa & pegawai). Kolom `radius_geofence_m` tidak diekspos — radius geofence dipegang per-terminal (`terminals.radius_m` di Gateway).

### Jam Pelajaran

Pemetaan slot (`ke`) → jam dinding. Bisa berbeda **per periode** (Ramadan) dan **per hari** (Jumat lebih pendek).

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/akademik/jam?tanggal=` | Semua | **Set jam efektif** pada tanggal (ikut periode + hari) |
| GET | `/akademik/jam` | Semua | List mentah (filter: `periode_id`, `hari`) |
| POST | `/akademik/jam` | SuperAdmin, Admin | Tambah slot (`ke`, `jam_mulai`, `jam_selesai`, `periode_id?`, `hari?`) |
| PATCH | `/akademik/jam/{id}` | SuperAdmin, Admin | Update slot |
| DELETE | `/akademik/jam/{id}` | SuperAdmin, Admin | Hapus slot (gagal jika masih dipakai jadwal) |

- `periode_id` null = **set normal**; terisi = set milik periode itu.
- `hari` null = berlaku semua hari; terisi = khusus hari itu (**menang** atas baris semua-hari).
- Kalau sebuah periode punya set jam sendiri, set itu **menggantikan** set normal — slot yang tidak didefinisikan berarti **ditiadakan** (mis. Ramadan hanya sampai ke-6). Kalau periode tidak punya set jam, set normal dipakai.
- **Jadwal tidak perlu diduplikasi** saat Ramadan: jadwal menyimpan slot `ke`, jam dindingnya di-resolve per tanggal.

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

### Absensi — Per Pelajaran (Guru)

Guru menandai kehadiran siswa saat jam pelajarannya. Gateway meng-inject `X-Guru-Id` dari akun login.

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/akademik/absensi/pelajaran/sekarang` | Guru | Jadwal guru yang sedang berlangsung (hari+jam WIB) + daftar siswa & status hari ini |
| GET | `/akademik/absensi/pelajaran/{jadwal_id}/siswa` | SuperAdmin, Admin, Guru | Daftar siswa + status untuk 1 jadwal (guru hanya miliknya) |
| POST | `/akademik/absensi/pelajaran/tandai` | Guru | Tandai status siswa untuk jadwalnya (siswa di luar kelas diabaikan) |

### Absensi — Keluar (Pulang Awal / Izin)

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| POST | `/akademik/absensi/keluar` | SuperAdmin, Admin, Guru | Catat izin keluar; disetujui wali kelas/admin (`disetujui_oleh` = id user penyetuju) |
| GET | `/akademik/absensi/keluar` | SuperAdmin, Admin, Guru | Daftar izin keluar (filter `tanggal`, `siswa_id`) |

**Enforcement wali kelas:** jika penyetuju seorang **Guru**, ia hanya boleh menyetujui siswa di **kelas asuhannya** (Gateway meng-inject `X-Guru-Id`; service mencocokkan ke [Wali Kelas](#wali-kelas) kelas aktif siswa). Bukan wali → `403`. **Admin/SuperAdmin** melewati pengecekan ini (override). Kelas siswa belum punya wali → guru diblokir `403`, admin tetap bisa.

### Absensi — Rekap

Rentang default = awal bulan berjalan s/d hari ini (WIB); override via `tanggal_dari` & `tanggal_sampai`. Semester dari request atau semester aktif.

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/akademik/absensi/rekap/harian/kelas/{id}` | SuperAdmin, Admin, Guru, Karyawan | Rekap per siswa dalam kelas (hadir/terlambat/izin/sakit/alpa) |
| GET | `/akademik/absensi/rekap/harian/siswa/{id}` | SuperAdmin, Admin, Guru, Karyawan | Ringkasan + detail harian 1 siswa |
| GET | `/akademik/absensi/rekap/harian/saya` | Siswa | Rekap harian diri sendiri |
| GET | `/akademik/absensi/rekap/pelajaran/siswa/{id}` | SuperAdmin, Admin, Guru, Karyawan | Ringkasan absensi per pelajaran 1 siswa |
| GET | `/akademik/absensi/rekap/pelajaran/saya` | Siswa | Rekap pelajaran diri sendiri |
| GET | `/akademik/absensi/rekap/pegawai/{tipe}/{id}` | SuperAdmin, Admin | Rekap pegawai (`tipe` = `guru`\|`karyawan`) |

> **Scan kartu & absen PIN** dilayani di prefix Gateway `/absensi/*` (autentikasi **terminal**, bukan Bearer) — lihat [Gateway/README.md](../Gateway/README.md). Secara internal, endpoint scan memanggil `POST absensi/scan-siswa` & `absensi/scan-pegawai` di service ini.

> **Zona waktu:** service ini memakai `Asia/Jakarta`. Waktu masuk, ambang terlambat, dan batas "hari" absensi dihitung WIB.

### Auto-alpa (terjadwal)

Siswa yang tidak punya catatan absensi sama sekali pada hari sekolah ditandai `alpa` (`metode = turunan`, `jam_masuk` null) oleh command:

```bash
php artisan absensi:tandai-alpa                       # hari ini (WIB)
php artisan absensi:tandai-alpa --tanggal=2026-03-02  # tanggal tertentu
php artisan absensi:tandai-alpa --dry-run             # lihat dulu, jangan simpan
```

- **Dilewati** kalau: akhir pekan, atau tanggal masuk periode berjenis `libur`.
- Siswa yang sudah punya catatan apa pun (hadir/terlambat/izin/sakit) **tidak disentuh**.
- **Idempotent** — aman dijalankan berkali-kali (unique `siswa_id` + `tanggal`).
- Hanya siswa (daftarnya ada lokal di `siswa_kelas`); guru/karyawan ada di service lain.

Jadwalkan tiap sore setelah jam pulang, mis. via Windows Task Scheduler:

```powershell
# Harian 15:00
schtasks /create /tn "Absensi Auto-Alpa" /tr "php C:\path\AkademikService\artisan absensi:tandai-alpa" /sc daily /st 15:00
```

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

### POST /akademik/absensi/pelajaran/tandai

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `jadwal_id` | ✅ | ID jadwal pelajaran milik guru yang login |
| `tanggal` | ❌ | Format `YYYY-MM-DD`, default hari ini (WIB) |
| `absensi` | ✅ | Array; tiap item `{ siswa_id, status, keterangan? }` |
| `absensi.*.status` | ✅ | `hadir` \| `izin` \| `sakit` \| `alpa` |

**Response:** `jadwalId`, `tanggal`, `tersimpan` (jumlah tercatat), `dilewati` (siswa_id di luar kelas)

### POST /akademik/absensi/keluar

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `siswa_id` | ✅ | ID siswa |
| `jenis` | ✅ | `pulang_awal` \| `izin_kegiatan` \| `lomba` \| `pulang_sakit` |
| `keterangan` | ❌ | Catatan bebas |
| `jam_keluar` | ❌ | Default sekarang (WIB) |

**Response:** `idKeluar`, `siswaId`, `tanggal`, `jamKeluar`, `jenis`, `keterangan`, `disetujuiOleh`, `terminalId`

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
