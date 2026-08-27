# Tahapan Pembangunan App Android "SIM Sekolah"

Panduan eksekusi fase-per-fase untuk menghasilkan app dari `android-app-prompt.md`
menggunakan Claude Code (model: Claude Opus 4.8, reasoning effort `xhigh`).

> **Soal `effort`:** ini setelan *reasoning effort* Claude Code dengan tingkatan
> `low` → `medium` → `high` → `xhigh` → `max`. `xhigh` = satu tingkat di bawah
> maksimum: penalaran dalam tanpa selambat `max`. Cocok untuk fase yang banyak
> keputusan arsitektur (Fase 1, 2, 6D). Untuk fase ringan (6A) `high` sudah cukup.
> Ini BERBEDA dari "ultrathink" (kata kunci pemicu berpikir panjang) dan dari
> `/code-review ultra` (review multi-agent, fitur terpisah).

## Aturan main (berlaku di semua fase)

1. **Satu fase = satu sesi kerja = satu commit git.** Jangan gabungkan fase.
2. **Mulai tiap fase dengan Plan Mode** (Shift+Tab): AI menyusun rencana → kamu
   setujui → baru eksekusi.
3. **Gate wajib lulus sebelum lanjut fase berikutnya.** Kalau gagal, perbaiki di
   sesi itu juga — jangan menumpuk utang ke fase berikutnya.
4. **Verifikasi selalu terhadap backend sungguhan** (`https://192.168.12.181/api`
   — server sekolah di LAN, dari emulator maupun HP fisik) memakai akun test per
   role — bukan mock. Daftar akun: tabel di `docs/api-sample-responses.md`.
5. Setiap pesan fase diawali instruksi baca ulang dokumen — sesi baru tidak
   membawa ingatan sesi sebelumnya.
6. Kalau AI membuat asumsi bentuk request/response, suruh cek
   `docs/api-sample-responses.md` dulu — dokumen itu capture asli, bukan tebakan.
7. **Gating menu JANGAN pakai `role` saja.** Karyawan bertanda
   **Administrator Sekolah** (staf TU) berrole `Karyawan` tetapi berhak menulis
   data akademik + master data. Pakai helper tunggal di seluruh app:
   `boleh = role in ("SuperAdmin","Admin") || isAdminSekolah`.
   Flag `isAdminSekolah` (boolean) ada di respons **login** dan **`GET /user`**.
   Akun uji: `akuntest.adminsekolah@example.com` (boleh) vs
   `akuntest.karyawan@example.com` (tidak boleh) — lihat tabel akun di
   `docs/api-sample-responses.md`.
8. **Setel `effort` sesuai fase** (tiap fase mencantumkan levelnya). Naikkan satu
   tingkat kalau fase terasa macet atau hasilnya dangkal; turunkan kalau tugasnya
   jelas-jelas mekanis. Level di dokumen ini titik awal, bukan harga mati.
9. **Semua DTO detail direktori WAJIB nullable** (`= null`, bukan `lateinit`).
   Server menyaring data pribadi per viewer: guru/karyawan/siswa yang dibuka
   non-pengelola datang **tanpa** `nik`/`alamat`/`telephone`/`tanggalLahir`/`nisn`.
   Field yang disaring tidak dikirim sama sekali, jadi DTO non-nullable melempar
   `MissingFieldException` → layar blank. Gejalanya hanya muncul di akun tertentu
   (karyawan biasa, siswa), sehingga mudah lolos kalau QA cuma pakai akun admin.
   Rincian field per role: `docs/android-todo-rbac-angkatan.md` Perubahan 8.
10. **Karyawan biasa ≠ Administrator Sekolah, juga untuk BACA.** Selain tidak boleh
   menulis, karyawan biasa mendapat **403** di seluruh nilai/raport/ranking, rekap
   absensi siswa, dan direktori siswa. Helper kedua di samping `boleh`:
   `bolehLihatNilai = boleh || role == "Guru"` dan
   `bolehLihatSiswa = boleh || role in ("Guru","Siswa")`.

---

## Fase 0 — Persiapan (manual, tanpa AI)

**Checklist:**
- [ ] **Backend hidup: Gateway + 6 service** (Class, Mapel, Guru, Siswa, Karyawan,
      Akademik) jalan, bisa di-curl dari mesin dev. Install ikuti **README.md root**
      (Prasyarat + langkah 1–10). PASTIKAN KaryawanService ikut ter-setup.
- [ ] `seed-test-accounts.ps1` sudah dijalankan (akun 4 role tersedia)
- [ ] `api-sample-responses.md` sudah di-generate di sisi backend:
      `$env:TEST_ADMIN_PASSWORD="..."; .\capture-api-samples.ps1`
- [ ] **Rakit bundle otomatis** dari repo backend (buat folder + CLAUDE.md +
      .gitignore + salin dokumen):
      `powershell -ExecutionPolicy Bypass -File setup-android-project.ps1`
      → menghasilkan `sim-sekolah-android/` berisi `CLAUDE.md` + `docs/`
      (android-app-prompt, android-build-phases, api-sample-responses, Postman, ui-ux/).
      (Alternatif manual: buat struktur di bawah sendiri.)
  ```
  sim-sekolah-android/
  ├── CLAUDE.md          (pointer dokumen + info jaringan + perintah build)
  └── docs/
      ├── android-app-prompt.md
      ├── android-build-phases.md
      ├── api-sample-responses.md        <- dikirim jalur non-git (akun test)
      ├── Microservice Laravel.postman_collection.json
      └── ui-ux/                         <- screenshot referensi desain (isi manual)
  ```
- [ ] `docs/ui-ux/` terisi: `design-notes.md` (acuan desain), `material-theme/`
      (tema final), `font/` (Plus Jakarta Sans), `SMAN1Tanjung_AndroidIcons/`
      (app icon drop-in); sesuaikan IP backend LAN di `CLAUDE.md`
- [ ] `.gitignore` project Android memuat `docs/api-sample-responses.md` (sudah
      dibuat otomatis oleh setup-android-project.ps1)
