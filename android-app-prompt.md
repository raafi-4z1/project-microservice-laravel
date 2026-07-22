# Prompt: Aplikasi Android "SIM Sekolah" (Kotlin + MVVM + Jetpack Compose Material 3)

Buatkan aplikasi Android native manajemen sekolah yang menjadi klien dari backend
microservice Laravel yang sudah ada. Aplikasi TIDAK membuat backend sendiri —
seluruh data diambil dari satu API Gateway.

## Tech Stack (wajib)

- **Jenis aplikasi: NATIVE Android (Kotlin)** — bukan Flutter/React Native/WebView.
  Alasan: Mode Terminal butuh akses kamera (CameraX + ML Kit) yang stabil dan
  kiosk-friendly, plus struktur KMP-ready agar layer domain/data bisa dipakai
  ulang kelak.
- **Target perangkat & versi:**
  - `minSdk 24` (Android 7.0) — mencakup hampir semua HP guru/siswa yang masih
    beredar; jangan lebih tinggi tanpa alasan kuat
  - `compileSdk` & `targetSdk` = versi stabil terbaru saat build (jangan tebak
    angkanya — pakai yang terpasang di SDK Manager lalu verifikasi lewat build)
  - **JDK 17**, Gradle Kotlin DSL + version catalog
  - Satu APK untuk semua role; **Mode Terminal adalah layar di dalam app yang
    sama** (dipicu setelah provisioning), bukan aplikasi terpisah
- Kotlin, struktur project KMP-ready: layer domain/data (model, repository,
  networking, session) dipisah dari layer UI agar kelak bisa di-share ke iOS/desktop
- Arsitektur MVVM + Repository pattern, unidirectional data flow (UI State via StateFlow)
- Jetpack Compose dengan Material 3, Navigation Compose
- Ktor Client + kotlinx.serialization untuk networking (multiplatform)
- Koin untuk dependency injection (multiplatform, jangan pakai Hilt/Dagger)
- Coroutines + Flow
- multiplatform-settings (atau DataStore di sisi Android) untuk menyimpan
  access token & data sesi
- Coil 3 untuk menampilkan gambar (foto dikirim sebagai data-URI base64 WebP);
  aktifkan dukungan **SVG** (coil-svg) untuk menampilkan QR kartu dari `/kartu/qr`
- **Mode Terminal (kiosk absensi):** CameraX + ML Kit Barcode Scanning untuk
  memindai QR kartu; Play Services Location (FusedLocationProvider) untuk lat/lng
  saat terminal mode demo (geofence). Deklarasikan izin `CAMERA` dan
  `ACCESS_FINE_LOCATION` (lokasi hanya diminta di alur Mode Terminal demo).

## Struktur Folder (wajib diikuti)

Single-module, package-by-feature, dengan pemisahan layer yang ketat agar
kelak folder `core/network`, `core/session`, `domain`, dan `data` bisa
dipindah ke `commonMain` modul shared KMP tanpa refactor. Aturan keras:
composable dan ViewModel TIDAK boleh memakai Ktor/DTO secara langsung —
semua akses data lewat interface repository di `domain`.

```
app/src/main/java/com/sekolah/app/
├── core/
│   ├── di/              # Koin modules (networkModule, repositoryModule, viewModelModule)
│   ├── network/         # Ktor HttpClient (Bearer + handler 401) + client TERPISAH
│   │                    #   untuk Mode Terminal (header X-Terminal-Id/Token)
│   ├── session/         # SessionManager (token, role) — multiplatform-settings
│   ├── designsystem/    # Theme M3, Color, Type, komponen reusable (AppCard, EmptyState…)
│   └── util/            # Result wrapper, formatter tanggal, validator
├── domain/
│   ├── model/           # Model murni Kotlin (Siswa, Guru, Nilai, Jadwal…)
│   └── repository/      # Interface (SiswaRepository, AkademikRepository…)
├── data/
│   ├── remote/dto/      # DTO @Serializable (request snake_case, response camelCase)
│   ├── remote/api/      # Fungsi Ktor per service (AuthApi, SiswaApi, AkademikApi…)
│   └── repository/      # Implementasi interface domain, mapping DTO → model
├── feature/
│   ├── auth/            # LoginScreen + LoginViewModel
│   ├── dashboard/
│   ├── siswa/           # list/, detail/, form/
│   ├── guru/
│   ├── karyawan/        # list/, detail/, form/
│   ├── mapel/
│   ├── kelas/
│   ├── akademik/        # pembagian kelas, pengampu, wali kelas, jam, jadwal
│   ├── nilai/           # input nilai, pengaturan bobot
│   ├── raport/          # raport + ranking
│   ├── absensi/         # kartu/, terminal/ (kiosk scan+PIN), pelajaran/,
│   │                    #   pinwindow/, keluar/, rekap/
│   ├── usermanagement/
│   └── profil/
└── navigation/          # NavHost, route sealed class, bottom bar / nav rail
```

Setiap package feature berisi Screen (composable), ViewModel, dan UiState-nya
sendiri. Komponen UI yang dipakai lebih dari satu feature diletakkan di
`core/designsystem`, bukan saling impor antar feature.

## Backend / API

- Satu base URL Gateway, contoh dev: `https://gateway.test/api`
  (buat base URL configurable via BuildConfig/setting, karena saat development
  memakai sertifikat lokal mkcert — sediakan opsi trust-all HANYA untuk build debug)
- Autentikasi: OAuth2 Laravel Passport — `POST /login` (body: email, password,
  device_name). **Kirim `device_name: "android"`** agar sesi app tidak saling
  tendang dengan sesi web. Respons sukses: `data.token` (Bearer token),
  `data.user` (nama), `data.email`, `data.role`, `data.mustChangePassword`
  (boolean). Login gagal mengembalikan HTTP **400** dengan
  `resMsg: "Invalid user credentials."`. Semua request lain memakai header
  `Authorization: Bearer {token}`.
