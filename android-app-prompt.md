# Prompt: Aplikasi Android "SIM Sekolah" (Kotlin + MVVM + Jetpack Compose Material 3)

Buatkan aplikasi Android native manajemen sekolah yang menjadi klien dari backend
microservice Laravel yang sudah ada. Aplikasi TIDAK membuat backend sendiri —
seluruh data diambil dari satu API Gateway.

## Tech Stack (wajib)

- Kotlin, struktur project KMP-ready: layer domain/data (model, repository,
  networking, session) dipisah dari layer UI agar kelak bisa di-share ke iOS/desktop
- Arsitektur MVVM + Repository pattern, unidirectional data flow (UI State via StateFlow)
- Jetpack Compose dengan Material 3, Navigation Compose
- Ktor Client + kotlinx.serialization untuk networking (multiplatform)
- Koin untuk dependency injection (multiplatform, jangan pakai Hilt/Dagger)
- Coroutines + Flow
- multiplatform-settings (atau DataStore di sisi Android) untuk menyimpan
  access token & data sesi
- Coil 3 untuk menampilkan gambar (foto dikirim sebagai data-URI base64 WebP)

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
│   ├── network/         # Ktor HttpClient, plugin auth Bearer, handler 401
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
│   ├── mapel/
│   ├── kelas/
│   ├── akademik/        # pembagian kelas, pengampu, jam, jadwal
│   ├── nilai/           # input nilai, pengaturan bobot
│   ├── raport/          # raport + ranking
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
  lengkap) + akademik/nilai/raport.

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
- **Jam pelajaran**: `GET|POST /akademik/jam`, `PATCH|DELETE /akademik/jam/{id}`
  (ke 1–10, jam_mulai/jam_selesai "HH:MM").
  Respons: `idJam`, `ke`, `jamMulai`, `jamSelesai`.
- **Jadwal**: `POST /akademik/jadwal` (pengampu_mapel_id, hari Senin–Jumat,
  jam_mulai_id, jam_selesai_id, ruangan?, catatan? — server menolak 409 jika
  bentrok kelas/guru), `PATCH|DELETE /akademik/jadwal/{id}`,
  `GET /akademik/jadwal/kelas/{id}`, `/jadwal/guru/{id}`, `/jadwal/siswa/{id}`,
  `/jadwal/pengampu/{id}`, plus varian `/riwayat` (SuperAdmin/Admin).
  Respons: `idJadwal`, `pengampuMapelId`, `guruId`, `mapelId`, `kelasId`,
  `tahunAjaran`, `semester`, `hari`, `jamMulaiId`, `jamSelesaiId`, `keMulai`,
  `keSelesai`, `pukul` (string siap tampil, contoh `"07:00:00 - 08:30:00"`),
  `ruangan`, `catatan`.
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
memanggil API berulang.

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
- Update guru/siswa/kelas/mapel memakai `POST .../update` (bukan PUT/PATCH),
  kirim hanya field yang berubah; update akademik memakai `PATCH`.
- Detail guru/siswa/kelas/mapel memakai query param (`?idGuru=`), bukan path param;
  DELETE memakai path param (`/guru/{id}`).
- Login gagal = HTTP 400 (bukan 401) — jangan menganggap 400 sebagai sesi
  kedaluwarsa; hanya 401 yang memicu auto-logout.
- Kode status lain yang harus ditangani: **403** = role tidak berhak (tampilkan
  pesan "tidak berhak", jangan logout — KECUALI jika `data.mustChangePassword ==
  true`: arahkan ke layar Ganti Password wajib), **409** = konflik bisnis (jadwal
  bentrok, kelas penuh, siswa sudah terdaftar — tampilkan `resMsg` apa adanya),
  **422** = error validasi, **429** = rate limit.
- `POST /login`, `POST /refresh`, dan `POST /password` dibatasi 5 percobaan/menit
  → tangani 429 dengan pesan yang ramah (`resMsg` berisi instruksi tunggu).

## Layar yang dibutuhkan

1. Splash (cek token tersimpan) → Login → (jika `mustChangePassword`)
   layar Ganti Password wajib → Dashboard
2. Home/Dashboard per role (menu grid sesuai hak akses, kartu semester aktif)
3. Daftar + detail + form (create/edit) untuk: Mapel, Kelas, Guru (dengan
   image picker + crop 3:4), Siswa (dengan image picker + crop 3:4)
4. Akademik: pembagian kelas (assign/pindah siswa), pengampu mapel,
   kelola jam pelajaran, kelola jadwal (tampilan jadwal mingguan per kelas/guru)
5. Nilai: input nilai per kelas+mapel (khusus Guru/Admin, tabel siswa dengan
   kolom harian/UTS/UAS), pengaturan bobot nilai (Admin)
6. Raport & ranking (tabel per kelas; untuk Siswa tampilkan raport & ranking diri sendiri)
7. Profil & Pengaturan: data diri, ganti password, pilihan tema
   (Terang / Gelap / Ikuti Sistem), logout, dan "Keluar dari semua perangkat"
   (`POST /logout-all`, dengan dialog konfirmasi)
8. Manajemen user (Admin/SuperAdmin): list, register akun, reset password, hapus akun

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