- [ ] Android Studio / SDK / JDK terpasang; `adb devices` mendeteksi emulator
- [ ] **Setelan dua editor** (folder project yang sama dibuka di keduanya):
      - **VS Code** = tempat Claude Code bekerja (edit kode, git, terminal)
      - **Android Studio** = Compose Preview, emulator, Logcat, debug, Profiler
      - Sesudah `build.gradle.kts` / `libs.versions.toml` berubah -> klik
        **Gradle Sync** di Android Studio (tidak selalu otomatis)
      - Jangan mengedit file yang sama di dua editor bersamaan; biarkan Claude
        Code yang menulis, Android Studio untuk melihat/preview/menjalankan
- [ ] Emulator/HP bisa mengakses backend: buka `https://192.168.12.181/api` dari
      browser (respons JSON 404 "route api could not be found" = BERHASIL tembus;
      abaikan warning sertifikat — cert untuk gateway.test diakses lewat IP)

**Gate:** semua checklist tercentang; `docs/` lengkap & CLAUDE.md berisi info jaringan benar.

---

## Fase 1 — Scaffold Project

**Langkah 0 (MANUAL, sebelum Claude Code): buat kerangka lewat Android Studio.**
Jangan minta Claude Code membuat project Gradle dari nol — `gradle-wrapper.jar`
itu file BINER yang tidak bisa ditulis AI, dan menebak kombinasi versi
AGP/Kotlin/Compose BOM yang kompatibel rawan gagal. Biarkan wizard menangani itu:

1. Pindahkan sementara `CLAUDE.md` dan `docs/` ke luar folder (wizard butuh
   folder kosong).
2. Android Studio → **New Project** → **Empty Activity** (Compose):
   - Name: `SMANSA Tanjung`
   - Package name: `id.sch.sman1tanjung.sim`
   - Save location: folder `sim-sekolah-android`
   - Language: Kotlin · Minimum SDK: **API 24** · Build config: **Kotlin DSL**
3. Tunggu Gradle Sync selesai, tekan **Run ▶** — pastikan app kosong tampil di
   emulator/HP. Ini membuktikan toolchain sehat SEBELUM kode ditambahkan.
4. Kembalikan `CLAUDE.md` dan `docs/` ke folder project.
5. Buka folder itu di VS Code → jalankan Claude Code → prompt di bawah.

(Ini satu-satunya kali wizard dipakai. Sesudah ini Android Studio hanya untuk
Preview/emulator/Logcat; penulisan kode lewat Claude Code di VS Code.)

**Effort:** `xhigh` - kombinasi versi AGP/Kotlin/Compose + rombak struktur; salah di sini menular ke semua fase