- **Wajib ganti password saat login pertama**: akun guru/siswa dibuat otomatis
  dengan password default. Jika `data.mustChangePassword == true`, arahkan user
  ke layar Ganti Password WAJIB (tidak bisa di-skip, back ditahan, hanya tombol
  logout sebagai jalan keluar) sebelum masuk dashboard. Selama flag aktif,
  server memblokir semua endpoint lain dengan 403 +
  `data.mustChangePassword: true` — jika 403 berbentuk ini diterima kapan pun,
  arahkan ke layar yang sama (jangan tampilkan pesan "tidak berhak" biasa).
  Setelah `POST /password` sukses, lanjut ke dashboard.
- Token berlaku 8 jam; **satu sesi aktif per device**: login baru hanya mencabut
  token lama dari `device_name` yang sama (login ulang di HP lain menendang sesi
  android sebelumnya, tapi sesi web tetap hidup) → jika respons 401, arahkan user
  kembali ke layar Login.
- **Perpanjangan sesi**: `POST /refresh` (dengan Bearer token yang masih valid)
  mengembalikan token baru 8 jam dengan bentuk respons sama seperti login
  (`data.token`, `data.user`, `data.email`, `data.role`); token lama otomatis
  dicabut dan `device_name` diwarisi dari token lama. Simpan waktu login/refresh
  terakhir; saat app dibuka dan umur token > 6 jam, panggil `/refresh` di
  background dan ganti token tersimpan. Token yang sudah kedaluwarsa tidak bisa
  di-refresh (401) → login ulang.
- `POST /logout` mencabut token device ini saja. `POST /logout-all` mencabut
  semua sesi di semua device (sediakan di menu keamanan/profil, untuk kasus akun
  dicurigai dipakai orang lain). `POST /password` ganti password sendiri
  (current_password, new_password, confirm_password).
- `GET /user` mengembalikan profil akun yang login: `data.id`, `data.name`,
  `data.email`, `data.role`, `data.must_change_password` (snake_case di sini,
  berbeda dari `mustChangePassword` di response login). Saat Splash memvalidasi
  token tersimpan via `GET /user`, cek field ini juga — jika `true`, arahkan ke
  layar Ganti Password wajib, bukan dashboard.

## Role & RBAC (UI harus menyesuaikan role dari GET /user)

- **SuperAdmin / Admin**: akses penuh CRUD semua modul, register akun baru
  (`POST /register`: name, email, password, confirm_password, role=Admin|Guru|Siswa|Karyawan;
  Admin tidak bisa membuat Admin lain), manajemen user
  (`GET /users` — paginated, mendukung `?role=` dan `?search=`; `GET /users/{id}`,
  `POST /users/{id}/password`, `DELETE /users/{id}`).
- **Guru**: read-only data master; boleh input/update/hapus nilai — HANYA untuk
  mapel yang diampunya sendiri (server memvalidasi via lookup email, 403 jika
  bukan pengampunya); lihat jadwal mengajar.
- **Siswa**: read-only terbatas; punya endpoint khusus `nilai/saya`, `raport/saya`,
  `ranking/saya`, dan jadwal kelasnya. **Privasi**: Siswa TIDAK bisa membuka detail
  siswa lain (`GET /siswa` → 403) dan menerima detail guru yang sudah disaring
  (lihat modul Guru) — jangan tampilkan navigasi ke detail siswa untuk role ini.
- **Karyawan** (staf TU): read-only data master (termasuk detail siswa/guru
  lengkap) + akademik/nilai/raport. Ikut **absen sebagai pegawai** (kartu/PIN)
  dan bisa mengatur PIN sendiri + melihat rekap absensinya.
- **Absensi (lintas role):** SuperAdmin/Admin mengelola kartu, wali kelas,
  jendela PIN, dan pendaftaran terminal; **Guru** menandai absensi siswa saat
  jam pelajarannya dan — sebagai **wali kelas** — menyetujui izin keluar siswa
  kelas asuhannya; **Siswa** melihat rekap absensinya (`/rekap/*/saya`);
  **Guru/Karyawan** mengatur PIN sendiri & melihat rekap pegawainya. Scan kartu
  di gerbang/kantor dilakukan lewat **Mode Terminal** (perangkat sekolah dengan
  autentikasi terminal, bukan login user — lihat modul Absensi).

Sembunyikan menu & tombol aksi yang tidak sesuai role.

## Modul & Endpoint (semua relatif ke base URL)

### 1. Mata Pelajaran

- `GET /mapel/all`, `GET /mapel?idPelajaran={id}`
- `POST /mapel` (tambah: kode, namaPelajaran, keterangan?), `POST /mapel/update`
  (update, kirim idPelajaran + field berubah), `DELETE /mapel/{id}`
- **Respons**: `idPelajaran`, `kode`, `namaPelajaran`, `keterangan`

### 2. Kelas (ruang kelas)

- `GET /class/all`, `GET /class?idKelas={id}`
- `POST /class` (noKelas, tingkat 1–3, jurusan, limitSiswa), `POST /class/update`
  (idKelas + field berubah), `DELETE /class/{id}`
- **Respons**: `idKelas`, `namaKelas` (nama jadi, contoh `"X MIPA 1"` — dibentuk
  server dari tingkat+jurusan+noKelas), `tingkat`, `jurusan`, `limitSiswa`,
  `deletedAt` (null jika aktif)

### 3. Guru (dengan foto, multipart/form-data)

- `GET /guru/all` (list tanpa foto), `GET /guru?idGuru={id}` (detail + foto)
- `POST /guru` multipart (data + file foto), `POST /guru/update` (idGuru + field berubah,
  foto opsional), `DELETE /guru/{id}`
- Foto upload: JPEG/PNG/JPG maks 2 MB, minimal 360×480 px.
  Foto pada respons detail berupa string `data:image/webp;base64,...` — render dengan Coil.
