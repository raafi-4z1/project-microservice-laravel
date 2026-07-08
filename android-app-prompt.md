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

## Backend / API

- Satu base URL Gateway, contoh dev: `https://gateway.test/api`
  (buat base URL configurable via BuildConfig/setting, karena saat development
  memakai sertifikat lokal mkcert — sediakan opsi trust-all HANYA untuk build debug)
- Autentikasi: OAuth2 Laravel Passport — `POST /login` (body: email, password)
  mengembalikan `access_token`. Semua request lain memakai header
  `Authorization: Bearer {token}`.
- Token berlaku 8 jam; login baru mencabut semua token lama (tidak ada sesi ganda)
  → jika respons 401, arahkan user kembali ke layar Login.
- `POST /logout` mencabut token. `POST /password` ganti password sendiri
  (current_password, new_password, confirm_password).
- `GET /user` mengembalikan profil user yang login (termasuk `role`).

## Role & RBAC (UI harus menyesuaikan role dari GET /user)

- **SuperAdmin / Admin**: akses penuh CRUD semua modul, register akun baru
  (`POST /register`: name, email, password, confirm_password, role=Admin|Guru|Siswa|Karyawan;
  Admin tidak bisa membuat Admin lain), manajemen user
  (`GET /users`, `GET /users/{id}`, `POST /users/{id}/password`, `DELETE /users/{id}`).
- **Guru**: read-only data master; boleh input/update/hapus nilai; lihat jadwal mengajar.
- **Siswa**: read-only; punya endpoint khusus `nilai/saya`, `raport/saya`, `ranking/saya`,
  dan jadwal kelasnya.
- **Karyawan**: read-only data akademik/nilai/raport.

Sembunyikan menu & tombol aksi yang tidak sesuai role.

## Modul & Endpoint (semua relatif ke base URL)

### 1. Mata Pelajaran

- `GET /mapel/all`, `GET /mapel?idPelajaran={id}`
- `POST /mapel` (tambah), `POST /mapel/update` (update, kirim id + field berubah),
  `DELETE /mapel/{id}`

### 2. Kelas (ruang kelas)

- `GET /class/all`, `GET /class?idKelas={id}`
- `POST /class`, `POST /class/update`, `DELETE /class/{id}`

### 3. Guru (dengan foto, multipart/form-data)

- `GET /guru/all` (list tanpa foto), `GET /guru?idGuru={id}` (detail + foto)
- `POST /guru` multipart (data + file foto), `POST /guru/update` (idGuru + field berubah,
  foto opsional), `DELETE /guru/{id}`
- Foto upload: JPEG/PNG/JPG maks 2 MB, minimal 360×480 px.
  Foto pada respons detail berupa string `data:image/webp;base64,...` — render dengan Coil.

### 4. Siswa (dengan foto, multipart/form-data)

- `GET /siswa/all`, `GET /siswa?idSiswa={id}`, `POST /siswa`, `POST /siswa/update`,
  `DELETE /siswa/{id}`
- Field tambah siswa (multipart): email, nisn, namaLengkap, telephone,
  jenisKelamin (Laki-Laki|Perempuan), tempatLahir, tanggalLahir (YYYY-MM-DD),
  tanggalMasuk, alamat, namaIbu, foto (wajib); opsional: agama, namaAyah,
  pekerjaanAyah, pekerjaanIbu, noTelpAyah, noTelpIbu, namaWali, hubunganWali, noTelpWali.

### 5. Akademik (prefix `/akademik`)

- **Semester**: `GET /akademik/semester/aktif`, `GET /akademik/semester/riwayat`,
  `POST /akademik/semester/aktif` (tahun_ajaran "YYYY/YYYY", semester 1|2, tanggal_mulai)
- **Pembagian kelas**: `POST /akademik/kelas/assign` (siswa_id, kelas_id, tahun_ajaran,
  semester), `PATCH /akademik/kelas/assign/{id}`, `DELETE /akademik/kelas/assign/{id}`,
  `GET /akademik/kelas/{id}/siswa`, `GET /akademik/siswa/{id}/kelas`,
  `GET /akademik/siswa/belum-terdaftar`, plus varian `/riwayat`