**Prompt:**
> Baca docs/android-app-prompt.md dan docs/ui-ux/design-notes.md secara LENGKAP.
> Project Android kosong hasil wizard sudah ada dan `assembleDebug` sudah hijau —
> JANGAN buat ulang project atau mengganti Gradle wrapper. Tugasmu merapikan dan
> melengkapi: rombak jadi struktur folder persis bagian "Struktur Folder",
> pastikan applicationId/namespace `id.sch.sman1tanjung.sim` dan
> `android:label` "SMANSA Tanjung", tambahkan version catalog + dependensi
> (Compose M3, Navigation, Ktor + kotlinx.serialization, Koin, Coil 3,
> multiplatform-settings), NavHost kosong dengan route sealed class, dan satu
> layar placeholder. Base URL via BuildConfig (debug:
> https://192.168.12.181/api). JANGAN implement fitur apa pun dulu.
> Naikkan versi dependensi hanya ke kombinasi stabil yang kompatibel — verifikasi
> lewat build, bukan asumsi. Kalau build pecah setelah menaikkan versi, kembalikan.
>
> ASET yang sudah tersedia — PAKAI, jangan bikin sendiri:
> - Tema: salin `docs/ui-ux/material-theme/ui/theme/*.kt` (Color, Theme, Type,
>   StatusColor) ke `app/src/main/java/id/sch/sman1tanjung/sim/ui/theme/`.
>   Warna FINAL — jangan re-seed, jangan ubah, dynamicColor tetap false.
>   Pasang `LocalStatusColors` (light/dark) via CompositionLocalProvider di AppTheme.
> - Font: salin `docs/ui-ux/font/*.ttf` ke `app/src/main/res/font/` (nama sudah benar).
> - App icon: salin isi `docs/ui-ux/SMAN1Tanjung_AndroidIcons/res/` ke
>   `app/src/main/res/` (gabungkan folder), lalu set di AndroidManifest:
>   `android:icon="@mipmap/ic_launcher"` dan `android:roundIcon="@mipmap/ic_launcher_round"`.
>   Sudah drop-in — tidak perlu rename atau Image Asset wizard.
>
> Selesai = ./gradlew assembleDebug hijau dan app terpasang di emulator
> menampilkan layar placeholder dengan icon sekolah.

**Gate:**
- `./gradlew assembleDebug` hijau
- App terbuka di emulator tanpa crash
- Struktur folder sesuai prompt (core/domain/data/feature/navigation)
- Icon launcher = logo sekolah (bukan icon Android hijau bawaan)
- Font Plus Jakarta Sans terpakai (judul terlihat beda dari Roboto default)
- **Batas KMP terjaga** — tidak ada import `android.*`/`androidx.*` di `domain/`
  dan `data/`. Cek cepat (harus tidak menghasilkan apa pun):
  ```
  grep -rE "^import (android|androidx)\." app/src/main/java/id/sch/sman1tanjung/sim/domain app/src/main/java/id/sch/sman1tanjung/sim/data
  ```
  Jalankan ulang cek ini di akhir SETIAP fase berikutnya — pelanggaran biasanya
  masuk diam-diam lewat `Context`, `Uri`, `Bitmap`, atau `android.util.Log`.

**Jebakan umum:** versi dependensi tidak kompatibel (Compose BOM vs Kotlin) —
minta AI pakai versi stabil terbaru dan verifikasi lewat build, bukan asumsi.
Jangan pakai Hilt/Dagger (bukan multiplatform) — sudah ditetapkan Koin.

---

## Fase 2 — Core Network + Auth

**Effort:** `xhigh` - siklus token, 401, wajib-ganti-password - banyak jalur tepi

**Prompt:**
> Baca ulang docs/android-app-prompt.md (bagian Backend/API, Konvensi Data,
> Keamanan) dan docs/api-sample-responses.md (bagian Auth dan Contoh Error).
> Implement: (1) Ktor HttpClient + envelope generic ApiResponse<T> + penanganan
> error terpusat (400/401/403/409/422/429, data error 422 bertipe dinamis);
> (2) SessionManager terenkripsi (token, role, waktu login); (3) LoginScreen +
> ViewModel — kirim device_name "android"; (4) auto-refresh token saat umur > 6 jam;
> (5) interceptor Bearer + 401 -> hapus sesi -> ke Login; SessionManager WAJIB
> menyimpan `isAdminSekolah` (boolean dari respons login) bersama role, karena
> seluruh gating menu bergantung padanya; (6) alur WAJIB GANTI
> PASSWORD: jika mustChangePassword true di login ATAU must_change_password true
> di GET /user ATAU 403 dengan data.mustChangePassword -> layar Ganti Password
> wajib (back ditahan, hanya logout sebagai jalan keluar); (7) Splash: validasi
> token tersimpan via GET /user; (8) trust-all sertifikat HANYA build debug.
> Verifikasi dengan login sungguhan memakai akun test di docs/api-sample-responses.md.

**Gate (uji manual di emulator):**
- Login Admin sukses → dashboard placeholder; login password salah → pesan error
  (bukan logout/crash)
- Kill app → buka lagi → langsung masuk (token tersimpan)
- Login akun ter-flag (buat guru baru via Postman, login dengan password=email)
  → dipaksa ke layar ganti password → setelah ganti → masuk normal
- Login 6x cepat → pesan 429 yang ramah

**Jebakan:** login gagal = HTTP **400**, bukan 401 — jangan sampai memicu
auto-logout; `mustChangePassword` camelCase di login tapi `must_change_password`
snake_case di GET /user.

---

## Fase 3 — Master Data (Mapel, Kelas, Guru, Siswa)

**Effort:** `high` - pola CRUD berulang, sudah jelas polanya

**Prompt:**
> Baca ulang docs/android-app-prompt.md (modul 1-4, Konvensi Data) dan
> docs/api-sample-responses.md (bagian Mapel, Kelas, Guru, Siswa). Implement list +
> detail + form create/edit + hapus untuk Mapel, Kelas, Guru, Siswa: pagination
> infinite scroll (data.data, current_page < last_page), search bar debounce 400ms
> yang reset ke halaman 1, pull-to-refresh, empty/error/loading state. Guru & Siswa:
> form multipart dengan image picker + crop 3:4 LALU **perkecil sisi terpanjang
> ke ~1600 px** (batas server 6000 px; foto HP kelas atas melebihinya walau sudah
> di-crop) (foto wajib saat create, opsional
> saat edit); render foto detail (data-URI base64) dengan Coil. DTO detail guru
> DAN siswa: SEMUA field nullable — server menyaring field pribadi untuk setiap
> viewer non-pengelola (Guru, Siswa, karyawan biasa); lihat Aturan main #9 dan
> docs/android-todo-rbac-angkatan.md Perubahan 8. Baris detail yang null
> DISEMBUNYIKAN, jangan tampilkan "-".
> Update memakai POST .../update dengan hanya field berubah. Tombol tambah/edit/
> hapus hanya muncul bila `boleh` (Aturan main #7). Konfirmasi dialog sebelum hapus.
> Modul Siswa disembunyikan untuk karyawan biasa (`bolehLihatSiswa`, Aturan main #10).
> Verifikasi CRUD lengkap terhadap backend sungguhan sebagai Admin, lalu buka
> layar yang sama sebagai **Siswa** dan sebagai **karyawan biasa**
> (`akuntest.karyawan@example.com`) dan pastikan: tombol aksi hilang; detail guru
> tampil tanpa field pribadi di KEDUA akun itu; sebagai Siswa daftar & detail siswa
> lain TAMPIL tapi tanpa NISN/tanggal lahir/alamat/data orang tua; sebagai karyawan
> biasa modul Siswa tidak ada dan panggilan langsung membalas 403 (ditangani ramah).

**Gate:**
- CRUD keempat entitas sukses end-to-end (data benar-benar berubah di backend)
- Upload foto guru/siswa dari galeri emulator berhasil (< 2MB, 360x480 s/d 6000x6000)
- **Uji dengan foto kamera beresolusi penuh**, bukan hanya gambar contoh kecil:
  tanpa langkah perkecil, foto HP kelas atas ditolak 422 "invalid image dimensions"
- Search + infinite scroll bekerja; role Siswa tidak melihat tombol aksi
- **Tidak ada crash parsing** saat membuka detail guru/karyawan/siswa memakai akun
  Siswa dan akun karyawan biasa (bukti DTO sudah nullable — lihat Aturan main #9)

**Jebakan:** request master data camelCase (`namaLengkap`, `limitSiswa`) —
berbeda dari modul akademik yang snake_case; detail via query param
(`?idGuru=`), delete via path (`/guru/{id}`).

---

## Fase 4 — Akademik Dasar (Semester, Pembagian Kelas, Pengampu, Jam, Jadwal)

**Effort:** `high` - banyak entitas tapi pola serupa Fase 3

**Prompt:**
> Baca ulang docs/android-app-prompt.md (modul 5 + catatan PENTING soal ID relasi)
> dan docs/api-sample-responses.md (bagian Akademik). Implement: (1) repository
> master-data cache in-memory per sesi (map id->nama untuk guru/mapel/kelas/siswa)
> — ikuti bagian "Strategi Cache & Data Lokal" di docs/android-app-prompt.md:
> cache HANYA id->nama (JANGAN simpan alamat/no telp/data wali siswa di lokal),
> dan cache yang bergantung tanggal wajib di-key oleh tanggal.
> SEMUA layar akademik me-resolve nama lewat cache ini, dilarang N+1 call;
> (2) kartu semester aktif di dashboard, ambil sekali saat start, jadi default
> tahun_ajaran/semester semua layar; (3) pembagian kelas: daftar siswa per kelas,
> assign dari daftar belum-terdaftar (INGAT: response-nya snake_case), pindah
> kelas (PATCH), keluarkan siswa; (4) pengampu: assign guru+mapel+kelas, ganti
> guru, hapus; (5) kelola jam pelajaran (CRUD); (6) jadwal: form create dengan
> dropdown pengampu/hari/jam, tangani 409 bentrok dengan menampilkan resMsg apa
> adanya, tampilan jadwal mingguan tab per hari (timeline kartu berwarna, hari ini
> ter-highlight) untuk kelas dan guru; (7) panel **Riwayat** di layar detail
> (guru→mapel, siswa→kelas, kelas→siswa, mapel→pengampu) memakai **paginasi
> server**: kirim `per_page=25&page=n`, hentikan saat `current_page == last_page`.
> Tanpa param respons berupa array datar (perilaku lama) — DENGAN param `data`
> menjadi envelope seperti `/guru/all`; siapkan parser untuk bentuk berparam saja
> kalau memang selalu mengirim param. `per_page` maksimum 200 (di luar itu 422) — batas yang sama berlaku di semua endpoint berpaginasi.
> Panel riwayat hanya untuk SuperAdmin/Admin/Administrator Sekolah.
> Request akademik snake_case, response camelCase. Verifikasi terhadap backend
> sungguhan: assign siswa -> muncul di daftar kelas; buat jadwal bentrok -> pesan
> 409 tampil; riwayat halaman 2 memuat baris berbeda dari halaman 1.

**Gate:**
- Alur penuh: assign siswa → buat pengampu → buat jadwal → tampil di jadwal
  mingguan dengan NAMA guru/mapel (bukan ID)
- Jadwal bentrok menampilkan pesan konflik dari server
- Role Siswa/Karyawan: semua layar read-only
- Riwayat: "Muat lebih banyak" menarik halaman berikutnya dari server (cek di
  Logcat/proxy bahwa `page=2` benar-benar dikirim), berhenti di `last_page`

**Jebakan:** response akademik hanya berisi ID relasi — kalau layar menampilkan
angka ID mentah, cache resolver belum dipakai; `belum-terdaftar` snake_case.

---

## Fase 5 — Nilai + Pengaturan Bobot

**Effort:** `xhigh` - tabel input padat + simpan optimistic per-baris + bobot nilai

**Prompt:**
> Baca ulang docs/android-app-prompt.md (modul 5 bagian Nilai/Pengaturan) dan
> docs/api-sample-responses.md (bagian Nilai). Implement: (1) pengaturan bobot
> nilai (SuperAdmin/Admin saja, termasuk GET): form bobot harian/UTS/UAS dengan
> validasi total=100 di client sebelum kirim; (2) input nilai per kelas+mapel
> (Guru/Admin): pilih pengampu -> tabel siswa (nama dari cache) dengan kolom
> **Ulangan 1..5** (`nilai_harian_1`..`nilai_harian_5`), UTS, dan UAS editable
> inline, validasi 0-100 per field, indikator tersimpan per baris, nilaiAkhir
> tampil otomatis setelah rata-rata harian + UTS + UAS terisi (dari response
> server, jangan hitung sendiri);
> **ULANGAN HARIAN maksimal 5 per semester.** Kotak yang belum ada ulangannya
> dibiarkan KOSONG dan dikirim sebagai `null` — **jangan kirim `0`**, itu
> dihitung sebagai nilai nol dan menurunkan rata-rata siswa secara keliru.
> Slot kosong tidak ikut dihitung: 3 terisi berarti dibagi 3. Respons memuat
> `nilaiHarian` (rata-rata, READ-ONLY), `ulanganHarian` (array 5, null = belum
> ada), dan `jumlahUlangan`; tampilkan ringkasan "Rata-rata harian 80 (3 ulangan)".
> Untuk MENGHAPUS satu ulangan, PATCH dengan `"nilai_harian_2": null` secara
> eksplisit — menghilangkan field berarti tidak berubah; (3) daftar nilai per kelas/siswa
> (read-only). **Seluruh modul Nilai disembunyikan dari karyawan biasa**
> (`bolehLihatNilai`, Aturan main #10) — server membalas 403 di semua endpoint
> nilai/raport/ranking untuk akun itu. Administrator Sekolah TETAP melihatnya.
> Login sebagai akun Guru test dan pastikan guru
> hanya bisa memilih pengampu miliknya (403 dari server jika bukan — tangani
> ramah). Verifikasi: input nilai sebagai Guru -> cek nilai muncul saat dibaca
> sebagai Admin.

**Gate:**
- Guru test bisa input/update nilai untuk mapelnya; nilaiAkhir muncul setelah
  UTS+UAS diisi (nilai dari server)
- Bobot total ≠ 100 tertolak di client; duplikat semester → pesan 409
- Role Siswa tidak melihat menu input nilai
- Login `akuntest.karyawan@example.com` (karyawan biasa) → **modul Nilai tidak ada**;
  login `akuntest.adminsekolah@example.com` → modul Nilai **ada**. Dua akun ini
  sama-sama berrole `Karyawan`, jadi perbedaannya membuktikan gating memakai
  `isAdminSekolah`, bukan `role`.

---

## Fase 6 — Raport & Ranking (termasuk mode Siswa)

**Effort:** `high` - agregasi/tampilan, logika sudah di backend

**Prompt:**
> Baca ulang docs/android-app-prompt.md (modul 5 Raport & ranking) dan
> docs/api-sample-responses.md (bagian Raport dan bagian Perilaku per Role).
> Implement: (1) raport per siswa & per kelas: kartu ringkasan (rata-rata,
> bobot) + tabel nilai per mapel (nama mapel dari cache);
> **PENTING — data SE-KELAS kini hak WALI saja.** Untuk role Guru, dropdown kelas
> pada Raport/Ranking/Rekap diisi HANYA dari `GET akademik/guru/{id}/wali`
> (bukan pengampu). Guru yang cuma mengampu satu mapel di kelas itu mendapat
> **403**. Admin/Karyawan tetap seluruh kelas (`GET /class/all`). Bila guru bukan
> wali kelas manapun, tampilkan empty state yang menjelaskan;
> (2) ranking kelas: list bernomor, highlight 3 besar; (3) mode Siswa: layar
> Nilai Saya / Raport Saya / Ranking Saya memakai endpoint /saya (TANPA memilih
> siswa) — ranking saya hanya menampilkan posisi sendiri + total siswa;
> (4) tahun_ajaran & semester WAJIB terkirim di semua request raport/ranking
> (default semester aktif, bisa diganti lewat filter); (5) tangani data kosong:
> nilai [] dan rataRata null tampil sebagai empty state, peringkat null =
> "belum ada nilai". Verifikasi sebagai Admin (raport kelas berisi data) dan
> sebagai akun Siswa test (ketiga layar /saya).

**Gate:**
- Raport siswa/kelas tampil dengan nama mapel + rata-rata sesuai backend
- Login Siswa test: ketiga layar /saya jalan; tidak ada akses ke raport siswa lain
- Filter ganti semester memuat ulang data

---

## Fase 6A — Karyawan (master data)

**Effort:** `medium` - persis pola Fase 3 yang sudah jadi - tinggal ulang

**Prompt:**
> Baca ulang docs/android-app-prompt.md (modul 4b Karyawan, Konvensi Data) dan
> docs/api-sample-responses.md. Implement list + detail + form create/edit + hapus
> Karyawan mengikuti pola Guru/Siswa: multipart + image picker crop 3:4 (foto
> opsional), pagination + search debounce, DTO detail SEMUA nullable — server
> menyaring field pribadi untuk SEMUA non-pengelola, termasuk **karyawan biasa**
> (Aturan main #9). Request camelCase (`namaLengkap`, `jenisKelamin`, `noTelp`),
> detail via `?idKaryawan=`, delete via path. Tombol aksi hanya bila `boleh`.
> Modul Karyawan tidak ditampilkan ke role Siswa (server membalas 403 di detail).
> Verifikasi CRUD sebagai Admin, lalu buka sebagai **karyawan biasa**: field
> pribadi (alamat, `noTelp`) TIDAK ada dan tombol aksi hilang, tanpa crash.
> **Tambahan: penanda Administrator Sekolah.** Form create/edit punya switch
> **"Administrator Sekolah"** (`isAdminSekolah`, boolean) — tampilkan switch itu
> HANYA bila pengguna SuperAdmin/Admin. Kalau Administrator Sekolah sendiri yang
> mengirimnya, server membuangnya diam-diam sehingga UI terlihat "berhasil"
> padahal tidak berubah; menyembunyikannya mencegah kebingungan. Di daftar
> karyawan, tampilkan badge "TU" bila `isAdminSekolah` true.

**Gate:** CRUD karyawan sukses end-to-end; membuat karyawan otomatis membuat akun
user Karyawan; **karyawan biasa** hanya melihat field publik (dan layarnya tidak
crash); switch "Administrator Sekolah" hanya muncul untuk SuperAdmin/Admin dan
badge "TU" tampil di daftar.

---


## Fase 6B — Absensi: Kartu, Wali Kelas, Rekap (Admin & read)

**Effort:** `high` - banyak layar baca + rekap

**Prompt:**
> Baca ulang docs/android-app-prompt.md (modul 6 bagian 6a/6f + Wali Kelas di
> Konvensi, plus catatan QR SVG & WIB) dan docs/api-sample-responses.md (bagian
> Absensi). Implement (Bearer, tanpa Mode Terminal): (1) **Kelola Kartu** (Admin):
> terbitkan/blokir/terbit-ulang kartu siswa/guru/karyawan, tampilkan QR dari
> `GET /kartu/qr?data=<uid>` — INGAT responsnya image/svg+xml, render via coil-svg
> (bukan ApiResponse), sediakan aksi bagikan/cetak; (2) **Wali Kelas** (Admin):
> tetapkan/ganti/hapus wali per kelas (tangani 409 sudah ada wali, 404 guru tak
> ada), tampilkan wali per kelas; (3) **Rekap** (Admin/Adm. Sekolah/Guru — **karyawan biasa hanya rekap
> dirinya sendiri lewat `rekap/pegawai/saya`**, sisanya 403): rekap harian
> per kelas (tabel per siswa: hadir/terlambat/izin/sakit/alpa) & per siswa
> (ringkasan + detail), rekap pelajaran siswa, rekap pegawai; filter rentang
> tanggal (default awal bulan s/d hari ini). Di detail: `metode` bisa `turunan`
> (auto-alpa) → tampilkan mis. "Alpa (otomatis)"; `jamMasuk` bisa null (izin/
> sakit/alpa) → tampilkan "-", jangan crash. Verifikasi terhadap backend sungguhan.

**Gate:** terbitkan kartu → kartuUid muncul & QR tampil; blokir lalu terbit ulang
→ UID berubah; assign wali → tampil; rekap kelas menampilkan hitungan per status;
detail rekap dengan record `alpa`/`turunan` & `jamMasuk` null tampil tanpa crash;
login **karyawan biasa** → menu rekap siswa tidak ada, "Absensi Saya" tetap jalan.

**Jebakan:** `/kartu/qr` BUKAN JSON (SVG); request kartu camelCase (`idSiswa`),
request wali/rekap snake_case (`tahun_ajaran`, `tanggal_dari`); `jamMasuk` nullable
di DTO detail; enum `metode` termasuk `turunan`, `status` termasuk `dinas_luar` (pegawai).

---

## Fase 6C — Absensi: Pelajaran (Guru), Izin Keluar, PIN

**Effort:** `xhigh` - aturan bisnis absensi + persetujuan wali + jendela PIN

**Prompt:**
> Baca ulang docs/android-app-prompt.md (modul 6 bagian 6c/6d/6e + enforcement
> wali) dan docs/api-sample-responses.md. Implement (Bearer): (1) **Absensi
> pelajaran** (Guru): layar "Jam sekarang" (`GET /akademik/absensi/pelajaran/
> sekarang`) — jika ada jam berlangsung tampilkan daftar siswa (namaLengkap sudah
> disertakan) dengan pilihan status hadir/izin/sakit/alpa + catatan, submit
> `POST .../pelajaran/tandai`; tangani `data: []` (tidak ada jam) sebagai empty
> state; opsi memuat jadwal lain via `.../{jadwal_id}/siswa`; (2) **Izin keluar**
> (Guru wali / Admin): form pilih siswa + jenis (pulang_awal/izin_kegiatan/lomba/
> pulang_sakit) + keterangan → `POST /akademik/absensi/keluar`, tangani **403
> enforcement wali** (guru bukan wali kelas siswa itu) dengan resMsg ramah; daftar
> izin keluar hari ini; (3) **Atur PIN** (Guru/Karyawan, di menu profil):
> `POST /absensi/pin/atur` (4-6 digit); (4) **Buka jendela PIN** (Admin): pilih
> pegawai → `POST /absensi/pin/buka`, tampilkan berlakuSampai. Verifikasi sebagai
> akun Guru test (wali & bukan wali) dan Admin.

**Gate:** guru menandai absensi pelajaran & tersimpan; guru wali menyetujui izin
keluar siswa kelasnya (201) tetapi 403 untuk siswa kelas lain; atur PIN & buka
jendela sukses.

**Jebakan:** endpoint pelajaran/keluar butuh identitas guru dari akun login
(server inject) — pastikan login sebagai Guru sungguhan (bukan akun register
biasa yang tak punya profil guru).

---

## Fase 6C-2 — Periode Khusus, Jam per Periode/Hari & Pengaturan Absensi

**Effort:** `xhigh` - resolusi rentang tanggal (rentang terpendek menang) + jam efektif

**Prompt:**
> Baca ulang docs/android-app-prompt.md (modul 5 bagian Periode khusus,
> Pengaturan absensi, Jam pelajaran) dan docs/api-sample-responses.md (bagian
> "Periode Khusus dan Pengaturan Absensi"). Implement (Bearer, Admin kecuali
> disebut lain): (1) **Kalender periode khusus**: list + form buat/ubah/hapus
> periode (`nama`, `jenis` ramadan|ujian|libur|khusus, rentang tanggal via date
> range picker, `kbm_normal`, `keterangan`); tampilkan badge jenis + rentang;
> libur 1 hari = dari==sampai. Tampilkan juga "periode berlaku hari ini" dari
> `GET /akademik/periode/aktif`; (2) **Set jam per periode/hari**: kelola
> `/akademik/jam` dengan `periode_id` (null = set normal) dan `hari` (null =
> semua hari) — UI harus jelas membedakan "set normal", "set periode", dan
> "khusus hari X"; duplikat slot -> **409** (tampilkan resMsg, JANGAN
> perlakukan sebagai 422); (3) **Pengaturan absensi**: form default semester +
> override per periode (ambang terlambat siswa/pegawai, jam masuk, durasi PIN);
> duplikat kombinasi -> 409; (4) **Jam efektif**: semua tampilan jadwal/jam
> harus memakai `GET /akademik/jam?tanggal=` — JANGAN cache jam lintas tanggal,
> karena jam berbeda saat Ramadan dan pada hari Jumat; (5) semua role bisa
> membaca `GET /akademik/pengaturan-absensi/efektif?tanggal=` — pakai untuk
> menampilkan "jam masuk & ambang terlambat hari ini" di dashboard.
> Verifikasi terhadap backend sungguhan: buat periode Ramadan + set jamnya,
> lalu cek `GET /akademik/jam?tanggal=` di dalam vs di luar rentang.

> (6) **Dashboard info hari ini** (semua role): saat start panggil
> `GET /akademik/periode/aktif` + `GET /akademik/pengaturan-absensi/efektif` →
> bila ada periode aktif tampilkan badge (Ramadan/Pekan Ujian/Libur) + jam masuk
> & ambang terlambat efektif hari ini; jadwal "hari ini" di dashboard memakai
> jam efektif (kirim `tanggal`).

**Gate:**
- Buat periode Ramadan + set jam periode → `GET /akademik/jam?tanggal=<dalam
  rentang>` menampilkan jam Ramadan; `<di luar rentang>` kembali ke jam normal
- Buat libur 1 hari di **dalam** rentang Ramadan → `periode/aktif` pada tanggal
  itu mengembalikan **libur** (rentang terpendek menang), bukan Ramadan
- Baris jam `hari=Jumat` menang atas baris semua-hari pada hari Jumat
- Override pengaturan periode tampil di `/efektif` dengan `sumber=periode`
- Dashboard menampilkan badge periode + jam/ambang hari ini dari `/efektif`
- Role Siswa: menu tulis periode/pengaturan tidak terlihat (tapi `/aktif` &
  `/efektif` boleh dibaca untuk dashboard), dan 403 dari server ditangani ramah

**Jebakan:** duplikat jam = **409** (bukan 422 — kontrak berubah sejak `ke`
boleh berulang antar periode/hari); `sumber=default_sistem` berarti belum ada
baris pengaturan sama sekali (fallback 07:20) — tampilkan apa adanya, bukan
error; periode `ujian`/`libur` punya `kbmNormal=false` → layar absensi pelajaran
harus jadi **empty state berpesan**, bukan error.

---

## Fase 6D — Mode Terminal (kiosk scan + PIN)

**Effort:** `max` - kamera/ML Kit/lokasi + auth terminal terpisah (X-Terminal-Id/Token, bukan Bearer) + mode kiosk; fase paling rawan

**Prompt:**
> Baca ulang docs/android-app-prompt.md (modul 6 bagian 6b, Tech Stack Mode
> Terminal, Konvensi autentikasi terminal) dan docs/api-sample-responses.md.
> Implement **Mode Terminal** terpisah dari sesi login user: (1) layar provisioning
> — input & simpan `X-Terminal-Id` + `X-Terminal-Token` terenkripsi (Keystore),
> pilih mode demo/produksi; (2) Ktor client TERPISAH yang menyisipkan header
> terminal (bukan Bearer); (3) layar scan kamera (CameraX + ML Kit Barcode) → baca
> QR → `POST /absensi/scan` dengan kartu_uid (mode demo lampirkan lat/lng via
> FusedLocation); umpan balik layar penuh: hijau=hadir, kuning=terlambat,
> merah=ditolak (tampilkan resMsg untuk 401/403/404); (4) tombol "Lupa kartu" →
> input NIP+PIN → `POST /absensi/pin/absen`; (5) kiosk: layar tetap menyala,
> kembali ke scan otomatis setelah tiap hasil. Verifikasi: daftarkan terminal via
> `php artisan terminal:register` (mode demo), scan kartu siswa asli → tercatat.

**Gate:** terminal ter-provisioning; scan QR kartu aktif → 201/200 dengan status
hadir/terlambat; kartu diblokir → ditolak; tanpa/ salah token terminal → 401
ditangani ramah; alur lupa-kartu (buka jendela via Admin lalu NIP+PIN) berhasil.

**Jebakan:** Mode Terminal TIDAK memakai Bearer user — jangan pakai client yang
sama; lat/lng hanya untuk mode demo; izin CAMERA/LOCATION diminta di alur ini saja.

---

## Fase 6E — Laporan Peringkat Se-Angkatan + Ekspor (Admin/TU)

**Effort:** `high` - satu layar laporan + unduh berkas; logika peringkat sudah di server

**Prompt:**
> Baca ulang docs/android-app-prompt.md (bagian Laporan peringkat SE-ANGKATAN) dan
> docs/api-sample-responses.md (bagian Endpoint /saya, Laporan Angkatan &
> Ulangan Harian). Implement layar **Laporan Peringkat Se-Angkatan**, tampil hanya
> untuk SuperAdmin/Admin/Administrator Sekolah (`boleh` helper dari Aturan main #7).
>
> (1) Filter: tingkat (WAJIB, 1=X/2=XI/3=XII), jurusan (opsional — kosong berarti
> gabungan se-tingkat; isi dropdown dari `GET /class/all?tingkat=N` lalu ambil
> jurusan uniknya), tahun_ajaran & semester (default semester aktif), dan toggle
> "tampilkan nilai per mapel" (`detail=1`).
>
> (2) Header ringkasan: `rataRataAngkatan` dan `totalSiswa`.
>
> (3) Tabel peringkat dari `GET akademik/nilai/ranking/angkatan`. **ATURAN
> TAMPILAN — jangan "diperbaiki" di klien:**
> - `peringkat` bisa KEMBAR dan MELOMPAT (mis. 1, 2, 2, 4, 5). Pakai field
>   `peringkat` apa adanya; **JANGAN memakai indeks baris**.
> - Server sudah mengurutkan (seri diurutkan alfabet nama) — **jangan di-sort
>   ulang** di klien, urutan seri akan jadi acak.
> - `belumDinilai > 0` berarti ada mapel yang dihitung 0 sehingga rata-rata belum
>   final: tampilkan penanda (mis. chip "2 dari 5 mapel belum dinilai"), JANGAN
>   sembunyikan barisnya.
> - Siswa non-aktif sudah dibuang server; klien tidak perlu menyaring.
> - `jurusan` bisa `null` -> DTO nullable. `nilai` (per mapel) hanya ada saat
>   `detail=1` -> opsional di DTO; `mapelId` di-resolve ke nama lewat cache mapel.
>
> (4) Tombol **Unduh**: `GET akademik/nilai/ranking/angkatan/export?...&format=csv|pdf`.
> Responsnya BERKAS, bukan JSON — jangan lewatkan ke parser JSON. Ambil nama
> berkas dari header `Content-Disposition` (sudah deskriptif), simpan lewat
> MediaStore/SAF, lalu tawarkan "Buka"/"Bagikan". Unduh di IO dispatcher dengan
> indikator progres (PDF bisa ratusan KB). Format selain csv/pdf -> 422.
>
> (5) Empty state: tingkat tanpa kelas -> 404; ada kelas tapi belum ada siswa
> aktif -> 200 dengan `totalSiswa: 0` dan `ranking: []`. Tangani ketiganya sebagai
> keterangan yang jelas, bukan error.
>
> Verifikasi: buka sebagai Admin DAN sebagai `akuntest.adminsekolah@example.com`
> (keduanya boleh), lalu sebagai `akuntest.karyawan@example.com` (menu tidak
> muncul; endpoint 403 bila dipaksa).

**Gate:**
- Peringkat kembar/melompat tampil persis seperti respons server
- Chip "belum dinilai" muncul saat `belumDinilai > 0`
- Unduh CSV dan PDF menghasilkan berkas yang bisa dibuka dari notifikasi/Files
- Administrator Sekolah bisa membuka layar; karyawan biasa tidak

**Jebakan umum:** memakai `index + 1` sebagai nomor peringkat (menghapus makna
seri), dan men-`sort` ulang daftar di klien.

---

## Fase 7 — Manajemen User + Profil & Pengaturan

**Effort:** `high` - CRUD user + preferensi tema

**Prompt:**
> Baca ulang docs/android-app-prompt.md (RBAC, layar 7-8) dan
> docs/api-sample-responses.md (bagian Manajemen User). Implement: (1) manajemen
> user (SuperAdmin/Admin): list paginated + search + filter role, badge untuk user
> dengan must_change_password=true ("belum ganti password") dan badge
> "Administrator Sekolah" bila `isAdminSekolah` true, register akun
> (dropdown role — Admin tidak melihat opsi Admin), reset password user lain,
> hapus akun dengan dialog konfirmasi (tangani 403 proteksi: hapus diri sendiri /
> Admin hapus Admin); (2) profil: data GET /user + foto placeholder inisial;
> (3) ganti password sendiri (validasi min 8 huruf+angka, confirm sama, tangani
> 422 current salah); (4) pengaturan tema Terang/Gelap/Sistem tersimpan di
> DataStore, langsung berlaku tanpa restart; (5) logout (panggil POST /logout)
> dan "Keluar dari semua perangkat" (POST /logout-all + konfirmasi).
> Verifikasi: register Karyawan baru sebagai Admin -> login akun itu; reset
> password user -> user itu ter-logout (401 di device-nya).

**Gate:**
- Register + reset + hapus bekerja dengan batasan role yang benar
- Ganti tema instan; ganti password sendiri sukses dan sesi tetap hidup
- Logout-all mencabut sesi di device lain (uji dengan 2 emulator/postman)

---

## Fase 8 — QA per Role, Responsif & Poles

**Effort:** `xhigh` - berburu bug lintas 5 role + responsif; butuh teliti

**Prompt:**
> Lakukan pass QA menyeluruh, JANGAN menambah fitur baru: (1) login bergantian
> **5 akun test** (Admin / Administrator Sekolah / **karyawan biasa** / Guru /
> Siswa) — telusuri semua menu, pastikan menu/tombol yang tidak berhak
> tersembunyi DAN 403 dari server tetap ditangani ramah (defense in depth).
> Dua akun karyawan itu **pasangan pembanding**: keduanya berrole `Karyawan`,
> jadi setiap perbedaan menu membuktikan gating memakai `isAdminSekolah`.
> Khusus karyawan biasa, pastikan modul **Nilai/Raport/Ranking** dan **Siswa**
> tidak ada, sedangkan direktori Guru/Karyawan/Kelas/Mapel dan "Absensi Saya"
> tetap jalan. Buka detail guru & karyawan dengan akun itu dan akun Siswa —
> **tidak boleh crash** dan tidak boleh menampilkan baris kosong "-" untuk field
> pribadi yang memang tidak dikirim; (2) rotasi layar di setiap layar utama — state tidak hilang
> (form, posisi scroll); (3) uji WindowSizeClass: compact = bottom nav, medium/
> expanded = navigation rail + list-detail dua pane; (4) font scale sistem
> maksimum — teks tidak terpotong; (5) dark mode di semua layar — tidak ada teks
> tak terbaca; (6) audit state: setiap layar punya loading (skeleton), empty
> (ilustrasi + CTA), error (tombol coba lagi); (7) matikan backend sebentar —
> semua layar menampilkan error ramah, tidak crash; (8) pastikan tidak ada token/
> password/data siswa di logcat; (9) perbaiki semua temuan.

**Gate:**
- Matriks role lulus (**lima** akun × semua menu)
- Detail guru/karyawan/siswa terbuka tanpa crash di akun karyawan biasa & Siswa
- Tidak ada crash pada rotasi/offline/font besar
- Logcat bersih dari data sensitif

---

## Fase 9 — Build Rilis (opsional, saat mau distribusi)

**Effort:** `high` - signing, ProGuard, verifikasi rilis

**Prompt:**
> Siapkan build release: (1) R8/ProGuard aktif + rules untuk Ktor/kotlinx-
> serialization/Koin (uji app release TIDAK crash karena obfuscation DTO);
> (2) hapus trust-all dari release — validasi sertifikat normal + siapkan
> Network Security Config untuk certificate pinning (placeholder pin);
> (3) allowBackup=false, usesCleartextTraffic=false; (4) base URL release via
> BuildConfig terpisah; (5) signing config dari keystore properties di luar git;
> (6) versioning (versionCode/Name). Hasil: ./gradlew assembleRelease sukses dan
> APK release berjalan di emulator terhadap backend dev (sementara pakai
> sertifikat valid/IP yang sesuai).

**Gate:**
- APK release terpasang & fungsional (login + satu alur CRUD)
- DTO tidak rusak oleh R8 (uji semua modul sekali jalan)

---

## Jika sesi kepanjangan / context habis

Claude Code otomatis merangkum konteks — tidak masalah. Tapi kalau hasil mulai
melenceng: hentikan, commit yang sudah benar, buka sesi baru, dan mulai dengan
"Baca CLAUDE.md, docs/android-app-prompt.md, lalu lanjutkan Fase N dari commit
terakhir; cek dulu apa yang sudah ada sebelum menulis kode baru."

---

## Catatan QA untuk modul Absensi (masukkan ke Fase 8)

Saat pass QA per role di Fase 8, tambahkan cakupan berikut:
- Matriks role kini termasuk menu **Karyawan & Absensi** — pastikan hanya role
  berhak yang melihat kelola kartu, wali kelas, buka jendela PIN (Admin), absensi
  pelajaran & izin keluar (Guru), dan "Absensi Saya" (Siswa/pegawai).
- **Mode Terminal** diuji terpisah (bukan bagian sesi login user): scan kartu
  valid/blokir, tanpa/token terminal salah (401), di luar geofence mode demo
  (403), serta alur lupa-kartu (Admin buka jendela → NIP+PIN).
- Waktu absensi ditampilkan apa adanya (WIB) — tidak ada pergeseran zona.
- QR `/kartu/qr` tampil sebagai gambar SVG, bukan teks/JSON mentah.
- **Periode khusus**: buat periode Ramadan/ujian/libur lalu telusuri ulang layar
  jadwal & absensi — jam ikut berubah pada tanggal dalam rentang, kembali normal
  di luar rentang, dan layar absensi pelajaran jadi empty-state berpesan saat
  `kbmNormal=false`. Pastikan tidak ada jam yang ter-cache lintas tanggal.