- **Respons list**: `idGuru`, `namaLengkap`, `nip`, `email`, `jabatan`, `statusKepegawaian`
- **Respons detail — bergantung role!** SuperAdmin/Admin/Karyawan menerima profil
  lengkap (tambah: `nik`, `telephone`, `jenisKelamin`, `tempatLahir`, `tanggalLahir`,
  `alamat`, `agama`, `statusPernikahan`, `tanggalMasuk`, `pendidikanTerakhir`,
  `jurusan`, `universitas`, `tahunLulus`, `nomorSKPengangkatan`, `nomorSertifikasi`,
  `pelatihan`, `foto`). Untuk role **Guru/Siswa** Gateway menyaring detail menjadi
  HANYA: `idGuru`, `namaLengkap`, `nip`, `email`, `jabatan`, `statusKepegawaian`,
  `pendidikanTerakhir`, `foto` — buat SEMUA field DTO detail nullable agar aman
  di kedua bentuk.

### 4. Siswa (dengan foto, multipart/form-data)

- `GET /siswa/all` (list, semua role), `GET /siswa?idSiswa={id}` (detail —
  **hanya SuperAdmin/Admin/Guru/Karyawan; role Siswa mendapat 403** karena berisi
  data pribadi), `POST /siswa`, `POST /siswa/update`, `DELETE /siswa/{id}`
- Field tambah siswa (multipart): email, nisn, namaLengkap, telephone,
  jenisKelamin (Laki-Laki|Perempuan), tempatLahir, tanggalLahir (YYYY-MM-DD),
  tanggalMasuk, alamat, namaIbu, foto (wajib); opsional: agama, namaAyah,
  pekerjaanAyah, pekerjaanIbu, noTelpAyah, noTelpIbu, namaWali, hubunganWali, noTelpWali.
- **Respons list**: `idSiswa`, `namaLengkap`, `nisn`, `jenisKelamin`, `tempatLahir`,
  `tanggalLahir`, `tanggalMasuk`, `status`
- **Respons detail** (menambah): `email`, `telephone`, `alamat`, `agama`,
  `namaAyah`, `namaIbu`, `pekerjaanAyah`, `pekerjaanIbu`, `noTelpAyah`, `noTelpIbu`,
  `namaWali`, `hubunganWali`, `noTelpWali`, `foto`

### 4b. Karyawan (dengan foto, multipart/form-data)

- `GET /karyawan/all` (list), `GET /karyawan?idKaryawan={id}` (detail),
  `POST /karyawan` multipart, `POST /karyawan/update` (idKaryawan + field berubah),
  `DELETE /karyawan/{id}`
- Field tambah (multipart): email, nip, namaLengkap, jabatan (wajib); opsional:
  statusKepegawaian, jenisKelamin (Laki-Laki|Perempuan), noTelp, alamat, foto.
- **Respons list**: `idKaryawan`, `namaLengkap`, `nip`, `email`, `jabatan`, `statusKepegawaian`
- **Respons detail — bergantung role!** SuperAdmin/Admin/Karyawan menerima
  profil lengkap (tambah: `jenisKelamin`, `noTelp`, `alamat`, `foto`); role
  **Guru/Siswa** hanya menerima field publik (`idKaryawan`, `namaLengkap`, `nip`,
  `email`, `jabatan`, `statusKepegawaian`, `foto`) — buat field DTO detail nullable.
- Membuat karyawan otomatis membuat akun user role Karyawan (password default =
  email, `mustChangePassword=true`), sama seperti Guru/Siswa.

### 5. Akademik (prefix `/akademik`)

- **Semester**: `GET /akademik/semester/aktif`, `GET /akademik/semester/riwayat`,
  `POST /akademik/semester/aktif` (tahun_ajaran "YYYY/YYYY", semester 1|2, tanggal_mulai).
  Respons: `idSemesterAktif`, `tahunAjaran`, `semester`, `tanggalMulai`,
  `tanggalSelesai`, `isAktif`. Ambil semester aktif sekali saat app start dan
  pakai sebagai default `tahun_ajaran`/`semester` di semua layar akademik.
- **Pembagian kelas**: `POST /akademik/kelas/assign` (siswa_id, kelas_id, tahun_ajaran,
  semester), `PATCH /akademik/kelas/assign/{id}` (kelas_id tujuan),
  `DELETE /akademik/kelas/assign/{id}`,
  `GET /akademik/kelas/{id}/siswa`, `GET /akademik/siswa/{id}/kelas`,
  `GET /akademik/siswa/belum-terdaftar`, plus varian `/riwayat` (SuperAdmin/Admin).
  Respons: `idSiswaKelas`, `siswaId`, `kelasId`, `tahunAjaran`, `semester`
  (+`deletedAt` di varian riwayat).
- **Pengampu mapel**: `POST /akademik/pengampu` (guru_id, mapel_id, kelas_id,
  tahun_ajaran, semester), `PATCH /akademik/pengampu/{id}` (guru_id pengganti),
  `DELETE /akademik/pengampu/{id}`,
  `GET /akademik/kelas/{id}/pengampu`, `GET /akademik/guru/{id}/mapel`,
  `GET /akademik/mapel/{id}/guru`, plus varian `/riwayat` (SuperAdmin/Admin).
  Respons: `idPengampuMapel`, `guruId`, `mapelId`, `kelasId`, `tahunAjaran`, `semester`.
- **Periode khusus** (Ramadan/ujian/libur/kegiatan) — rentang tanggal yang
  mengubah aturan sementara lalu otomatis kembali normal:
  `GET /akademik/periode` (filter tahun_ajaran/semester/jenis),
  `GET /akademik/periode/aktif?tanggal=` (periode yang berlaku; **rentang
  terpendek menang** — libur 1 hari mengalahkan Ramadan),
  `POST /akademik/periode` (nama, tahun_ajaran, semester, jenis
  `ramadan|ujian|libur|khusus`, berlaku_dari, berlaku_sampai, kbm_normal?,
  keterangan?), `PATCH|DELETE /akademik/periode/{id}` (SuperAdmin/Admin).
  Respons: `idPeriode`, `nama`, `tahunAjaran`, `semester`, `jenis`,
  `berlakuDari`, `berlakuSampai`, `kbmNormal`, `keterangan`.
  **`kbmNormal:false`** (ujian/libur) → endpoint absensi pelajaran mengembalikan
  `data: []` + pesan periode; tampilkan sebagai empty state, bukan error.
