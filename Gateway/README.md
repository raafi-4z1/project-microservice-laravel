# Gateway

Service utama yang menjadi pintu masuk seluruh request. Menangani autentikasi OAuth2 (Laravel Passport), otorisasi berbasis role, routing ke service internal via HMAC, dan audit log.

**Domain lokal:** `https://gateway.test`  
**Database:** `gateway_db`

---

## Konfigurasi Environment

```env
APP_URL=https://gateway.test

SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gateway_db
DB_USERNAME=root
DB_PASSWORD=

# Secret HMAC — harus sama dengan ACCEPTED_SECRETS di tiap service
CLASS_SERVICE_BASE_URL=http://classmicroservices.test
CLASS_SERVICE_SECRET=base64:...

MAPEL_SERVICE_BASE_URL=http://mapelservice.test
MAPEL_SERVICE_SECRET=base64:...

GURU_SERVICE_BASE_URL=http://guruservice.test
GURU_SERVICE_SECRET=base64:...

SISWA_SERVICE_BASE_URL=http://siswaservice.test
SISWA_SERVICE_SECRET=base64:...

KARYAWAN_SERVICE_BASE_URL=http://karyawanservice.test
KARYAWAN_SERVICE_SECRET=base64:...

AKADEMIK_SERVICE_BASE_URL=http://akademikservice.test
AKADEMIK_SERVICE_SECRET=base64:...

# Akun SuperAdmin awal (dipakai seeder — wajib diganti)
SUPERADMIN_NAME="Nama Admin Kamu"
SUPERADMIN_EMAIL="email@domain.com"
SUPERADMIN_PASSWORD="MinimalDuabelasKarakter1"
```

---

## Endpoints

Base URL: `https://gateway.test/api`