- **Pengampu mapel**: `POST /akademik/pengampu` (guru_id, mapel_id, kelas_id,
  tahun_ajaran, semester), `PATCH /akademik/pengampu/{id}`, `DELETE /akademik/pengampu/{id}`,
  `GET /akademik/kelas/{id}/pengampu`, `GET /akademik/guru/{id}/mapel`,
  `GET /akademik/mapel/{id}/guru`, plus varian `/riwayat`
- **Jam pelajaran**: `GET|POST /akademik/jam`, `PATCH|DELETE /akademik/jam/{id}`
  (ke 1–10, jam_mulai/jam_selesai "HH:MM")
- **Jadwal**: `POST /akademik/jadwal` (pengampu_mapel_id, hari Senin–Jumat,
  jam_mulai_id, jam_selesai_id, ruangan?, catatan?), `PATCH|DELETE /akademik/jadwal/{id}`,
  `GET /akademik/jadwal/kelas/{id}`, `/jadwal/guru/{id}`, `/jadwal/siswa/{id}`,
  `/jadwal/pengampu/{id}`, plus varian `/riwayat`
- **Pengaturan nilai**: `GET|POST /akademik/pengaturan-nilai`,
  `PATCH /akademik/pengaturan-nilai/{id}` (bobot_harian + bobot_uts + bobot_uas = 100)
- **Nilai**: `POST /akademik/nilai` (siswa_kelas_id, pengampu_mapel_id, nilai_harian?,
  nilai_uts?, nilai_uas? — nilai_akhir dihitung otomatis server),
  `PATCH|DELETE /akademik/nilai/{id}`, `GET /akademik/nilai/pengampu/{id}`,
  `/nilai/kelas/{id}`, `/nilai/siswa/{id}`, `/nilai/saya` (khusus Siswa)
- **Raport & ranking**: `GET /akademik/raport/siswa/{id}`, `/raport/kelas/{id}`,
  `/raport/saya`, `GET /akademik/nilai/ranking/kelas/{id}`, `/nilai/ranking/saya`
  (query opsional: tahun_ajaran, semester)

## Konvensi data (penting)

- Field REQUEST akademik memakai snake_case (siswa_id, tahun_ajaran);
  field RESPONSE memakai camelCase (idSiswa, namaLengkap, nilaiAkhir, tahunAjaran).
- Update guru/siswa/kelas/mapel memakai `POST .../update` (bukan PUT/PATCH),
  kirim hanya field yang berubah.
- Detail guru/siswa/kelas/mapel memakai query param, bukan path param.
- Error respons JSON standar Laravel (message + errors per-field 422) —
  tampilkan error validasi per field di form.
- `POST /login` dibatasi 5 percobaan/menit → tangani 429 dengan pesan yang ramah.

## Layar yang dibutuhkan

1. Splash (cek token tersimpan) → Login
2. Home/Dashboard per role (menu grid sesuai hak akses, kartu semester aktif)
3. Daftar + detail + form (create/edit) untuk: Mapel, Kelas, Guru (dengan
   image picker + crop 3:4), Siswa (dengan image picker + crop 3:4)
4. Akademik: pembagian kelas (assign/pindah siswa), pengampu mapel,
   kelola jam pelajaran, kelola jadwal (tampilan jadwal mingguan per kelas/guru)
5. Nilai: input nilai per kelas+mapel (khusus Guru/Admin, tabel siswa dengan
   kolom harian/UTS/UAS), pengaturan bobot nilai (Admin)
6. Raport & ranking (tabel per kelas; untuk Siswa tampilkan raport & ranking diri sendiri)
7. Profil & Pengaturan: data diri, ganti password, pilihan tema
   (Terang / Gelap / Ikuti Sistem), logout
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