- **Pengaturan absensi**: `GET /akademik/pengaturan-absensi/efektif?tanggal=`
  (semua role — aturan yang benar-benar berlaku; `sumber` =
  `periode|default_semester|default_sistem`, isi `pengaturan{jamMasukSekolah,
  batasTerlambatSiswa, jamMasukPegawai, batasTerlambatPegawai,
  durasiPinWindowMenit}`); `GET|POST /akademik/pengaturan-absensi`,
  `PATCH|DELETE /akademik/pengaturan-absensi/{id}` (SuperAdmin/Admin —
  `periode_id` null = default semester, terisi = override periode; 409 bila
  kombinasi sudah ada).
- **Jam pelajaran**: `GET /akademik/jam?tanggal=` → **set jam EFEKTIF** pada
  tanggal itu (sudah ikut periode + hari) — respons `{tanggal, hari, periode,
  jam:[...]}`. `GET /akademik/jam` (list mentah, filter `periode_id`/`hari`),
  `POST /akademik/jam` (ke 1–10, jam_mulai/jam_selesai "HH:MM", `periode_id?`,
  `hari?`), `PATCH|DELETE /akademik/jam/{id}`.
  Respons: `idJam`, `periodeId`, `hari`, `ke`, `jamMulai`, `jamSelesai`.
  - `periode_id` null = set normal; `hari` null = semua hari (baris ber-`hari`
    spesifik **menang**, mis. Jumat lebih pendek).
  - **Jangan cache jam lintas tanggal** — jam bisa berbeda per tanggal (Ramadan)
    dan per hari (Jumat). Untuk menampilkan jadwal hari tertentu, ambil jam
    efektif tanggal itu; `pukul` di respons absensi pelajaran sudah ter-resolve.
- **Jadwal**: `POST /akademik/jadwal` (pengampu_mapel_id, hari Senin–Jumat,
  jam_mulai_id, jam_selesai_id, ruangan?, catatan? — server menolak 409 jika
  bentrok kelas/guru), `PATCH|DELETE /akademik/jadwal/{id}`,
  `GET /akademik/jadwal/kelas/{id}`, `/jadwal/guru/{id}`, `/jadwal/siswa/{id}`,
  `/jadwal/pengampu/{id}`, plus varian `/riwayat` (SuperAdmin/Admin).
  Respons: `idJadwal`, `pengampuMapelId`, `guruId`, `mapelId`, `kelasId`,
  `tahunAjaran`, `semester`, `hari`, `jamMulaiId`, `jamSelesaiId`, `keMulai`,
  `keSelesai`, `pukul` (string siap tampil, contoh `"07:00:00 - 08:30:00"`),
  `ruangan`, `catatan`.
  - **`pukul` mengikuti tanggal**: semua GET jadwal menerima query `tanggal`
    (default hari ini WIB). `pukul` di-resolve dari periode + hari — saat
    Ramadan otomatis jam Ramadan, hari Jumat otomatis jam Jumat. Untuk layar
    jadwal mingguan, kirim `tanggal` sesuai minggu yang ditampilkan.
  - Kalau slot **ditiadakan** pada periode itu (mis. Ramadan hanya sampai ke-6),
    field `pukul` **tidak ada** di respons → tampilkan sebagai "tidak ada
    pelajaran", bukan crash. Identitas slot tetap di `keMulai`/`keSelesai`.
- **Pengaturan nilai** (SuperAdmin/Admin saja, termasuk GET):
  `GET|POST /akademik/pengaturan-nilai`, `PATCH /akademik/pengaturan-nilai/{id}`
  (bobot_harian + bobot_uts + bobot_uas = 100).
  Respons: `idPengaturan`, `tahunAjaran`, `semester`, `bobotHarian`, `bobotUts`, `bobotUas`.
- **Nilai**: `POST /akademik/nilai` (siswa_kelas_id, pengampu_mapel_id, nilai_harian?,
  nilai_uts?, nilai_uas? — masing-masing 0–100; nilai_akhir dihitung otomatis server),
  `PATCH|DELETE /akademik/nilai/{id}`, `GET /akademik/nilai/pengampu/{id}`,
  `/nilai/kelas/{id}`, `/nilai/siswa/{id}`, `/nilai/saya` (khusus Siswa).
  Query `tahun_ajaran`/`semester` di GET nilai bersifat OPSIONAL (filter).
  Respons: `idNilai`, `siswaKelasId`, `pengampuMapelId`, `nilaiHarian`, `nilaiUts`,
  `nilaiUas`, `nilaiAkhir` (null sampai ketiga komponen terisi).
- **Raport & ranking**: `GET /akademik/raport/siswa/{id}`, `/raport/kelas/{id}`,
  `/raport/saya`, `GET /akademik/nilai/ranking/kelas/{id}`, `/nilai/ranking/saya`.
  Query `tahun_ajaran` dan `semester` **WAJIB** di semua endpoint raport/ranking
  (422 jika kosong) — isi otomatis dari semester aktif.
  - Respons raport siswa: `{ siswaId, tahunAjaran, semester, bobot: {bobotHarian,
    bobotUts, bobotUas}, nilai: [{idNilai, pengampuMapelId, guruId, mapelId,
    nilaiHarian, nilaiUts, nilaiUas, nilaiAkhir}], rataRata }`
  - Respons raport kelas: `{ kelasId, tahunAjaran, semester, bobot,
    siswa: [{siswaId, siswaKelasId, nilai: [...], rataRata}] }`
  - Respons ranking kelas: `{ kelasId, tahunAjaran, semester, totalSiswa,
    ranking: [{peringkat, siswaId, rataRata}] }`
  - Respons `ranking/saya`: `{ kelasId, tahunAjaran, semester, totalSiswa,
    peringkat, rataRata }` (posisi diri sendiri saja, tanpa daftar siswa lain)