> **Baca kolom Role begini:** di seluruh tabel di bawah, **"SuperAdmin, Admin"
> juga mencakup karyawan bertanda [Administrator Sekolah](#administrator-sekolah-staf-tata-usaha)**
> (staf TU) — mereka berrole `Karyawan` tapi lolos lewat pseudo-role
> `AdminSekolah`. Karyawan biasa (satpam, kebersihan) tetap **403**.
> Konvensi yang sama dipakai di [AkademikService/README.md](../AkademikService/README.md).
>
> Kolom Role hanya menyatakan siapa yang boleh **memanggil**. **Isi** respons bisa
> berbeda per role — data pribadi disaring untuk non-pengelola, lihat
> [Privasi BACA](#privasi-baca--direktori-publik-vs-data-pribadi).

### Auth

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| POST | `/login` | Publik | Login (per device via `device_name`), max 5x/menit |
| POST | `/refresh` | Semua | Tukar token yang masih valid dengan token baru (perpanjang sesi), max 5x/menit |
| POST | `/logout` | Semua | Cabut token aktif (device ini saja) |
| POST | `/logout-all` | Semua | Cabut semua sesi aktif di semua device |
| POST | `/register` | SuperAdmin, Admin | Daftar akun baru (wajib login) |
| GET | `/user` | Semua | Profil diri sendiri — hanya mengembalikan data milik user yang sedang login |
| POST | `/password` | Semua | Ganti password sendiri, max 5x/menit |

### Manajemen User

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| GET | `/users` | SuperAdmin, Admin | List semua akun user. Query: `page`, `per_page`, `role` (filter exact), `search` (cari di nama/email) |
| GET | `/users/{id}` | SuperAdmin, Admin | Detail akun user by ID |
| POST | `/users/{id}/password` | SuperAdmin, Admin | Reset password user lain (token target dicabut) |
| DELETE | `/users/{id}` | SuperAdmin, Admin | Hapus akun user (soft delete) |

### Kartu Absensi

| Method | Endpoint | Role | Keterangan |
|--------|----------|------|------------|
| POST | `/siswa/kartu/terbitkan` · `/guru/kartu/terbitkan` · `/karyawan/kartu/terbitkan` | SuperAdmin, Admin | Terbitkan/ganti kartu (UID opaque berprefix SIS-/GUR-/KAR-) |
| POST | `/siswa/kartu/blokir` · `/guru/kartu/blokir` · `/karyawan/kartu/blokir` | SuperAdmin, Admin | Blokir kartu (`status`: `hilang`/`blokir`) |
| GET | `/kartu/qr?data=<uid>` | SuperAdmin, Admin | Render QR kartu sebagai `image/svg+xml` |

### Absensi (Scan & PIN via Terminal)

Endpoint di bawah **tidak** memakai Bearer token — melainkan autentikasi **terminal** (header `X-Terminal-Id` + `X-Terminal-Token`). Lihat bagian [Terminal Absensi](#terminal-absensi).

| Method | Endpoint | Auth | Keterangan |
|--------|----------|------|------------|
| POST | `/absensi/scan` | Terminal | Scan kartu masuk (siswa gerbang / guru & karyawan). Resolve UID→subjek via prefix, catat absensi. Idempotent per hari |
| POST | `/absensi/pin/absen` | Terminal | Absen via NIP+PIN saat lupa kartu (butuh jendela PIN aktif) |
| POST | `/absensi/pin/atur` | Guru, Karyawan | Pegawai mengatur PIN sendiri (4-6 digit) |
| POST | `/absensi/pin/buka` | SuperAdmin, Admin | Buka jendela PIN untuk seorang pegawai (time-boxed, sekali pakai) |

> Absensi per pelajaran, izin keluar, dan rekap berada di prefix `/akademik/absensi/*` — lihat [AkademikService/README.md](../AkademikService/README.md).

---

## Request Fields

### POST /login

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `email` | ✅ | Email akun |
| `password` | ✅ | Password akun |
| `device_name` | ❌ | Identitas perangkat, contoh: `web` / `android` (default: `web`, maks 50 karakter) |

Response menyertakan `access_token` yang disimpan otomatis ke `{{token}}` oleh Postman, plus field `mustChangePassword` (lihat bagian Password Default di bawah).

### POST /register

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `name` | ✅ | Nama lengkap |
| `email` | ✅ | Email unik |
| `password` | ✅ | Min 8 karakter, harus mengandung huruf dan angka |
| `confirm_password` | ✅ | Harus sama dengan `password` |
| `role` | ✅ | `Admin` / `Guru` / `Siswa` / `Karyawan` |

### POST /password (ganti password sendiri)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `current_password` | ✅ | Password saat ini |
| `new_password` | ✅ | Min 8 karakter, harus mengandung huruf dan angka |
| `confirm_password` | ✅ | Harus sama dengan `new_password` |

### POST /users/{id}/password (reset password user lain)

| Field | Wajib | Keterangan |
|-------|-------|------------|
| `new_password` | ✅ | Min 8 karakter, harus mengandung huruf dan angka |
| `confirm_password` | ✅ | Harus sama dengan `new_password` |

Semua token aktif milik target user langsung dicabut saat password direset.

---

## Role & Akses

| Role | GET | Write (POST/PATCH) | DELETE | Register |
|------|-----|--------------------|--------|----------|
| SuperAdmin | ✅ | ✅ | ✅ | Admin, Guru, Siswa, Karyawan |
| Admin | ✅ | ✅ | ✅* | Guru, Siswa, Karyawan |
| **Karyawan — Administrator Sekolah** | ✅ | ✅ (akademik + master data + kartu) | ✅* | Guru, Siswa, Karyawan |
| Guru / Siswa / Karyawan lain | ✅ terbatas — lihat [Privasi BACA](#privasi-baca--direktori-publik-vs-data-pribadi) | ✅ (password sendiri) | ❌ | ❌ |

Role `SuperAdmin` tidak dapat dibuat melalui API — hanya via `php artisan db:seed`.
Kolom GET di tabel ini hanya menyatakan *boleh memanggil*; **isi** yang dikembalikan
berbeda per role — data pribadi disaring untuk non-pengelola.

### Administrator Sekolah (staf Tata Usaha)

Pemetaan peran di sekolah ini: **Admin = Kepala Sekolah**, **SuperAdmin = akun
cadangan/darurat**, dan **Administrator Sekolah = staf TU** yang menjalankan
operasional harian. Karena itu staf TU butuh cakupan tulis yang luas tanpa menjadi
Admin.

**Cara kerjanya.** Akunnya tetap berrole `Karyawan` — sengaja, supaya absensi
pegawai, `rekap/pegawai/saya`, dan hak baca karyawan tidak berubah. Yang
membedakan adalah flag `users.is_admin_sekolah`; middleware `check.role` mengenali
pseudo-role **`AdminSekolah`**:

```php
->middleware('check.role:SuperAdmin,Admin,AdminSekolah')
```

SuperAdmin & Admin lolos lewat role; karyawan lolos hanya bila flag-nya menyala.
Karyawan lain (satpam, kebersihan) tetap **403**.

**Menandai seseorang:** `POST /karyawan` atau `POST /karyawan/update` dengan
`isAdminSekolah: true` — **hanya boleh oleh SuperAdmin/Admin**. Penanda ikut di
respons **login** dan **`GET /user`** sebagai `isAdminSekolah` (boolean), supaya
klien dapat menampilkan menu manajemen tanpa panggilan tambahan.

**Yang BOLEH:** manajemen akademik (pembagian kelas, pengampu, jadwal, jam,
periode, pengaturan absensi, wali kelas), **pergantian semester aktif**, CRUD data
induk (siswa, guru, karyawan, kelas, mapel, bobot nilai), kartu absensi + QR,
membuka jendela PIN, **rekap absensi pegawai lain**, laporan peringkat se-angkatan
+ ekspornya, dan `POST /register` untuk Guru/Siswa/Karyawan.

Singkatnya: seluruh operasional harian sekolah. Yang tersisa hanya urusan **akun
istimewa** — itulah satu-satunya batas yang dijaga.

**Yang TETAP DIKUNCI** (diuji di suite):

| Percobaan | Hasil |
|---|---|
| Membuat akun Admin / SuperAdmin | ❌ 422 |
| Menghapus / reset password akun Admin / SuperAdmin | ❌ 403 |
| Menghapus / reset password sesama Administrator Sekolah | ❌ 403 |
| Menghapus karyawan yang juga Administrator Sekolah | ❌ 403 |
| Menandai dirinya/orang lain sebagai Administrator Sekolah | ❌ flag diabaikan |

Mencabut penanda otomatis **mencabut seluruh token aktif** akun tersebut, agar hak
tulis yang sudah dicabut tidak terus dipakai sampai token kedaluwarsa.

**Penanda ada di dua tabel** — `users.is_admin_sekolah` (dipakai otorisasi) dan
`karyawans.is_admin_sekolah` (ditampilkan di daftar karyawan). Gateway menulis
keduanya dalam satu operasi, jadi normalnya selalu selaras. Kalau seseorang
mengubah database langsung, keduanya bisa berbeda; arah kegagalannya aman
(karyawan tampak "TU" tapi hak tulisnya tidak aktif, bukan sebaliknya). Cek
cepatnya — dijalankan di masing-masing DB, lalu bandingkan email yang muncul:

```sql
-- DB Gateway
SELECT email FROM users WHERE role='Karyawan' AND is_admin_sekolah=1;
-- DB KaryawanService
SELECT email FROM karyawans WHERE is_admin_sekolah=1 AND deleted_at IS NULL;
```

Perbaikannya cukup `POST /karyawan/update` dengan `isAdminSekolah` yang benar —
Gateway akan menyelaraskan ulang keduanya.

### Batas paginasi

Seluruh endpoint berpaginasi memakai **satu** batas: `per_page` maksimum **200**,
minimum 1, dan harus numerik. Di luar itu **422** — bukan diam-diam dipaksa ke
batas, supaya klien tahu permintaannya tidak dipenuhi apa adanya.

Berlaku di `GET /users`, `GET /siswa/all`, `/guru/all`, `/karyawan/all`,
`/class/all`, `/mapel/all`, dan keempat endpoint `/riwayat` di AkademikService.

Sebelumnya tidak ada batas sama sekali: `per_page=100000` diterima apa adanya dan
memaksa query tak terbatas. Di `GET /users` lebih buruk — nilainya masuk langsung
ke `paginate()` tanpa diperiksa, sehingga `per_page=abc` dan `per_page=-5`
melempar **500**.

> Kalau butuh menemukan satu baris tertentu, pakai filter server (`?search=`,
> `?role=`) — bukan `per_page` besar lalu menyaring di klien. Pola kedua diam-diam
> gagal begitu jumlah baris melewati ukuran halaman.

### Respons 500 tidak membocorkan detail internal

Semua service membangun respons lewat `ApiResponser::response()`. Untuk kode
**500**, pesan aslinya dicatat ke `storage/logs/laravel.log` dengan kode rujukan,
dan klien hanya menerima kalimat umum:

```json
{ "resCode": 500, "resMsg": "Terjadi kesalahan di server. Sertakan kode A1B2C3D4 saat melaporkannya." }
```

Sebelumnya pesan exception diteruskan apa adanya. Contoh nyata yang pernah sampai
ke klien saat `POST /karyawan` dengan email yang sudah terpakai: **statement SQL
utuh, nama database, host + port, dan hash password bcrypt** dari baris INSERT
yang gagal. Cukup dipicu oleh akun Admin biasa.

Detailnya tidak hilang — cari kode rujukannya di log service yang bersangkutan:

```bash
grep A1B2C3D4 KaryawanService/storage/logs/laravel.log
```

Diuji di suite (Fase 14.10): respons 500 tidak boleh memuat `SQLSTATE`,
`insert into`, `Database:`, `Connection:`, atau `$2y$`.

**Tiga jalur yang harus ditutup semua** — menutup satu saja tidak cukup, karena
error bisa keluar lewat jalur mana pun:

| Jalur | Dulu | Sekarang |
|---|---|---|
| `ApiResponser::response()` (184 pemanggilan `$e->getMessage()`) | pesan exception mentah di `resMsg` | kalimat umum + kode rujukan |
| Handler fallback Gateway (`bootstrap/app.php`) | pesan exception mentah di **`data`** | `data` dikosongkan untuk 5xx |
| Service **tanpa** handler sama sekali | render bawaan Laravel: `exception`, **path absolut file**, stack trace — dan **bukan** envelope, jadi klien tak bisa mem-parse | envelope `resCode/resMsg` + 5xx disanitasi |

Yang terakhir paling mudah terlewat: body service diteruskan Gateway **apa adanya**
ke klien, jadi render bawaan Laravel di service ikut sampai ke luar. `APP_DEBUG=false`
di produksi memang menutupi sebagian, tapi mengandalkan satu setelan env sebagai
satu-satunya penghalang terlalu rapuh.

Validasi (`ValidationException`) sengaja dibiarkan ditangani Laravel supaya
bentuk 422 beserta daftar error per field tidak berubah.

### Email bisa dipakai ulang setelah dihapus (pemulihan)

`users.email` dan `{tabel}.email` unik di level DB, sementara modelnya memakai soft
delete — jadi baris yang "dihapus" **menahan emailnya selamanya**. Dulu itu berarti
sebuah email tak pernah bisa dipakai ulang: siswa pindah lalu kembali, atau akun
salah ketik terlanjur dihapus, emailnya mati.

Sekarang baris lamanya **dipulihkan**, bukan ditolak:

| Kondisi | Hasil |
|---|---|
| Email dipegang akun/record **aktif** | **422** |
| Email dipegang akun/record **terhapus** | **201** + `dipulihkan: true` |
| `nip`/`nik`/`nisn` dipegang record lain (termasuk terhapus) | **422** |

**Baris yang SAMA yang dipulihkan**, bukan baris baru — id-nya tetap. Itu disengaja:
tautan ke riwayat akademik (nilai, kelas, absensi) berkunci id, dan membuat baris
baru akan memutusnya diam-diam.

**Nomor identitas sengaja tidak ikut.** Kalau `nip`/`nisn` dipegang record lain,
datanya memang orang berbeda — menimpanya diam-diam jauh lebih berbahaya daripada
menolak.

**Yang dilakukan saat memulihkan:**

1. Baris di-`restore()`, lalu **ditimpa** data baru (nama, role, password, penanda).
   Akun tidak hidup kembali dengan kredensial dan haknya yang dulu.
2. **Token lama dicabut.** Tanpa ini, siapa pun yang masih memegang token sebelum
   penghapusan langsung mendapat akses ke akun yang kini milik orang lain.
3. **Dicatat ke audit log** dengan `action = restored`, berikut pelaku, role, dan
   penanda `tokenDicabut`. Memulihkan akun adalah aksi istimewa — identitas lama
   hidup kembali dan tertaut ke record domain yang sama; kalau suatu saat
   dipertanyakan, inilah jejak yang menjawabnya.

Gagal menulis audit **tidak** menggagalkan pemulihannya, tapi dicatat sebagai
`Log::warning` agar tidak hilang tanpa jejak.

### Email/NIP/NISN ganda → 422, bukan 500 + data yatim

`POST /guru|siswa|karyawan` menulis **dua** tempat: record domain di service, lalu
akun user di Gateway. Tidak ada transaksi lintas-service, jadi urutannya penting.

Dulu Gateway membuat record domain lebih dulu. Kalau emailnya sudah dipakai akun
lain, `User::create()` gagal — record domainnya **terlanjur tersimpan tanpa akun
login**, dan pemanggil hanya menerima 500. Percobaan ulang lalu gagal dengan
alasan berbeda ("duplicate karyawans"), sehingga terlihat seperti buntu.

Sekarang Gateway memeriksa `UserService::emailDipakaiAktif()` **sebelum** menulis
apa pun dan membalas **422**. Yang menghalangi hanya akun **aktif** — email milik
akun terhapus dipulihkan, bukan ditolak (lihat bagian di atas).

Di sisi service, `email`/`nip`/`nik`/`nisn` kini divalidasi `unique:` juga —
sebelumnya kolomnya unik di DB tapi tidak divalidasi, sehingga duplikatnya lolos
validasi lalu meledak sebagai 500 dari driver alih-alih 422 yang bisa ditampilkan.

### Petugas Acara — peran yang dikurung

Penanda `users.is_petugas_acara`, pola sama dengan Administrator Sekolah: role
tetap, penanda menambah hak. Muncul sebagai `isPetugasAcara` di **login** dan
**`GET /user`**. Hanya SuperAdmin/Admin yang boleh memberikannya lewat
`POST /register` (Administrator Sekolah boleh mendaftarkan user, tapi penandanya
diabaikan — anti-eskalasi).

Bedanya dengan penanda lain: peran ini **menyempitkan**, bukan memperluas.

**Kenapa `check.role` saja tidak cukup.** `check.role` hanya MEMBERI hak — ia tak
pernah mencabut. Sebagian besar rute baca di sistem ini sengaja tanpa gate
(direktori guru/kelas/mapel, jadwal, semester), jadi memberi seseorang penanda ini
tidak otomatis menutup apa pun. Karena itu ada middleware terpisah
`BatasiPetugasAcara` dengan **daftar-BOLEH yang ditolak secara default** — bukan
daftar-larang, yang akan bocor tiap kali ada rute baru.

Middleware itu dipasang di **grup route** (sesudah `auth:api`), bukan sebagai
middleware global. Pernah dicoba global dan diam-diam tidak pernah aktif: global
berjalan sebelum autentikasi, sehingga `$request->user()` masih null.

Yang boleh: `akademik/acara*`; **baca saja** `akademik/periode`, `periode/aktif`,
`semester/aktif` (agar ia bisa melihat periode yang memblokir acaranya); serta
`/user`, `/password`, `/logout`, `/logout-all`, `/refresh`. Selain itu **403**.
Akun yang juga Admin/SuperAdmin/Adm. Sekolah tidak ikut disempitkan.

### `GET {modul}/nama` — bulk id + nama

Tersedia di `siswa`, `guru`, `karyawan`, `mapel`, `class`. Dua kebutuhan, satu endpoint:

1. **Resolusi nama historis.** Endpoint akademik hanya menyimpan id; klien
   meresolusinya dari roster **aktif**, sehingga entitas yang sudah lulus/pindah/
   dihapus tampil `#<id>`. Endpoint ini memakai **`withTrashed()`** — justru yang
   non-aktif yang jadi masalah.
2. **Cache sekolah besar.** `/all` dibatasi `per_page<=200`; sekolah ribuan siswa
   akan terpotong. Endpoint ini ringan (2 kolom, tanpa foto/PII) jadi aman dimuat
   sekaligus.

`?ids=1,2,3` menyaring; `ids=` yang tak menyisakan angka valid balas **kosong**,
bukan seluruh tabel. **Bukan pengganti `/all` untuk dropdown** — memuat non-aktif.

### Mode foto pada daftar (`?foto=1`)

`GET siswa|guru|karyawan/all` punya dua mode yang saling tarik:

| Mode | Aktif bila | `foto` | `per_page` |
|---|---|---|---|
| B — ringkas (default) | tanpa param | tidak ada | 1–200 |
| A — berfoto | `?foto=1` | **URL** | 1–25 (default 5) |

URL-nya menunjuk `GET /api/{modul}/foto/{id}` — endpoint **ber-autentikasi**, bukan
`/storage/...`. Foto ada di disk `private`; memindahkannya ke disk publik berarti
foto setiap siswa bisa diambil siapa pun yang menebak URL. Klien mengirim header
`Authorization` seperti biasa; responsnya `Cache-Control: private, max-age=86400`.

Kolom `foto` sengaja **tidak** di-select lewat model saat menyusun daftar —
accessor-nya mengubah path menjadi Base64, persis beban yang ingin dihindari.

### Privasi BACA — direktori publik vs data pribadi

Penanda Administrator Sekolah mengatur hak **tulis**; bagian ini mengatur hak
**baca**. Keputusan pemilik produk: direktori guru/karyawan/kelas/mapel adalah
**info sekolah** — boleh dilihat semua warga sekolah. Yang tidak ikut adalah
**data pribadi** (NIK, alamat, telepon, tanggal lahir, NISN, data orang tua) dan
**data akademik siswa**.

Karena itu ada dua predikat di `App\Models\User`, dan seluruh keputusan baca
memakainya — bukan daftar role yang ditulis ulang di tiap controller:

| Predikat | Isinya | Dipakai untuk |
|---|---|---|
| `isPengelola()` | SuperAdmin, Admin, Administrator Sekolah | PII pegawai, seluruh data akademik siswa |
| `bolehLihatPiiSiswa()` | `isPengelola()` + Guru | PII siswa (guru butuh kontak orang tua) |

**Matriks detail yang diterima tiap viewer:**

| Detail | Admin / SuperAdmin / Adm. Sekolah | Guru | Karyawan biasa | Siswa |
|---|:-:|:-:|:-:|:-:|
| `GET /guru?idGuru=` | penuh | publik | publik | publik |
| `GET /karyawan?idKaryawan=` | penuh | publik | publik | ❌ 403 |
| `GET /siswa?idSiswa=` · `GET /siswa/all` | penuh | penuh | ❌ 403 | publik |
| Nilai / raport / ranking kelas · rekap absensi siswa | ✅ | ✅ (sesuai lingkup wali/pengampu) | ❌ 403 | hanya `/saya` |

"Publik" = proyeksi whitelist di controller (`GURU_PUBLIC_FIELDS`,
`KARYAWAN_PUBLIC_FIELDS`, `SISWA_PUBLIC_FIELDS`) lewat trait `App\Traits\SaringPii`.
Whitelist, **bukan** blacklist: kolom baru di service otomatis tersaring, bukan
otomatis bocor. Meta paginasi dipertahankan agar klien memakai satu parser untuk
semua role.

**Karyawan biasa** (satpam, kebersihan — `isAdminSekolah=false`) boleh:
direktori guru/karyawan/kelas/mapel versi publik, `absensi/rekap/pegawai/saya`,
PIN sendiri, dan profil sendiri. Selain itu **403**.

**Pencarian ikut disaring, bukan hanya respons.** `GET /siswa/all?search=` mencari
di nama **dan NISN**. Membuang kolom `nisn` dari respons saja tidak cukup: selama
NISN masih jadi kunci cari, jumlah baris yang cocok menjadi oracle — terbukti bisa
memulihkan NISN utuh digit demi digit (`0` cocok 5 baris, `012` 2 baris,
`0123456784` 1 baris) lengkap dengan nama pemiliknya. Karena itu Gateway
menyalakan `search_publik=1` untuk viewer yang tidak berhak melihat NISN, dan
SiswaService membatasi pencarian ke nama saja. Flag itu ditentukan server dari
token; Gateway hanya meneruskan `page`/`per_page`/`search`, jadi klien tidak bisa
menitipkannya sendiri.

**Record milik pemanggil sendiri tidak disaring.** Seorang guru/karyawan yang
membuka detail dirinya sendiri tetap melihat alamat & nomor teleponnya — yang
dilindungi adalah data orang lain. Kecocokan diambil dari `email` pada payload
service dibandingkan email di token, **bukan** dari `id` yang dikirim klien,
sehingga tidak bisa dipalsukan dengan menebak id.

**`GET /siswa/saya` juga tidak tersaring** — itu profil diri sendiri, dan
subjeknya diresolve dari email token, bukan dari input klien.

Catatan penulisan: kondisi penyaringan menyebut pihak yang **berhak penuh**
(`if (!auth()->user()->isPengelola())`), bukan pihak yang disaring. Versi lama
menyebut role yang disaring (`in_array($role, ['Guru','Siswa'])`) — itulah sebab
role `Karyawan` ikut menerima PII penuh tanpa ada yang menyadarinya.

### Proteksi DELETE /users/{id}

| Kondisi | Hasil |
|---------|-------|
| Admin hapus Guru / Siswa / Karyawan | ✅ Diizinkan |
| Admin hapus Admin lain | ❌ 403 |
| Admin hapus SuperAdmin | ❌ 403 |
| Administrator Sekolah hapus Guru / Siswa / Karyawan biasa | ✅ Diizinkan |
| Administrator Sekolah hapus Admin / SuperAdmin | ❌ 403 |
| Administrator Sekolah hapus sesama Administrator Sekolah | ❌ 403 |
| SuperAdmin hapus Admin / Guru / Siswa / Karyawan | ✅ Diizinkan |
| Siapapun hapus SuperAdmin | ❌ 403 (tidak bisa via API) |
| Menghapus akun sendiri | ❌ 403 |

Hapus bersifat **soft delete** — kolom `deleted_at` terisi, data tetap di database. Token aktif milik user yang dihapus langsung dicabut.

### Proteksi Reset Password

- Admin hanya dapat reset password Guru, Siswa, Karyawan — tidak bisa reset Admin/SuperAdmin
- Administrator Sekolah dibatasi sama seperti Admin, **plus** tidak boleh mereset
  password sesama Administrator Sekolah — reset password adalah jalan pintas
  mengambil alih akun, jadi dijaga seketat penghapusan
- Password SuperAdmin tidak dapat direset via API
- `new_password` tidak boleh sama dengan password lama

---

## Password Default & Wajib Ganti Password

Akun guru/siswa dibuat **otomatis** saat data guru/siswa didaftarkan, dengan
password awal = alamat emailnya sendiri. Karena password default itu mudah
ditebak, akun tersebut ditandai `must_change_password` dan **wajib mengganti
password saat login pertama**:

- Response `POST /login` menyertakan `mustChangePassword: true/false`
- Selama flag masih `true`, semua endpoint diblokir **403** dengan
  `data: {"mustChangePassword": true}` — kecuali `POST /password`, `GET /user`,
  `POST /logout`, dan `POST /logout-all`
- Setelah `POST /password` sukses, flag terhapus dan akses normal kembali
- Akun yang dibuat via `POST /register` (password dipilih admin) dan SuperAdmin
  tidak terkena kewajiban ini; migration mem-backfill akun lama yang masih
  memakai password default

---

## Token & Sesi

- Token OAuth2 (Bearer) berlaku **8 jam**
- `POST /refresh` menukar token yang **masih valid** dengan token baru 8 jam
  (token lama langsung dicabut, `device_name` diwarisi) — klien dapat memperpanjang sesi tanpa login ulang.
  Token yang sudah kedaluwarsa tidak bisa di-refresh; user harus login kembali
- **Satu sesi aktif per device**: login baru hanya mencabut token lama dari
  `device_name` yang sama. Login di `web` dan `android` bisa berjalan bersamaan;
  login ulang di `android` hanya menendang sesi `android` yang lama
- `POST /logout-all` mencabut **semua** sesi di semua device — gunakan jika akun
  dicurigai dipakai orang lain
- Endpoint `/login`, `/refresh`, `/password`, dan `/oauth/token` dibatasi **5 percobaan per menit**

### Rate limit seluruh API

| Cakupan | Batas | Kunci |
|---|---|---|
| `/login`, `/refresh`, `/password`, `/oauth/token` | 5/menit | per IP |
| **Semua endpoint lain (sudah login)** | **240/menit** | per **user** |
| Route tanpa `auth` | 300/menit | per IP |

429 membawa `Retry-After`, `X-RateLimit-Limit`, `X-RateLimit-Remaining`,
`X-RateLimit-Reset`, dan `data.retryAfter`.

Batasnya **per user** karena ratusan perangkat sekolah berbagi satu IP publik —
ember per-IP membuat satu kelas bisa saling mengunci. Angka 240 dipilih agar layar
daftar berfoto (1 permintaan daftar + 25 permintaan foto) tidak terputus.

> **`$middleware->throttleApi()` di `bootstrap/app.php` wajib ada.** Sejak Laravel
> 11 grup `api` bawaan tidak memuat throttle kecuali baris itu dipanggil; tanpanya
> `RateLimiter::for('api')` terdefinisi tapi tak pernah dipakai dan API berjalan
> tanpa batas. Dijaga Fase 18 di `run-tests.ps1`.

Request **tanpa token ke route terproteksi tidak terbatasi**: `$middlewarePriority`
Laravel menempatkan autentikasi di atas throttle, jadi `auth:api` menolak lebih
dulu. Hasilnya hanya 401 murah.

---

## Terminal Absensi

Absen via scan/PIN hanya diterima dari **terminal terdaftar** (perangkat sekolah di gerbang/ruang guru/TU), bukan dari perangkat pribadi. Autentikasi terminal terpisah dari Bearer token user.

**Header wajib:** `X-Terminal-Id` dan `X-Terminal-Token` (token dicek terhadap hash SHA-256 di DB).

**Mode terminal:**

| Mode | Cara verifikasi lokasi | Untuk |
|------|------------------------|-------|
| `produksi` | IP request harus di `ip_allowlist` (LAN sekolah) | perangkat tetap di sekolah |
| `demo` | `lat`/`lng` di body harus di dalam geofence (radius) terminal | simulasi tanpa jaringan sekolah |

**Daftarkan terminal** (token hanya ditampilkan sekali):

```bash
# Produksi (allowlist IP/CIDR LAN)
php artisan terminal:register --nama="Gerbang Utama" --lokasi=gerbang --mode=produksi --ip="192.168.1.0/24"

# Demo (geofence)
php artisan terminal:register --nama="Gerbang Utama" --lokasi=gerbang --mode=demo --lat=-6.200000 --lng=106.816666 --radius=150
```

**Jendela PIN (lupa kartu):** admin membuka jendela time-boxed (default 10 menit, dari `durasi_pin_window_menit` pengaturan absensi) untuk seorang pegawai; pegawai lalu absen dengan NIP+PIN di terminal. Jendela **sekali pakai** — hangus setelah dipakai atau kedaluwarsa.

---

## Audit Log

Semua aksi tulis (create, update, delete, login, register) dicatat di tabel `audit_logs`.

| Kolom | Isi |
|-------|-----|
| `action` | `login` / `created` / `updated` / `deleted` / `registered` |
| `resource` | `guru` / `siswa` / `mapel` / `kelas` / `user` / dst. |
| `resource_id` | ID record yang diubah |
| `performed_by` | Email pelaku aksi |
| `role` | Role pelaku |
| `ip_address` | IP address pengirim request |
| `payload` | Data yang dikirim (foto dan password otomatis disanitasi) |

---

## Database Index

| Tabel | Index |
|-------|-------|
| `users` | `role`, `deleted_at` |
| `audit_logs` | `(resource, resource_id)`, `performed_by`, `created_at` |