**PENTING — respons akademik hanya berisi ID relasi** (`guruId`, `mapelId`,
`kelasId`, `siswaId`), TIDAK ada nama guru/mapel/siswa ter-embed. App wajib
me-resolve nama dengan mengambil master data (`/guru/all`, `/mapel/all`,
`/class/all`, `/siswa/all` per kelas) lalu join di repository layer — cache
master data ini di memori per sesi agar layar jadwal/nilai/raport tidak
memanggil API berulang. (Pengecualian: absensi pelajaran & rekap kelas sudah
menyertakan `namaLengkap` siswa.)

- **Wali kelas** (SuperAdmin/Admin): `POST /akademik/wali` (guru_id, kelas_id,
  tahun_ajaran, semester — satu wali per kelas/semester, 409 jika sudah ada),
  `PATCH /akademik/wali/{id}` (guru_id pengganti), `DELETE /akademik/wali/{id}`,
  `GET /akademik/kelas/{id}/wali`, `GET /akademik/guru/{id}/wali`.
  Respons: `idWaliKelas`, `guruId`, `kelasId`, `tahunAjaran`, `semester`.
  Wali kelas dipakai untuk enforcement persetujuan izin keluar (lihat modul Absensi).

### 6. Absensi

Modul absensi kartu/QR untuk siswa (gerbang) dan pegawai (guru/karyawan, kantor),
absensi per pelajaran oleh guru, PIN saat lupa kartu, izin keluar/pulang awal,
dan rekap. **Waktu & ambang terlambat dihitung WIB (Asia/Jakarta) oleh server.**

**6a. Kartu absensi** (SuperAdmin/Admin) — Bearer:
- `POST /siswa/kartu/terbitkan` (`{idSiswa}`), `POST /siswa/kartu/blokir`
  (`{idSiswa, status:"hilang"|"blokir"}`); guru & karyawan serupa via
  `/guru/kartu/*` (`idGuru`) dan `/karyawan/kartu/*` (`idKaryawan`).
  Respons terbitkan: `idSiswa`, `kartuUid` (opaque, prefix SIS-/GUR-/KAR-),
  `kartuStatus`, `kartuDiterbitkanAt`. Terbit-ulang menimpa UID lama.
- `GET /kartu/qr?data=<kartuUid>` → **image/svg+xml** (BUKAN JSON) — untuk dicetak
  ke kartu fisik. Render SVG (mis. tampilkan via WebView/`AndroidSvg`/Coil-SVG).

**6b. Mode Terminal (scan di gerbang/kantor)** — autentikasi **terminal**, bukan
Bearer. Kirim header `X-Terminal-Id` + `X-Terminal-Token` (disimpan aman di
perangkat terminal, di-set sekali saat setup kiosk). Body pakai snake_case.
- `POST /absensi/scan` (`{kartu_uid, lat?, lng?}`) — pindai QR kartu lalu kirim
  `kartu_uid`. `lat`/`lng` HANYA untuk terminal mode demo (geofence); mode
  produksi memakai allowlist IP LAN. Respons sukses: `idAbsensi`, `subjek`
  (siswa|guru|karyawan), `siswaId`/`subjekId`, `tanggal`, `jamMasuk`, `status`
  (`hadir`|`terlambat`), `metode`, `sudahAbsen` (true bila sudah absen hari ini).
- `POST /absensi/pin/absen` (`{subjek_tipe:"guru"|"karyawan", nip, pin, lat?, lng?}`)
  — absen pegawai saat lupa kartu; hanya berhasil jika ada jendela PIN aktif.
- **Kode status terminal**: `401` tanpa/`X-Terminal-*` salah, `403` di luar
  geofence (demo) atau di luar LAN (produksi) atau kartu tidak aktif,
  `404` kartu tidak dikenali. Tampilkan pesan `resMsg` di layar terminal.

**6c. Absensi per pelajaran** (Guru) — Bearer; server resolve guru dari akun:
- `GET /akademik/absensi/pelajaran/sekarang` → jadwal yang sedang berlangsung +
  daftar siswa: `{jadwalId, mapelId, kelasId, hari, pukul, tahunAjaran, semester,
  tanggal, siswa:[{siswaId, namaLengkap, status|null, keterangan|null}]}`;
  `data: []` jika tak ada jam pelajaran saat ini.
- `GET /akademik/absensi/pelajaran/{jadwal_id}/siswa?tanggal=` → sama untuk jadwal
  tertentu (guru hanya boleh jadwalnya sendiri, 403 jika bukan).
- `POST /akademik/absensi/pelajaran/tandai`
  (`{jadwal_id, tanggal?, absensi:[{siswa_id, status:"hadir"|"izin"|"sakit"|"alpa", keterangan?}]}`)
  → `{jadwalId, tanggal, tersimpan, dilewati:[siswa_id di luar kelas]}`.

**6d. Jendela PIN (lupa kartu)**:
- `POST /absensi/pin/atur` (`{pin}` 4-6 digit) — **Guru/Karyawan** atur PIN sendiri.
- `POST /absensi/pin/buka` (`{subjek_tipe, subjek_id, durasi_menit?}`) —
  **SuperAdmin/Admin** buka jendela (default 10 mnt, sekali pakai). Respons:
  `idPinWindow`, `subjekTipe`, `subjekId`, `berlakuSampai`, `durasiMenit`.

**6e. Izin keluar / pulang awal** — Bearer:
- `POST /akademik/absensi/keluar`
  (`{siswa_id, jenis:"pulang_awal"|"izin_kegiatan"|"lomba"|"pulang_sakit", keterangan?}`)
  — **SuperAdmin/Admin/Guru**. **Enforcement wali kelas:** jika penyetuju seorang
  Guru, ia HANYA boleh menyetujui siswa di kelas asuhannya → `403` jika bukan
  wali (atau kelas belum punya wali); Admin/SuperAdmin override. Respons:
  `idKeluar`, `siswaId`, `tanggal`, `jamKeluar`, `jenis`, `keterangan`,
  `disetujuiOleh`, `terminalId`.
- `GET /akademik/absensi/keluar?tanggal=&siswa_id=` — daftar izin keluar.

**6f. Rekap absensi** (rentang default = awal bulan s/d hari ini WIB; override
`tanggal_dari`/`tanggal_sampai`):
- `GET /akademik/absensi/rekap/harian/kelas/{id}` (Admin/Guru/Karyawan) → per
  siswa `{siswaId, namaLengkap, hadir, terlambat, izin, sakit, alpa, total}`.
- `GET /akademik/absensi/rekap/harian/siswa/{id}` → `{ringkasan:{...counts,total},
  detail:[{tanggal, jamMasuk, status, metode}]}`.
- `GET /akademik/absensi/rekap/harian/saya` (Siswa) — rekap harian sendiri.
- `GET /akademik/absensi/rekap/pelajaran/siswa/{id}`, `/pelajaran/saya` (Siswa).
- `GET /akademik/absensi/rekap/pegawai/{tipe}/{id}` (Admin; `tipe`=guru|karyawan).

**6g. Enum & catatan DTO absensi (untuk menampilkan record):**
- `status` harian/pegawai: `hadir` | `terlambat` | `izin` | `sakit` | `alpa`
  (pegawai juga `dinas_luar`). `status` pelajaran: `hadir` | `izin` | `sakit` | `alpa`.
- `metode`: `scan` (kartu di terminal) | `pin` (lupa kartu) | `manual` (dicatat
  petugas) | **`turunan`** (ditandai OTOMATIS oleh sistem — auto-alpa). Tampilkan
  `alpa` bermetode `turunan` sebagai mis. "Alpa (otomatis)".
- **`jamMasuk` bisa `null`** — untuk `izin`/`sakit`/`alpa` (termasuk auto-alpa)
  tidak ada jam scan. JANGAN asumsikan selalu ada; tampilkan "-" bila null.
- **Auto-alpa** dijalankan backend terjadwal (bukan aksi app). App tidak pernah
  memicunya — hanya menampilkan hasilnya. Hari **libur** & akhir pekan tidak
  ditandai (tidak ada record) → di rekap, tanggal itu wajar kosong, bukan alpa.

## Konvensi data (penting)

- **Semua respons Gateway memakai envelope tetap** — buat satu generic wrapper
  `ApiResponse<T>` untuk ini:

  ```json
  {
    "resCode": 200,
    "resPhrase": "Ok",
    "resStatus": "success",   // "success" atau "fail"
    "resMsg": "Access granted.",
    "data": { ... }           // isi sebenarnya; bisa objek, array, atau []
  }
  ```

- **Error validasi (422)**: `resMsg` selalu berisi pesan error pertama —
  tampilkan `resMsg` sebagai error utama di form/snackbar. Bentuk `data`-nya
  TIDAK konsisten antar modul (endpoint auth mengirim array string, endpoint
  master/akademik mengirim map field→array pesan) — jangan parsing `data` untuk
  error 422, cukup andalkan `resMsg`; buat field `data` di DTO error bertipe
  `JsonElement?`/dinamis agar tidak gagal deserialisasi.
- **Endpoint list (`/siswa/all`, `/guru/all`, dst.) menggunakan pagination
  gaya Laravel**: query param `page` dan `per_page` (default server = 5,
  kirim `per_page` yang lebih besar, mis. 20). Respons berisi field paginator
  (`current_page`, `last_page`, `total`, `data: [...]`). Implementasikan
  infinite scroll / load-more berdasarkan `current_page < last_page`.
- **Search**: endpoint list mendukung query param `search` (partial match,
  maks 100 karakter, bisa digabung `page`/`per_page`):
  `/guru/all?search=` (nama/NIP/email/jabatan), `/siswa/all?search=` (nama/NISN),
  `/mapel/all?search=` (kode/nama pelajaran), `/class/all?search=`
  (nama kelas/jurusan), `/users?search=` (nama/email, juga ada filter `role`).
  Buat search bar dengan debounce (~400ms) yang mengirim `search` sebagai query
  param dan me-reset pagination ke halaman 1.
- **Konvensi penamaan field (campuran — ikuti persis):**
  - REQUEST modul **akademik** (`/akademik/*`): snake_case
    (`siswa_id`, `tahun_ajaran`, `pengampu_mapel_id`, `bobot_harian`)
  - REQUEST modul **master** (guru/siswa/kelas/mapel): camelCase
    (`namaLengkap`, `limitSiswa`, `jenisKelamin`, `idGuru`, `namaPelajaran`)
  - REQUEST modul **auth/user**: snake_case
    (`device_name`, `current_password`, `new_password`, `confirm_password`)
  - RESPONSE: camelCase di semua modul (`idSiswa`, `namaLengkap`, `nilaiAkhir`,
    `tahunAjaran`). Pengecualian: `GET /akademik/siswa/belum-terdaftar` yang
    mengembalikan `tahun_ajaran`, `semester`, `total_siswa`, `total_terdaftar`,
    `total_belum`, `siswa: [...]`.
  - REQUEST modul **absensi** campuran: endpoint **kartu**
    (`/siswa|guru|karyawan/kartu/*`) camelCase (`idSiswa`, `status`); endpoint
    **scan/PIN** (`/absensi/*`) dan **absensi akademik** (`/akademik/absensi/*`)
    snake_case (`kartu_uid`, `subjek_tipe`, `siswa_id`, `jadwal_id`,
    `tanggal_dari`, `durasi_menit`). RESPONSE tetap camelCase.
- **Autentikasi terminal (Mode Terminal):** buat Ktor client / interceptor
  TERPISAH dari client Bearer user, yang menyisipkan header `X-Terminal-Id` +
  `X-Terminal-Token` (bukan Authorization). Token terminal disimpan terenkripsi
  (Keystore/EncryptedDataStore) dan di-set sekali saat provisioning kiosk —
  jangan campur dengan sesi login user.
- **Zona waktu:** semua waktu absensi (`jamMasuk`, `jamKeluar`, `tanggal`,
  `berlakuSampai`) sudah dalam WIB (Asia/Jakarta). Tampilkan apa adanya —
  JANGAN konversi zona waktu perangkat.
- **QR kartu:** `GET /kartu/qr` mengembalikan `image/svg+xml` (bukan envelope
  `ApiResponse`) — tangani sebagai respons biner/teks SVG terpisah.
- Update guru/siswa/kelas/mapel memakai `POST .../update` (bukan PUT/PATCH),
  kirim hanya field yang berubah; update akademik memakai `PATCH`.
- Detail guru/siswa/kelas/mapel memakai query param (`?idGuru=`), bukan path param;
  DELETE memakai path param (`/guru/{id}`).
- Login gagal = HTTP 400 (bukan 401) — jangan menganggap 400 sebagai sesi
  kedaluwarsa; hanya 401 yang memicu auto-logout.
- Kode status lain yang harus ditangani: **403** = role tidak berhak (tampilkan
  pesan "tidak berhak", jangan logout — KECUALI jika `data.mustChangePassword ==
  true`: arahkan ke layar Ganti Password wajib), **409** = konflik bisnis (jadwal
  bentrok, kelas penuh, siswa sudah terdaftar, slot jam / periode / wali sudah
  ada — tampilkan `resMsg` apa adanya), **422** = error validasi,
  **429** = rate limit.
  - Catatan: duplikat **jam pelajaran** (`periode_id`+`hari`+`ke` sama) menjawab
    **409** (bukan 422) — samakan penanganannya dengan konflik lain.
- `POST /login`, `POST /refresh`, dan `POST /password` dibatasi 5 percobaan/menit
  → tangani 429 dengan pesan yang ramah (`resMsg` berisi instruksi tunggu).

## Layar yang dibutuhkan

1. Splash (cek token tersimpan) → Login → (jika `mustChangePassword`)
   layar Ganti Password wajib → Dashboard
2. Home/Dashboard per role (menu grid sesuai hak akses, kartu semester aktif)
3. Daftar + detail + form (create/edit) untuk: Mapel, Kelas, Guru (dengan
   image picker + crop 3:4), Siswa (dengan image picker + crop 3:4),
   **Karyawan** (dengan image picker + crop 3:4)
4. Akademik: pembagian kelas (assign/pindah siswa), pengampu mapel,
   **wali kelas** (tetapkan/ganti wali per kelas — Admin),
   **periode khusus** (Ramadan/pekan ujian/libur — Admin: kalender rentang
   tanggal + set jam periode + ambang absensi periode),
   kelola jam pelajaran (set normal + per hari + per periode),
   kelola jadwal (tampilan jadwal mingguan per kelas/guru — jam mengikuti
   tanggal yang dipilih)
5. Nilai: input nilai per kelas+mapel (khusus Guru/Admin, tabel siswa dengan
   kolom harian/UTS/UAS), pengaturan bobot nilai (Admin)
6. Raport & ranking (tabel per kelas; untuk Siswa tampilkan raport & ranking diri sendiri)
7. **Absensi:**
   - **Kelola kartu** (Admin): terbitkan/blokir/terbit-ulang kartu siswa/guru/
     karyawan, tampilkan & cetak/bagikan QR (render SVG dari `/kartu/qr`)
   - **Mode Terminal / Kiosk** (perangkat sekolah): layar scan QR kamera →
     `POST /absensi/scan`, umpan balik besar & jelas (hijau hadir / kuning
     terlambat / merah ditolak); tombol "Lupa kartu" → input NIP+PIN
     (`/absensi/pin/absen`); provisioning: input Terminal Id + Token sekali;
     mode demo mengirim lat/lng (izin lokasi)
   - **Absensi pelajaran** (Guru): layar "Jam sekarang" — auto-load daftar siswa
     kelas yang sedang diajar, ceklis status (hadir/izin/sakit/alpa) lalu submit
     (`/pelajaran/tandai`); bisa pilih jadwal lain
   - **Jendela PIN** (Admin): pilih pegawai → buka jendela; **Atur PIN** (Guru/
     Karyawan di menu profil)
   - **Izin keluar / pulang awal** (Guru wali / Admin): pilih siswa + jenis +
     keterangan → simpan; daftar izin keluar hari ini
   - **Rekap absensi**: harian per kelas & per siswa, pelajaran, pegawai (Admin/
     Guru/Karyawan); Siswa & pegawai melihat **"Absensi Saya"** (`/rekap/*/saya`)
8. Profil & Pengaturan: data diri (+ nomor kartu & tombol Atur PIN untuk pegawai),
   ganti password, pilihan tema (Terang / Gelap / Ikuti Sistem), logout, dan
   "Keluar dari semua perangkat" (`POST /logout-all`, dengan dialog konfirmasi)
9. Manajemen user (Admin/SuperAdmin): list, register akun, reset password, hapus akun

## Desain UI/UX

Gaya visual: modern-edukatif, bersih, dan ramah — mengacu pada kualitas desain
Google "Now in Android". Ikuti panduan Material 3 (m3.material.io).

- **Warna**: seed color biru kepercayaan (contoh `#1565C0`) atau teal edukasi
  (contoh `#00695C`) — turunkan seluruh skema warna Material 3 dari seed ini
  (light & dark). Gunakan warna semantik: hijau = nilai tuntas/aktif,
  kuning = perlu perhatian, merah = di bawah KKM/error.
- **Bentuk & elevasi**: kartu rounded 12–16dp, elevasi rendah (tonal, bukan
  bayangan tebal), spacing konsisten grid 8dp.
- **Tipografi**: hierarki jelas (headlineSmall untuk judul layar, titleMedium
  untuk kartu, bodyMedium untuk isi); angka nilai/ranking ditonjolkan dengan
  displaySmall.
- **Dashboard**: sapaan + nama & role user, kartu semester aktif di atas,
  grid menu 2 kolom dengan ikon berwarna lembut (tonal container), ringkasan
  cepat sesuai role (Guru: jadwal mengajar hari ini; Siswa: jadwal hari ini +
  rata-rata nilai terakhir).
  - **Info hari ini (semua role):** panggil `GET /akademik/periode/aktif` dan
    `GET /akademik/pengaturan-absensi/efektif` saat start. Bila ada periode aktif,
    tampilkan **badge** (mis. "Ramadan — jam disesuaikan", "Pekan Ujian — KBM
    normal libur", "Libur"); tampilkan jam masuk & ambang terlambat efektif hari
    ini dari `/efektif`. Jadwal "hari ini" di dashboard harus memakai jam efektif
    (kirim `tanggal` hari ini) agar ikut Ramadan/hari Jumat.
- **Navigasi**: bottom navigation maksimal 4–5 item sesuai role
  (mis. Beranda, Akademik, Nilai, Profil); di layar lebar berubah jadi
  navigation rail (sudah diatur di bagian responsif).
- **List** (siswa/guru/mapel/kelas): item dengan avatar/inisial, judul +
  teks pendukung, search bar menempel di atas + filter chips (mis. status,
  jenis kelamin); FAB untuk tambah data (hanya role yang berhak).
- **Jadwal pelajaran**: tab per hari (Senin–Jumat) dengan tampilan timeline
  vertikal per jam; setiap sesi berupa kartu berwarna berisi mapel, guru,
  ruangan, dan jam; hari ini ter-highlight.
- **Input nilai**: tabel siswa dengan kolom Harian/UTS/UAS yang bisa diedit
  inline per baris, indikator tersimpan, dan validasi 0–100 langsung di field.
- **Raport & ranking**: kartu ringkasan (rata-rata, peringkat, total siswa)
  di atas, diikuti tabel nilai per mapel; ranking memakai list bernomor dengan
  highlight 3 besar.
- **Foto profil**: avatar bulat di list, foto 3:4 rounded di halaman detail
  dengan placeholder inisial saat kosong/loading.
- **State**: skeleton/shimmer saat loading, empty state dengan ilustrasi
  sederhana + call-to-action, error state dengan tombol coba lagi; konfirmasi
  destructive (hapus) selalu pakai dialog; snackbar untuk feedback sukses.

## Keamanan

- Simpan access token secara terenkripsi: EncryptedSharedPreferences atau
  DataStore yang dienkripsi dengan kunci dari Android Keystore — JANGAN
  simpan token dalam plain text.
- Semua komunikasi wajib HTTPS. Trust-all/bypass sertifikat hanya boleh ada
  di build debug (untuk sertifikat mkcert lokal) — build release wajib
  validasi sertifikat normal, siapkan juga opsi certificate pinning via
  Network Security Config.
- `android:allowBackup="false"` dan `android:usesCleartextTraffic="false"`
  di manifest.
- Jangan tulis token, password, atau data siswa ke Log/logcat.
- Aktifkan R8/ProGuard (minify + obfuscation) di build release.
- Tidak ada secret apapun yang ditanam di APK (HMAC antar-service murni
  urusan backend, bukan klien).
- Ingat: menyembunyikan menu berdasarkan role hanyalah UX — otorisasi
  sesungguhnya tetap divalidasi server. App harus menangani 403 dengan
  pesan "tidak berhak" secara anggun, bukan crash.
- Logout harus memanggil `POST /logout` (mencabut token di server), bukan
  sekadar menghapus token lokal. Saat token kedaluwarsa/401, bersihkan sesi
  lokal dan kembali ke Login.
- Layar sensitif (nilai, raport, data pribadi siswa) opsional dilindungi
  `FLAG_SECURE` agar tidak bisa di-screenshot (jadikan setting yang bisa
  dimatikan).

## Kualitas

- Loading/error/empty state konsisten di semua layar, pull-to-refresh pada list
- Ktor plugin/interceptor: sisipkan header Bearer token di semua request;
  pada respons 401 hapus sesi dan navigasi ke Login
- **Tema**: Material 3 dengan tiga mode — Terang, Gelap, dan Ikuti Sistem
  (default: Ikuti Sistem). Pilihan user disimpan di settings/DataStore dan
  langsung diterapkan tanpa restart. Dukung dynamic color (Material You)
  di Android 12+ dengan fallback palet statis di versi lama.
- **Orientasi & ukuran layar (wajib responsif)**:
  - Dukung portrait DAN landscape — jangan kunci `screenOrientation` di manifest
  - State tidak boleh hilang saat rotasi (state di ViewModel + `rememberSaveable`
    untuk state UI lokal seperti input form dan posisi scroll)
  - Gunakan `WindowSizeClass` (material3-adaptive): compact = single pane
    (bottom navigation), medium/expanded = navigation rail + layout dua pane
    (list-detail) untuk tablet/foldable/landscape
  - Semua ukuran pakai `dp`/`sp`, tidak ada nilai pixel hardcode; layout memakai
    weight/fill/scroll sehingga aman di berbagai resolusi dan kepadatan layar
    (ldpi–xxxhdpi), termasuk layar kecil ≤5" dan tablet
  - Teks mengikuti font scale sistem (aksesibilitas) tanpa terpotong;
    edge-to-edge dengan insets yang benar (status/navigation bar)
