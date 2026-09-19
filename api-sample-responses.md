# Contoh Response API - SIM Sekolah (hasil capture asli)

Dokumen ini berisi response JSON **asli** yang direkam dari Gateway pada 2026-09-19,
sebagai referensi bentuk DTO untuk pengembangan aplikasi client (Android, dsb).

- Regenerate: `powershell -ExecutionPolicy Bypass -File capture-api-samples.ps1`
  (set `$env:TEST_ADMIN_PASSWORD` dulu; jalankan `seed-test-accounts.ps1` sekali sebelumnya)
- Field `foto` (base64 WebP) dan `token` dipotong agar dokumen ringkas -- panjang aslinya ribuan karakter.
- Semua response memakai envelope tetap: `resCode`, `resPhrase`, `resStatus` (`success`/`fail`), `resMsg`, `data`.
- Endpoint list master data paginated: isi sebenarnya di `data.data`, metadata paginator di `data.current_page`, `data.last_page`, `data.total`, dst.

## Akun test (hasil seed-test-accounts.ps1)

| Role | Email | Password | Keterangan |
|---|---|---|---|
| Admin | akuntest.admin@example.com | AdminTest123 | Akun baru |
| Karyawan | akuntest.karyawan@example.com | KaryawanTest123 | Karyawan BIASA — tanpa hak manajemen (pembanding) |
| **Administrator Sekolah** | akuntest.adminsekolah@example.com | AdminSekolahTest123 | Karyawan bertanda isAdminSekolah: true — boleh manajemen akademik. Pakai untuk menguji gating menu |
| Guru | andi.susanto2@sekolah.com | GuruTest123 | Terhubung record guru id=1 (punya pengampu) |
| Siswa | andi.siswa1@sekolah.com | SiswaTest123 | Terhubung record siswa id=1 (terdaftar di kelas) |

## PENTING: bentuk response berbeda per role

Sebagian besar contoh di bawah direkam sebagai **SuperAdmin**, jadi memperlihatkan
bentuk **terlengkap**. Untuk viewer lain, Gateway **membuang** field data pribadi —
field-nya tidak dikirim sama sekali, bukan dikirim bernilai null.

| Detail | Pengelola (SuperAdmin/Admin/Adm. Sekolah) | Guru | Karyawan biasa | Siswa |
|---|:-:|:-:|:-:|:-:|
| `guru?idGuru=` | penuh | publik | publik | publik |
| `karyawan?idKaryawan=` | penuh | publik | publik | 403 |
| `siswa?idSiswa=` & `siswa/all` | penuh | penuh | **403** | publik |
| nilai / raport / ranking / rekap absensi siswa | ya | sesuai lingkup wali/pengampu | **403** | hanya `/saya` |

**Konsekuensi untuk klien:** buat SEMUA field pribadi di DTO detail **nullable**.
DTO non-nullable akan melempar `MissingFieldException` saat membuka layar dengan
akun karyawan biasa atau siswa — layarnya blank, bukan sekadar kosong. Contoh
nyata tiap bentuk ada di bagian **Perilaku per Role** di akhir dokumen.

---

## Auth

### `POST /login`

Login sukses. `device_name` opsional (default `web`).

Request body:
```json
{
  "email": "superadmin@example.com",
  "device_name": "web",
  "password": "PasswordSuperAdmin"
}
```

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Access granted.",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI...<dipotong>",
    "user": "Super Admin",
    "email": "superadmin@example.com",
    "role": "SuperAdmin",
    "isAdminSekolah": false,
    "isPetugasAcara": false,
    "mustChangePassword": false
  }
}
```

### `GET /user`

Profil akun yang sedang login.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Data user.",
  "data": {
    "id": 1,
    "name": "Super Admin",
    "email": "superadmin@example.com",
    "role": "SuperAdmin",
    "must_change_password": false,
    "email_verified_at": null,
    "created_at": "2026-06-15T07:31:39.000000Z",
    "updated_at": "2026-07-13T20:56:46.000000Z",
    "deleted_at": null,
    "isAdminSekolah": false,
    "isPetugasAcara": false
  }
}
```

---

## Manajemen User (SuperAdmin/Admin)

### `GET /users?page=1&per_page=3`

Bentuk paginator Laravel: perhatikan `data.data` (array user) di dalam `data`.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Data users.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "Super Admin",
        "email": "superadmin@example.com",
        "role": "SuperAdmin",
        "must_change_password": false,
        "created_at": "2026-06-15T07:31:39.000000Z",
        "isAdminSekolah": false,
        "isPetugasAcara": false
      },
      {
        "id": 2,
        "name": "Ahmad Admin",
        "email": "ahmad.admin@sekolah.com",
        "role": "Admin",
        "must_change_password": false,
        "created_at": "2026-06-15T07:42:38.000000Z",
        "isAdminSekolah": false,
        "isPetugasAcara": false
      },
      {
        "id": 3,
        "name": "Budi Guru",
        "email": "budi.guru@sekolah.com",
        "role": "Guru",
        "must_change_password": false,
        "created_at": "2026-06-15T07:42:54.000000Z",
        "isAdminSekolah": false,
        "isPetugasAcara": false
      }
    ],
    "first_page_url": "https:\/\/gateway.test\/api\/users?per_page=3&page=1",
    "from": 1,
    "last_page": 18,
    "last_page_url": "https:\/\/gateway.test\/api\/users?per_page=3&page=18",
    "links": [
      {
        "url": null,
        "label": "&laquo; Previous",
        "page": null,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?per_page=3&page=1",
        "label": "1",
        "page": 1,
        "active": true
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?per_page=3&page=2",
        "label": "2",
        "page": 2,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?per_page=3&page=3",
        "label": "3",
        "page": 3,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?per_page=3&page=4",
        "label": "4",
        "page": 4,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?per_page=3&page=5",
        "label": "5",
        "page": 5,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?per_page=3&page=6",
        "label": "6",
        "page": 6,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?per_page=3&page=7",
        "label": "7",
        "page": 7,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?per_page=3&page=8",
        "label": "8",
        "page": 8,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?per_page=3&page=9",
        "label": "9",
        "page": 9,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?per_page=3&page=10",
        "label": "10",
        "page": 10,
        "active": false
      },
      {
        "url": null,
        "label": "...",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?per_page=3&page=17",
        "label": "17",
        "page": 17,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?per_page=3&page=18",
        "label": "18",
        "page": 18,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?per_page=3&page=2",
        "label": "Next &raquo;",
        "page": 2,
        "active": false
      }
    ],
    "next_page_url": "https:\/\/gateway.test\/api\/users?per_page=3&page=2",
    "path": "https:\/\/gateway.test\/api\/users",
    "per_page": 3,
    "prev_page_url": null,
    "to": 3,
    "total": 54
  }
}
```

### `GET /users?search=akuntest.admin`

Search partial di nama/email. Bisa digabung `role=` (filter exact) dan `per_page`.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Data users.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 82,
        "name": "Akun Test Admin",
        "email": "akuntest.admin@example.com",
        "role": "Admin",
        "must_change_password": false,
        "created_at": "2026-07-10T20:47:59.000000Z",
        "isAdminSekolah": false,
        "isPetugasAcara": false
      },
      {
        "id": 151,
        "name": "Akun Test Administrator Sekolah",
        "email": "akuntest.adminsekolah@example.com",
        "role": "Karyawan",
        "must_change_password": false,
        "created_at": "2026-08-28T08:12:26.000000Z",
        "isAdminSekolah": true,
        "isPetugasAcara": false
      }
    ],
    "first_page_url": "https:\/\/gateway.test\/api\/users?search=akuntest.admin&page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "https:\/\/gateway.test\/api\/users?search=akuntest.admin&page=1",
    "links": [
      {
        "url": null,
        "label": "&laquo; Previous",
        "page": null,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?search=akuntest.admin&page=1",
        "label": "1",
        "page": 1,
        "active": true
      },
      {
        "url": null,
        "label": "Next &raquo;",
        "page": null,
        "active": false
      }
    ],
    "next_page_url": null,
    "path": "https:\/\/gateway.test\/api\/users",
    "per_page": 10,
    "prev_page_url": null,
    "to": 2,
    "total": 2
  }
}
```

### `GET /users/82`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Data user.",
  "data": {
    "id": 82,
    "name": "Akun Test Admin",
    "email": "akuntest.admin@example.com",
    "role": "Admin",
    "must_change_password": false,
    "created_at": "2026-07-10T20:47:59.000000Z",
    "isAdminSekolah": false,
    "isPetugasAcara": false
  }
}
```

### `POST /register`

Request body:
```json
{
  "email": "sampleuser_164009@example.com",
  "name": "Sample User",
  "password": "SamplePass123",
  "confirm_password": "SamplePass123",
  "role": "Karyawan"
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "User registered.",
  "data": {
    "user": "Sample User",
    "email": "sampleuser_164009@example.com",
    "role": "Karyawan",
    "isPetugasAcara": false,
    "dipulihkan": false
  }
}
```

### `POST /users/356/password`

Reset password user lain. Semua token aktif milik target dicabut.

Request body:
```json
{
  "confirm_password": "ResetPass456",
  "new_password": "ResetPass456"
}
```

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Password user berhasil direset. User harus login ulang.",
  "data": {
    "name": "Sample User",
    "email": "sampleuser_164009@example.com",
    "role": "Karyawan"
  }
}
```

### `DELETE /users/356`

Soft delete + semua token target dicabut.

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Akun user berhasil dihapus.",
  "data": {
    "name": "Sample User",
    "email": "sampleuser_164009@example.com",
    "role": "Karyawan"
  }
}
```

### `GET /users/terhapus?per_page=3`

Daftar akun soft-deleted untuk layar `Pulihkan Akun`. Terbaru dihapus di atas. Akun di sini TIDAK muncul di `GET /users` biasa. Akses **SuperAdmin & Admin saja** (Administrator Sekolah 403).

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Daftar akun terhapus.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 356,
        "name": "Sample User",
        "email": "sampleuser_164009@example.com",
        "role": "Karyawan",
        "isAdminSekolah": false,
        "isPetugasAcara": false,
        "deletedAt": "2026-09-19T09:40:12.000000Z"
      },
      {
        "id": 343,
        "name": "Karyawan Test 163305",
        "email": "testkaryawan_163305@example.com",
        "role": "Karyawan",
        "isAdminSekolah": false,
        "isPetugasAcara": false,
        "deletedAt": "2026-09-19T09:39:49.000000Z"
      },
      {
        "id": 342,
        "name": "Siswa Test Role 163305",
        "email": "testsiswarole_163305@example.com",
        "role": "Siswa",
        "isAdminSekolah": false,
        "isPetugasAcara": false,
        "deletedAt": "2026-09-19T09:39:49.000000Z"
      }
    ],
    "first_page_url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=1",
    "from": 1,
    "last_page": 101,
    "last_page_url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=101",
    "links": [
      {
        "url": null,
        "label": "&laquo; Previous",
        "page": null,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=1",
        "label": "1",
        "page": 1,
        "active": true
      },
      {
        "url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=2",
        "label": "2",
        "page": 2,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=3",
        "label": "3",
        "page": 3,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=4",
        "label": "4",
        "page": 4,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=5",
        "label": "5",
        "page": 5,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=6",
        "label": "6",
        "page": 6,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=7",
        "label": "7",
        "page": 7,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=8",
        "label": "8",
        "page": 8,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=9",
        "label": "9",
        "page": 9,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=10",
        "label": "10",
        "page": 10,
        "active": false
      },
      {
        "url": null,
        "label": "...",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=100",
        "label": "100",
        "page": 100,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=101",
        "label": "101",
        "page": 101,
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=2",
        "label": "Next &raquo;",
        "page": 2,
        "active": false
      }
    ],
    "next_page_url": "https:\/\/gateway.test\/api\/users\/terhapus?per_page=3&page=2",
    "path": "https:\/\/gateway.test\/api\/users\/terhapus",
    "per_page": 3,
    "prev_page_url": null,
    "to": 3,
    "total": 302
  }
}
```

### `POST /users/356/restore`

Aktifkan ulang **APA ADANYA**: nama, role, dan password lama TIDAK diubah -- beda dengan memulihkan lewat `POST /register` email sama, yang menimpa ketiganya. Body opsional ``{ "password": "..." }`` bila passwordnya memang perlu diganti. `data.catatan` muncul untuk role Guru/Siswa/Karyawan: endpoint ini hanya menyentuh tabel `users`, record domainnya tidak ikut dipulihkan.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Akun dipulihkan apa adanya \u2014 nama, role, dan password lama tidak diubah. Sesi lama dicabut \u2014 user harus login ulang.",
  "data": {
    "id": 356,
    "name": "Sample User",
    "email": "sampleuser_164009@example.com",
    "role": "Karyawan",
    "isAdminSekolah": false,
    "isPetugasAcara": false,
    "passwordDiubah": false,
    "tokenDicabut": true,
    "wajibGantiPassword": false,
    "catatan": "Hanya akun login yang dipulihkan. Bila dulu dihapus lewat DELETE \/karyawan\/{id}, record domainnya masih terhapus \u2014 buat ulang lewat POST \/karyawan dengan email yang sama; record lama akan dipulihkan, bukan dibuat baru."
  }
}
```

### `POST /users/356/restore`

Diulang saat akun sudah aktif: **409**, bukan 500. Aman dipanggil berkali-kali.

Response (HTTP 409):
```json
{
  "resCode": 409,
  "resPhrase": "Conflict",
  "resStatus": "fail",
  "resMsg": "Akun sampleuser_164009@example.com masih aktif \u2014 tidak ada yang perlu dipulihkan.",
  "data": {
    "id": 356,
    "email": "sampleuser_164009@example.com",
    "role": "Karyawan"
  }
}
```

---

## Mata Pelajaran

### `GET /mapel/all?page=1&per_page=3`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "List Mata Pelajaran.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "idPelajaran": 1,
        "kode": "MTK",
        "namaPelajaran": "Matematika Lanjutan 1781687275",
        "keterangan": "Updated via test"
      },
      {
        "idPelajaran": 2,
        "kode": "BIN",
        "namaPelajaran": "Bahasa Indonesia",
        "keterangan": "Bahasa indonesia"
      },
      {
        "idPelajaran": 4,
        "kode": "IPS",
        "namaPelajaran": "Ilmu Pengetahuan Sosial",
        "keterangan": "Sosial"
      }
    ],
    "from": 1,
    "last_page": 10,
    "links": [
      {
        "query": "per_page=3&page=1",
        "label": "1",
        "page": 1,
        "active": true
      },
      {
        "query": "per_page=3&page=2",
        "label": "2",
        "page": 2,
        "active": false
      },
      {
        "query": "per_page=3&page=3",
        "label": "3",
        "page": 3,
        "active": false
      },
      {
        "query": "per_page=3&page=2",
        "label": "Next &raquo;",
        "page": 2,
        "active": false
      }
    ],
    "per_page": 3,
    "to": 3,
    "total": 28
  }
}
```

### `GET /mapel?idPelajaran=1`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Mata Pelajaran dengan id:1.",
  "data": {
    "idPelajaran": 1,
    "kode": "MTK",
    "namaPelajaran": "Matematika Lanjutan 1781687275",
    "keterangan": "Updated via test",
    "created_at": "2026-06-15T07:46:31.000000Z",
    "updated_at": "2026-06-19T02:39:56.000000Z",
    "deleted_at": null
  }
}
```

### `POST /mapel`

Request body:
```json
{
  "kode": "SMPL164009",
  "namaPelajaran": "Mapel Contoh 164009",
  "keterangan": "Dibuat oleh capture-api-samples.ps1"
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "Mata Pelajaran berhasil disimpan.",
  "data": {
    "kode": "SMPL164009",
    "namaPelajaran": "Mapel Contoh 164009",
    "keterangan": "Dibuat oleh capture-api-samples.ps1",
    "updated_at": "2026-09-19T09:40:15.000000Z",
    "created_at": "2026-09-19T09:40:15.000000Z",
    "idPelajaran": 107
  }
}
```

### `POST /mapel/update`

Update parsial: kirim `idPelajaran` + field yang berubah saja.

Request body:
```json
{
  "keterangan": "Keterangan diubah",
  "idPelajaran": 107
}
```

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Mata Pelajaran dengan id:107 berhasil diupdate.",
  "data": {
    "idPelajaran": 107,
    "kode": "SMPL164009",
    "namaPelajaran": "Mapel Contoh 164009",
    "keterangan": "Keterangan diubah",
    "created_at": "2026-09-19T09:40:15.000000Z",
    "updated_at": "2026-09-19T09:40:15.000000Z",
    "deleted_at": null
  }
}
```

### `DELETE /mapel/107`

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Mata Pelajaran dengan id:107 berhasil dihapus.",
  "data": []
}
```

---

## Kelas (Ruang Kelas)

### `GET /class/all?page=1&per_page=3`

`namaKelas` dibentuk server dari tingkat+jurusan+noKelas. `deletedAt` null = aktif.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "List Ruang Kelas.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "idKelas": 1,
        "namaKelas": "X MIPA 1",
        "tingkat": "1",
        "jurusan": "MIPA",
        "limitSiswa": "40"
      },
      {
        "idKelas": 2,
        "namaKelas": "X IPS 2",
        "tingkat": "1",
        "jurusan": "IPS",
        "limitSiswa": "36"
      },
      {
        "idKelas": 4,
        "namaKelas": "XII IPS 1",
        "tingkat": "3",
        "jurusan": "IPS",
        "limitSiswa": "32"
      }
    ],
    "from": 1,
    "last_page": 4,
    "links": [
      {
        "query": "per_page=3&page=1",
        "label": "1",
        "page": 1,
        "active": true
      },
      {
        "query": "per_page=3&page=2",
        "label": "2",
        "page": 2,
        "active": false
      },
      {
        "query": "per_page=3&page=3",
        "label": "3",
        "page": 3,
        "active": false
      },
      {
        "query": "per_page=3&page=2",
        "label": "Next &raquo;",
        "page": 2,
        "active": false
      }
    ],
    "per_page": 3,
    "to": 3,
    "total": 10
  }
}
```

### `GET /class?idKelas=1`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Ruang kelas dengan id:1.",
  "data": {
    "idKelas": 1,
    "namaKelas": "X MIPA 1",
    "tingkat": "1",
    "jurusan": "MIPA",
    "limitSiswa": "40",
    "created_at": "2026-06-15T07:47:07.000000Z",
    "updated_at": "2026-06-15T07:47:17.000000Z",
    "deleted_at": null
  }
}
```

### `POST /class`

Request body:
```json
{
  "limitSiswa": 30,
  "jurusan": "IPS",
  "tingkat": 3,
  "noKelas": 96
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "Ruang kelas berhasil disimpan.",
  "data": {
    "namaKelas": "XII IPS 96",
    "tingkat": 3,
    "jurusan": "IPS",
    "limitSiswa": 30,
    "updated_at": "2026-09-19T09:40:17.000000Z",
    "created_at": "2026-09-19T09:40:17.000000Z",
    "idKelas": 129
  }
}
```

### `POST /class/update`

Request body:
```json
{
  "idKelas": 129,
  "limitSiswa": 32
}
```

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Ruang kelas dengan id:129 berhasil diupdate.",
  "data": {
    "idKelas": 129,
    "namaKelas": "XII IPS 96",
    "tingkat": "3",
    "jurusan": "IPS",
    "limitSiswa": "32",
    "created_at": "2026-09-19T09:40:17.000000Z",
    "updated_at": "2026-09-19T09:40:17.000000Z",
    "deleted_at": null
  }
}
```

---

## Guru

### `GET /guru/all?page=1&per_page=3`

List ringan tanpa foto/data pribadi.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "List Data Guru.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "idGuru": 1,
        "namaLengkap": "Andi Susanto",
        "nip": "9876543210123401",
        "email": "andi.susanto2@sekolah.com",
        "jabatan": "Guru Senior",
        "statusKepegawaian": "PNS"
      },
      {
        "idGuru": 2,
        "namaLengkap": "Budi Hartono",
        "nip": "9876543210123402",
        "email": "budi.hartono2@sekolah.com",
        "jabatan": "Guru Tetap",
        "statusKepegawaian": "PNS"
      },
      {
        "idGuru": 4,
        "namaLengkap": "Dedi Kurniawan",
        "nip": "9876543210123404",
        "email": "dedi.kurniawan2@sekolah.com",
        "jabatan": "Kepala Sekolah",
        "statusKepegawaian": "PNS"
      }
    ],
    "from": 1,
    "last_page": 3,
    "links": [
      {
        "query": "per_page=3&page=1",
        "label": "1",
        "page": 1,
        "active": true
      },
      {
        "query": "per_page=3&page=2",
        "label": "2",
        "page": 2,
        "active": false
      },
      {
        "query": "per_page=3&page=3",
        "label": "3",
        "page": 3,
        "active": false
      },
      {
        "query": "per_page=3&page=2",
        "label": "Next &raquo;",
        "page": 2,
        "active": false
      }
    ],
    "per_page": 3,
    "to": 3,
    "total": 9
  }
}
```

### `GET /guru?idGuru=1`

Detail LENGKAP -- hanya diterima PENGELOLA (SuperAdmin, Admin, Administrator Sekolah). Guru, Siswa, dan karyawan biasa menerima versi tersaring (lihat bagian Perilaku per Role di bawah).

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Guru dengan id:1.",
  "data": {
    "idGuru": 1,
    "email": "andi.susanto2@sekolah.com",
    "nik": "1234567890123401",
    "nip": "9876543210123401",
    "namaLengkap": "Andi Susanto",
    "telephone": "081234567801",
    "jenisKelamin": "Laki-Laki",
    "tempatLahir": "Jakarta",
    "tanggalLahir": "1980-05-15",
    "agama": "Islam",
    "statusPernikahan": null,
    "alamat": "Jl. Merdeka 1",
    "foto": "data:image\/webp;base64,UklGRqpHAABXRUJQVlA4IJ5H...<dipotong>",
    "kartu_uid": "GUR-PO6VVXUVN9PE",
    "kartu_status": "aktif",
    "kartu_diterbitkan_at": "2026-09-19T09:38:00.000000Z",
    "statusKepegawaian": "PNS",
    "nomorSKPengangkatan": null,
    "tanggalMasuk": "2005-01-10",
    "jabatan": "Guru Senior",
    "nomorSertifikasi": null,
    "pendidikanTerakhir": "S1",
    "jurusan": "Matematika",
    "universitas": "UI",
    "tahunLulus": "2003",
    "pelatihan": null,
    "created_at": "2026-06-15T07:49:12.000000Z",
    "updated_at": "2026-09-19T09:38:10.000000Z",
    "deleted_at": null
  }
}
```

### `GET /guru?idGuru=99999`

ID tidak ada.

Response (HTTP 422):
```json
{
  "resCode": 422,
  "resPhrase": "Unprocessible Entity",
  "resStatus": "fail",
  "resMsg": "Guru dengan id:99999 tidak ada di database.",
  "data": {
    "idGuru": [
      "Guru dengan id:99999 tidak ada di database."
    ]
  }
}
```

---

## Siswa

### `GET /siswa/all?page=1&per_page=3`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "List Data Siswa.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "idSiswa": 1,
        "namaLengkap": "Andi Wijaya",
        "nisn": "0123456781",
        "jenisKelamin": "Laki-Laki",
        "tempatLahir": "Bandung",
        "tanggalLahir": "2008-05-20",
        "tanggalMasuk": "2023-07-10",
        "status": "Aktif"
      },
      {
        "idSiswa": 4,
        "namaLengkap": "Dedi Pratama",
        "nisn": "0123456784",
        "jenisKelamin": "Laki-Laki",
        "tempatLahir": "Semarang",
        "tanggalLahir": "2008-09-25",
        "tanggalMasuk": "2023-07-10",
        "status": "Aktif"
      },
      {
        "idSiswa": 6,
        "namaLengkap": "Andi Pratama",
        "nisn": "990615222501",
        "jenisKelamin": "Laki-Laki",
        "tempatLahir": "Jakarta",
        "tanggalLahir": "2007-03-10",
        "tanggalMasuk": "2022-07-15",
        "status": "Aktif"
      }
    ],
    "from": 1,
    "last_page": 3,
    "links": [
      {
        "query": "per_page=3&page=1",
        "label": "1",
        "page": 1,
        "active": true
      },
      {
        "query": "per_page=3&page=2",
        "label": "2",
        "page": 2,
        "active": false
      },
      {
        "query": "per_page=3&page=3",
        "label": "3",
        "page": 3,
        "active": false
      },
      {
        "query": "per_page=3&page=2",
        "label": "Next &raquo;",
        "page": 2,
        "active": false
      }
    ],
    "per_page": 3,
    "to": 3,
    "total": 7
  }
}
```

### `GET /siswa?idSiswa=1`

Detail lengkap termasuk data orang tua/wali -- untuk PENGELOLA dan Guru. Role Siswa menerima versi publik, karyawan biasa mendapat 403 (lihat bagian Perilaku per Role).

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Siswa dengan id:1.",
  "data": {
    "idSiswa": 1,
    "email": "andi.siswa1@sekolah.com",
    "nisn": "0123456781",
    "namaLengkap": "Andi Wijaya",
    "telephone": "081234561",
    "jenisKelamin": "Laki-Laki",
    "status": "Aktif",
    "statusDate": null,
    "tempatLahir": "Bandung",
    "tanggalLahir": "2008-05-20",
    "agama": null,
    "tanggalMasuk": "2023-07-10",
    "alamat": "Jl. Baru No.1 Updated",
    "foto": "data:image\/webp;base64,UklGRpIvAABXRUJQVlA4IIYv...<dipotong>",
    "kartu_uid": "SIS-EV0GZFSNDEY3",
    "kartu_status": "aktif",
    "kartu_diterbitkan_at": "2026-09-19T09:38:00.000000Z",
    "namaAyah": null,
    "namaIbu": "Siti Aminah",
    "pekerjaanAyah": null,
    "pekerjaanIbu": null,
    "noTelpAyah": null,
    "noTelpIbu": null,
    "namaWali": null,
    "hubunganWali": null,
    "noTelpWali": null,
    "created_at": "2026-06-15T00:50:37.000000Z",
    "updated_at": "2026-09-19T09:38:00.000000Z",
    "deleted_at": null
  }
}
```

---

## Akademik - Semester dan Jam Pelajaran

### `GET /akademik/semester/aktif`

Ambil sekali saat app start; pakai sebagai default tahun_ajaran/semester.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Semester aktif.",
  "data": {
    "idSemesterAktif": 6,
    "tahunAjaran": "2024\/2025",
    "semester": "2",
    "tanggalMulai": "2025-01-05T17:00:00.000000Z",
    "tanggalSelesai": null,
    "isAktif": true
  }
}
```

### `GET /akademik/semester/riwayat`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Riwayat semester.",
  "data": [
    {
      "idSemesterAktif": 5,
      "tahunAjaran": "2026\/2027",
      "semester": "1",
      "tanggalMulai": "2026-09-30T17:00:00.000000Z",
      "tanggalSelesai": "2027-03-30T17:00:00.000000Z",
      "isAktif": false
    },
    {
      "idSemesterAktif": 3,
      "tahunAjaran": "2025\/2026",
      "semester": "1",
      "tanggalMulai": "2025-07-13T17:00:00.000000Z",
      "tanggalSelesai": null,
      "isAktif": false
    },
    {
      "idSemesterAktif": 2,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "tanggalMulai": "2025-01-05T17:00:00.000000Z",
      "tanggalSelesai": null,
      "isAktif": false
    },
    {
      "idSemesterAktif": 4,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "tanggalMulai": "2025-01-05T17:00:00.000000Z",
      "tanggalSelesai": null,
      "isAktif": false
    },
    {
      "idSemesterAktif": 6,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "tanggalMulai": "2025-01-05T17:00:00.000000Z",
      "tanggalSelesai": null,
      "isAktif": true
    },
    {
      "idSemesterAktif": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "1",
      "tanggalMulai": "2024-07-14T17:00:00.000000Z",
      "tanggalSelesai": "2024-12-19T17:00:00.000000Z",
      "isAktif": false
    }
  ]
}
```

### `GET /akademik/jam`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Daftar jam pelajaran.",
  "data": [
    {
      "idJam": 1,
      "periodeId": null,
      "hari": null,
      "ke": 1,
      "jamMulai": "07:00:00",
      "jamSelesai": "07:45:00"
    },
    {
      "idJam": 2,
      "periodeId": null,
      "hari": null,
      "ke": 2,
      "jamMulai": "07:50:00",
      "jamSelesai": "08:30:00"
    },
    {
      "idJam": 9,
      "periodeId": null,
      "hari": null,
      "ke": 3,
      "jamMulai": "08:30:00",
      "jamSelesai": "09:15:00"
    },
    {
      "idJam": 21,
      "periodeId": 47,
      "hari": null,
      "ke": 1,
      "jamMulai": "05:00:00",
      "jamSelesai": "05:30:00"
    }
  ]
}
```

---

## Akademik - Pembagian Kelas

### `GET /akademik/siswa/belum-terdaftar?tahun_ajaran=2024%2F2025&semester=2`

PERHATIAN: response ini snake_case (pengecualian satu-satunya di modul akademik).

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Siswa belum terdaftar di kelas.",
  "data": {
    "tahun_ajaran": "2024\/2025",
    "semester": 2,
    "total_siswa": 7,
    "total_terdaftar": 4,
    "total_belum": 3,
    "siswa": [
      {
        "idSiswa": 8,
        "namaLengkap": "Cahyo Nugraha",
        "nisn": "990615222503",
        "jenisKelamin": "Laki-Laki",
        "tempatLahir": "Surabaya",
        "tanggalLahir": "2007-09-05",
        "tanggalMasuk": "2022-07-15",
        "status": "Aktif"
      },
      {
        "idSiswa": 9,
        "namaLengkap": "Dewi Kusuma",
        "nisn": "990615222504",
        "jenisKelamin": "Perempuan",
        "tempatLahir": "Yogya",
        "tanggalLahir": "2007-12-18",
        "tanggalMasuk": "2022-07-15",
        "status": "Aktif"
      },
      {
        "idSiswa": 11,
        "namaLengkap": "Ahmad Fauzi 1781687411",
        "nisn": "1781687411",
        "jenisKelamin": "Laki-Laki",
        "tempatLahir": "Surabaya",
        "tanggalLahir": "2007-06-20",
        "tanggalMasuk": "2023-07-10",
        "status": "Aktif"
      }
    ]
  }
}
```

### `GET /akademik/siswa/1/kelas?tahun_ajaran=2024%2F2025&semester=2`

Kelas aktif siswa id=1.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Riwayat kelas siswa id:1.",
  "data": [
    {
      "idSiswaKelas": 4,
      "siswaId": 1,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "namaKelas": "X MIPA 1"
    }
  ]
}
```

### `GET /akademik/kelas/1/siswa?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Daftar siswa di kelas id:1.",
  "data": [
    {
      "idSiswaKelas": 4,
      "siswaId": 1,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "namaLengkap": "Andi Wijaya"
    }
  ]
}
```

### `GET /akademik/siswa/1/kelas/riwayat`

Riwayat lengkap: `deletedAt` terisi jika pernah dipindah/dibatalkan. TANPA `page`/`per_page` bentuknya array datar seperti ini (perilaku lama, tetap didukung).

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Riwayat lengkap kelas siswa id:1.",
  "data": [
    {
      "idSiswaKelas": 4,
      "siswaId": 1,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "namaKelas": "X MIPA 1"
    },
    {
      "idSiswaKelas": 1,
      "siswaId": 1,
      "kelasId": 2,
      "tahunAjaran": "2024\/2025",
      "semester": "1",
      "namaKelas": "X IPS 2"
    }
  ]
}
```

### `GET /akademik/siswa/1/kelas/riwayat?per_page=2&page=1`

Endpoint riwayat yang SAMA dengan `per_page`: `data` berubah menjadi envelope paginasi identik dengan `GET /guru/all` (`data`, `current_page`, `last_page`, `per_page`, `total`, `from`, `to`, `links`). Bentuk tiap ITEM tidak berubah. Berlaku juga untuk `kelas/{id}/siswa/riwayat`, `guru/{id}/mapel/riwayat`, dan `mapel/{id}/guru/riwayat`. Default `per_page`=25, maksimum 100 (di luar itu 422). `next_page_url` sengaja tidak dikirim -- URL-nya menunjuk host service internal, bukan Gateway.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Riwayat lengkap kelas siswa id:1.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "idSiswaKelas": 4,
        "siswaId": 1,
        "kelasId": 1,
        "tahunAjaran": "2024\/2025",
        "semester": "2",
        "namaKelas": "X MIPA 1"
      },
      {
        "idSiswaKelas": 1,
        "siswaId": 1,
        "kelasId": 2,
        "tahunAjaran": "2024\/2025",
        "semester": "1",
        "namaKelas": "X IPS 2"
      }
    ],
    "from": 1,
    "last_page": 1,
    "links": [
      {
        "query": "per_page=2&page=1",
        "label": "1",
        "page": 1,
        "active": true
      }
    ],
    "per_page": 2,
    "to": 2,
    "total": 2
  }
}
```

### `POST /akademik/kelas/assign`

Request akademik memakai snake_case; response camelCase.

Request body:
```json
{
  "kelas_id": 129,
  "semester": 2,
  "siswa_id": 8,
  "tahun_ajaran": "2024/2025"
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "Siswa berhasil ditambahkan ke kelas.",
  "data": {
    "idSiswaKelas": 9,
    "siswaId": 8,
    "kelasId": 129,
    "tahunAjaran": "2024\/2025",
    "semester": "2"
  }
}
```

---

## Akademik - Pengampu Mapel

### `GET /akademik/guru/1/mapel?tahun_ajaran=2024%2F2025&semester=2`

Mapel aktif yang diampu guru id=1. Hanya berisi ID relasi -- nama mapel/kelas di-resolve dari master data.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Daftar mapel yang diampu guru id:1.",
  "data": [
    {
      "idPengampuMapel": 4,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "namaGuru": "Andi Susanto",
      "namaMapel": "Matematika Lanjutan 1781687275",
      "namaKelas": "X MIPA 1"
    },
    {
      "idPengampuMapel": 6,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 2,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "namaGuru": "Andi Susanto",
      "namaMapel": "Matematika Lanjutan 1781687275",
      "namaKelas": "X IPS 2"
    },
    {
      "idPengampuMapel": 13,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 7,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "namaGuru": "Andi Susanto",
      "namaMapel": "Matematika Lanjutan 1781687275",
      "namaKelas": "X IPS 4"
    }
  ]
}
```

### `GET /akademik/kelas/1/pengampu?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Daftar pengampu kelas id:1.",
  "data": [
    {
      "idPengampuMapel": 4,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "namaGuru": "Andi Susanto",
      "namaMapel": "Matematika Lanjutan 1781687275",
      "namaKelas": "X MIPA 1"
    },
    {
      "idPengampuMapel": 5,
      "guruId": 2,
      "mapelId": 2,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "namaGuru": "Budi Hartono",
      "namaMapel": "Bahasa Indonesia",
      "namaKelas": "X MIPA 1"
    }
  ]
}
```

### `GET /akademik/mapel/1/guru?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Daftar pengampu mapel id:1.",
  "data": [
    {
      "idPengampuMapel": 4,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "namaGuru": "Andi Susanto",
      "namaMapel": "Matematika Lanjutan 1781687275",
      "namaKelas": "X MIPA 1"
    },
    {
      "idPengampuMapel": 6,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 2,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "namaGuru": "Andi Susanto",
      "namaMapel": "Matematika Lanjutan 1781687275",
      "namaKelas": "X IPS 2"
    },
    {
      "idPengampuMapel": 13,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 7,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "namaGuru": "Andi Susanto",
      "namaMapel": "Matematika Lanjutan 1781687275",
      "namaKelas": "X IPS 4"
    }
  ]
}
```

### `POST /akademik/pengampu`

Request body:
```json
{
  "guru_id": 1,
  "semester": 2,
  "tahun_ajaran": "2024/2025",
  "kelas_id": 129,
  "mapel_id": 1
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "Guru berhasil ditetapkan sebagai pengampu mapel.",
  "data": {
    "idPengampuMapel": 24,
    "guruId": 1,
    "mapelId": 1,
    "kelasId": 129,
    "tahunAjaran": "2024\/2025",
    "semester": 2
  }
}
```

---

## Akademik - Jadwal Pelajaran

### `GET /akademik/jadwal/kelas/1?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Jadwal kelas id:1.",
  "data": [
    {
      "idJadwal": 7,
      "pengampuMapelId": 4,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "hari": "Senin",
      "jamMulaiId": 1,
      "jamSelesaiId": 9,
      "keMulai": 1,
      "keSelesai": 3,
      "pukul": "07:00:00 - 09:15:00",
      "ruangan": "Kelas A",
      "namaGuru": "Andi Susanto",
      "namaMapel": "Matematika Lanjutan 1781687275",
      "namaKelas": "X MIPA 1"
    }
  ]
}
```

### `GET /akademik/jadwal/guru/1?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Jadwal guru id:1.",
  "data": [
    {
      "idJadwal": 7,
      "pengampuMapelId": 4,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "hari": "Senin",
      "jamMulaiId": 1,
      "jamSelesaiId": 9,
      "keMulai": 1,
      "keSelesai": 3,
      "pukul": "07:00:00 - 09:15:00",
      "ruangan": "Kelas A",
      "namaGuru": "Andi Susanto",
      "namaMapel": "Matematika Lanjutan 1781687275",
      "namaKelas": "X MIPA 1"
    }
  ]
}
```

### `GET /akademik/jadwal/siswa/1?tahun_ajaran=2024%2F2025&semester=2`

Jadwal siswa berdasarkan kelas yang diikutinya.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Jadwal siswa id:1.",
  "data": [
    {
      "idJadwal": 7,
      "pengampuMapelId": 4,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "hari": "Senin",
      "jamMulaiId": 1,
      "jamSelesaiId": 9,
      "keMulai": 1,
      "keSelesai": 3,
      "pukul": "07:00:00 - 09:15:00",
      "ruangan": "Kelas A",
      "namaGuru": "Andi Susanto",
      "namaMapel": "Matematika Lanjutan 1781687275",
      "namaKelas": "X MIPA 1"
    }
  ]
}
```

---

## Akademik - Pengaturan Nilai dan Nilai

### `GET /akademik/pengaturan-nilai`

SuperAdmin/Admin saja (termasuk GET).

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Daftar pengaturan nilai.",
  "data": [
    {
      "idPengaturan": 1,
      "tahunAjaran": "2024\/2025",
      "semester": 2,
      "bobotHarian": 40,
      "bobotUts": 30,
      "bobotUas": 30
    }
  ]
}
```

### `POST /akademik/nilai`

**Ulangan harian: maksimal 5 slot** (`nilai_harian_1`..`nilai_harian_5`). Slot kosong = null dan TIDAK ikut dihitung -- 3 terisi berarti dibagi 3, bukan 5. Perhatikan respons: `nilaiHarian` = rata-rata (read-only), `ulanganHarian` = array 5 slot, `jumlahUlangan` = yang terisi. `nilaiAkhir` null sampai rata-rata harian + UTS + UAS terisi.

Request body:
```json
{
  "pengampu_mapel_id": 24,
  "siswa_kelas_id": 9,
  "nilai_harian_2": 80,
  "nilai_harian_1": 90,
  "nilai_harian_3": 70
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "Nilai berhasil disimpan.",
  "data": {
    "idNilai": 27,
    "siswaKelasId": 9,
    "siswaId": 8,
    "pengampuMapelId": 24,
    "guruId": 1,
    "mapelId": 1,
    "kelasId": 129,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "nilaiHarian": 80,
    "ulanganHarian": [
      90,
      80,
      70,
      null,
      null
    ],
    "jumlahUlangan": 3
  }
}
```

### `PATCH /akademik/nilai/27`

Setelah semua komponen terisi, `nilaiAkhir` dihitung otomatis dari bobot semester.

Request body:
```json
{
  "nilai_uts": 78,
  "nilai_uas": 90
}
```

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Nilai berhasil diperbarui.",
  "data": {
    "idNilai": 27,
    "siswaKelasId": 9,
    "siswaId": 8,
    "pengampuMapelId": 24,
    "guruId": 1,
    "mapelId": 1,
    "kelasId": 129,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "nilaiHarian": 80,
    "ulanganHarian": [
      90,
      80,
      70,
      null,
      null
    ],
    "jumlahUlangan": 3,
    "nilaiUts": 78,
    "nilaiUas": 90,
    "nilaiAkhir": 82.4
  }
}
```

### `PATCH /akademik/nilai/27`

MENGOSONGKAN satu ulangan: kirim `null` secara EKSPLISIT (menghilangkan field = tidak berubah). Penyebut rata-rata otomatis menyesuaikan -- lihat `jumlahUlangan` turun.

Request body:
```json
{
  "nilai_harian_2": null
}
```

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Nilai berhasil diperbarui.",
  "data": {
    "idNilai": 27,
    "siswaKelasId": 9,
    "siswaId": 8,
    "pengampuMapelId": 24,
    "guruId": 1,
    "mapelId": 1,
    "kelasId": 129,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "nilaiHarian": 80,
    "ulanganHarian": [
      90,
      null,
      70,
      null,
      null
    ],
    "jumlahUlangan": 2,
    "nilaiUts": 78,
    "nilaiUas": 90,
    "nilaiAkhir": 82.4
  }
}
```

### `GET /akademik/nilai/pengampu/24?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Nilai pengampu id:24.",
  "data": [
    {
      "idNilai": 27,
      "siswaKelasId": 9,
      "siswaId": 8,
      "pengampuMapelId": 24,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 129,
      "tahunAjaran": "2024\/2025",
      "semester": 2,
      "nilaiHarian": 80,
      "ulanganHarian": [
        90,
        null,
        70,
        null,
        null
      ],
      "jumlahUlangan": 2,
      "nilaiUts": 78,
      "nilaiUas": 90,
      "nilaiAkhir": 82.4
    }
  ]
}
```

### `GET /akademik/nilai/siswa/8?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Nilai siswa id:8.",
  "data": [
    {
      "idNilai": 27,
      "siswaKelasId": 9,
      "siswaId": 8,
      "pengampuMapelId": 24,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 129,
      "tahunAjaran": "2024\/2025",
      "semester": 2,
      "nilaiHarian": 80,
      "ulanganHarian": [
        90,
        null,
        70,
        null,
        null
      ],
      "jumlahUlangan": 2,
      "nilaiUts": 78,
      "nilaiUas": 90,
      "nilaiAkhir": 82.4
    }
  ]
}
```

---

## Akademik - Raport dan Ranking

### `GET /akademik/raport/siswa/8?tahun_ajaran=2024%2F2025&semester=2`

`tahun_ajaran` dan `semester` WAJIB di semua endpoint raport/ranking (422 jika kosong).

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Raport siswa id:8.",
  "data": {
    "siswaId": 8,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "bobot": {
      "bobotHarian": 40,
      "bobotUts": 30,
      "bobotUas": 30
    },
    "nilai": [
      {
        "idNilai": 27,
        "pengampuMapelId": 24,
        "guruId": 1,
        "mapelId": 1,
        "nilaiHarian": 80,
        "ulanganHarian": [
          90,
          null,
          70,
          null,
          null
        ],
        "nilaiUts": 78,
        "nilaiUas": 90,
        "nilaiAkhir": 82.4
      }
    ],
    "rataRata": 82.4
  }
}
```

### `GET /akademik/raport/kelas/129?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Raport kelas id:129.",
  "data": {
    "kelasId": 129,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "bobot": {
      "bobotHarian": 40,
      "bobotUts": 30,
      "bobotUas": 30
    },
    "siswa": [
      {
        "siswaId": 8,
        "siswaKelasId": 9,
        "nilai": [
          {
            "pengampuMapelId": 24,
            "mapelId": 1,
            "nilaiHarian": 80,
            "ulanganHarian": [
              90,
              null,
              70,
              null,
              null
            ],
            "nilaiUts": 78,
            "nilaiUas": 90,
            "nilaiAkhir": 82.4
          }
        ],
        "rataRata": 82.4
      }
    ]
  }
}
```

### `GET /akademik/nilai/ranking/kelas/129?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Peringkat kelas id:129.",
  "data": {
    "kelasId": 129,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "totalSiswa": 1,
    "ranking": [
      {
        "peringkat": 1,
        "siswaId": 8,
        "rataRata": 82.4
      }
    ]
  }
}
```

---

## Periode Khusus dan Pengaturan Absensi

### `POST /akademik/periode`

Periode khusus mengubah aturan sementara lalu otomatis kembali normal. jenis: ramadan|ujian|libur|khusus. kbm_normal default false untuk ujian & libur.

Request body:
```json
{
  "tahun_ajaran": "2024/2025",
  "berlaku_sampai": "2030-02-28",
  "jenis": "ramadan",
  "berlaku_dari": "2030-02-01",
  "nama": "Ramadan (contoh)",
  "semester": 2
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "Periode khusus berhasil dibuat.",
  "data": {
    "idPeriode": 120,
    "nama": "Ramadan (contoh)",
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "jenis": "ramadan",
    "berlakuDari": "2030-02-01",
    "berlakuSampai": "2030-02-28",
    "kbmNormal": true,
    "keterangan": null
  }
}
```

### `POST /akademik/periode`

Libur 1 hari: berlaku_dari = berlaku_sampai. Perhatikan `kbmNormal:false` otomatis. Auto-alpa melewati tanggal ini.

Request body:
```json
{
  "tahun_ajaran": "2024/2025",
  "berlaku_sampai": "2030-02-10",
  "jenis": "libur",
  "berlaku_dari": "2030-02-10",
  "nama": "Libur 1 hari (contoh)",
  "semester": 2
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "Periode khusus berhasil dibuat.",
  "data": {
    "idPeriode": 121,
    "nama": "Libur 1 hari (contoh)",
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "jenis": "libur",
    "berlakuDari": "2030-02-10",
    "berlakuSampai": "2030-02-10",
    "kbmNormal": false,
    "keterangan": null
  }
}
```

### `GET /akademik/periode/aktif?tanggal=2030-02-10`

RESOLUSI: kalau beberapa periode bertumpuk, rentang TERPENDEK menang -> libur 1 hari mengalahkan Ramadan.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Periode berlaku pada 2030-02-10.",
  "data": {
    "idPeriode": 121,
    "nama": "Libur 1 hari (contoh)",
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "jenis": "libur",
    "berlakuDari": "2030-02-10",
    "berlakuSampai": "2030-02-10",
    "kbmNormal": false,
    "keterangan": null
  }
}
```

### `GET /akademik/periode/aktif?tanggal=2030-02-05`

Tanggal Ramadan biasa.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Periode berlaku pada 2030-02-05.",
  "data": {
    "idPeriode": 120,
    "nama": "Ramadan (contoh)",
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "jenis": "ramadan",
    "berlakuDari": "2030-02-01",
    "berlakuSampai": "2030-02-28",
    "kbmNormal": true,
    "keterangan": null
  }
}
```

### `GET /akademik/periode`

Daftar periode.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Daftar periode khusus.",
  "data": [
    {
      "idPeriode": 20,
      "nama": "UTS",
      "tahunAjaran": "2024\/2025",
      "semester": 2,
      "jenis": "ujian",
      "berlakuDari": "2026-07-16",
      "berlakuSampai": "2026-07-16",
      "kbmNormal": false,
      "keterangan": null
    },
    {
      "idPeriode": 47,
      "nama": "test",
      "tahunAjaran": "2024\/2025",
      "semester": 2,
      "jenis": "ramadan",
      "berlakuDari": "2026-08-09",
      "berlakuSampai": "2026-08-10",
      "kbmNormal": true,
      "keterangan": null
    },
    {
      "idPeriode": 23,
      "nama": "Test Libur 075649",
      "tahunAjaran": "2024\/2025",
      "semester": 2,
      "jenis": "libur",
      "berlakuDari": "2026-08-29",
      "berlakuSampai": "2026-08-30",
      "kbmNormal": false,
      "keterangan": null
    },
    {
      "idPeriode": 22,
      "nama": "Test Ramadan 075649",
      "tahunAjaran": "2024\/2025",
      "semester": 2,
      "jenis": "ramadan",
      "berlakuDari": "2030-02-01",
      "berlakuSampai": "2030-02-28",
      "kbmNormal": true,
      "keterangan": null
    },
    {
      "idPeriode": 120,
      "nama": "Ramadan (contoh)",
      "tahunAjaran": "2024\/2025",
      "semester": 2,
      "jenis": "ramadan",
      "berlakuDari": "2030-02-01",
      "berlakuSampai": "2030-02-28",
      "kbmNormal": true,
      "keterangan": null
    },
    {
      "idPeriode": 121,
      "nama": "Libur 1 hari (contoh)",
      "tahunAjaran": "2024\/2025",
      "semester": 2,
      "jenis": "libur",
      "berlakuDari": "2030-02-10",
      "berlakuSampai": "2030-02-10",
      "kbmNormal": false,
      "keterangan": null
    }
  ]
}
```

### `POST /akademik/jam`

Set jam milik periode (menggantikan set normal). `hari` opsional: terisi = khusus hari itu (mis. Jumat) dan menang atas baris semua-hari.

Request body:
```json
{
  "jam_mulai": "07:30",
  "periode_id": 120,
  "jam_selesai": "08:00",
  "ke": 1
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "Jam ke-1 berhasil ditambahkan (periode id:120, semua hari).",
  "data": {
    "idJam": 83,
    "periodeId": 120,
    "hari": null,
    "ke": 1,
    "jamMulai": "07:30",
    "jamSelesai": "08:00"
  }
}
```

### `GET /akademik/jam?tanggal=2030-02-05&hari=Senin`

JAM EFEKTIF pada tanggal: sudah ikut periode + hari. Jadwal TIDAK diduplikasi saat Ramadan -- jam dinding di-resolve per (periode, hari, slot ke).

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Jam efektif 2030-02-05 (Senin).",
  "data": {
    "tanggal": "2030-02-05",
    "hari": "Senin",
    "periode": {
      "idPeriode": 120,
      "nama": "Ramadan (contoh)",
      "jenis": "ramadan"
    },
    "jam": [
      {
        "idJam": 83,
        "periodeId": 120,
        "hari": null,
        "ke": 1,
        "jamMulai": "07:30:00",
        "jamSelesai": "08:00:00"
      }
    ]
  }
}
```

### `POST /akademik/jam`

Duplikat slot (periode_id+hari+ke sama) -> 409 Conflict (BUKAN 422).

Request body:
```json
{
  "jam_mulai": "07:00",
  "ke": 1,
  "jam_selesai": "07:45"
}
```

Response (HTTP 409):
```json
{
  "resCode": 409,
  "resPhrase": "Conflict",
  "resStatus": "fail",
  "resMsg": "Jam ke-1 sudah terdaftar untuk set normal, semua hari.",
  "data": []
}
```

### `GET /akademik/jadwal/guru/1?tanggal=2030-02-05`

`pukul` mengikuti tanggal: pada periode Ramadan menampilkan jam Ramadan. Bandingkan dgn GET jadwal/guru/1 tanpa tanggal (bagian Jadwal di atas) yang memakai jam normal. Slot yg tidak diset di periode -> `pukul` tidak muncul (jadwal itu ditiadakan pada tanggal tsb).

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Jadwal guru id:1.",
  "data": [
    {
      "idJadwal": 7,
      "pengampuMapelId": 4,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "hari": "Senin",
      "jamMulaiId": 1,
      "jamSelesaiId": 9,
      "keMulai": 1,
      "keSelesai": 3,
      "pukul": "07:30:00 - 09:00:00",
      "ruangan": "Kelas A",
      "namaGuru": "Andi Susanto",
      "namaMapel": "Matematika Lanjutan 1781687275",
      "namaKelas": "X MIPA 1"
    }
  ]
}
```

### `POST /akademik/pengaturan-absensi`

periode_id terisi = override selama periode itu; tanpa periode_id = default semester. Duplikat kombinasi -> 409.

Request body:
```json
{
  "semester": 2,
  "periode_id": 120,
  "batas_terlambat_siswa": "08:00",
  "tahun_ajaran": "2024/2025",
  "batas_terlambat_pegawai": "08:00"
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "Pengaturan absensi berhasil dibuat.",
  "data": {
    "idPengaturanAbsensi": 56,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "periodeId": 120,
    "periodeNama": "Ramadan (contoh)",
    "lingkup": "periode",
    "jamMasukSekolah": "07:00:00",
    "batasTerlambatSiswa": "08:00:00",
    "jamMasukPegawai": "07:00:00",
    "batasTerlambatPegawai": "08:00:00",
    "durasiPinWindowMenit": 10
  }
}
```

### `GET /akademik/pengaturan-absensi/efektif?tanggal=2030-02-05`

Aturan yang BENAR-BENAR berlaku pada tanggal. `sumber`: periode | default_semester | default_sistem (fallback bawaan 07:20).

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Pengaturan absensi berlaku pada 2030-02-05.",
  "data": {
    "tanggal": "2030-02-05",
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "periode": {
      "idPeriode": 120,
      "nama": "Ramadan (contoh)",
      "jenis": "ramadan",
      "kbmNormal": true
    },
    "sumber": "periode",
    "pengaturan": {
      "jamMasukSekolah": "07:00:00",
      "batasTerlambatSiswa": "08:00:00",
      "jamMasukPegawai": "07:00:00",
      "batasTerlambatPegawai": "08:00:00",
      "durasiPinWindowMenit": 10
    }
  }
}
```

### `GET /akademik/pengaturan-absensi/efektif`

Tanpa tanggal = hari ini. Belum ada baris apa pun -> sumber=default_sistem.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Pengaturan absensi berlaku pada 2026-09-19.",
  "data": {
    "tanggal": "2026-09-19",
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "periode": null,
    "sumber": "default_sistem",
    "pengaturan": {
      "jamMasukSekolah": "07:00:00",
      "batasTerlambatSiswa": "07:20:00",
      "jamMasukPegawai": "07:00:00",
      "batasTerlambatPegawai": "07:20:00",
      "durasiPinWindowMenit": 10
    }
  }
}
```

### `DELETE /akademik/pengaturan-absensi/56`

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Pengaturan absensi berhasil dihapus.",
  "data": []
}
```

### `DELETE /akademik/periode/120`

Soft delete.

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Periode khusus berhasil dihapus.",
  "data": []
}
```

---

## Absensi (Kartu, Keluar, Rekap, PIN, Terminal)

### `POST /siswa/kartu/terbitkan`

Terbitkan/ganti kartu siswa. `kartuUid` opaque (bukan NISN). Terbit-ulang menimpa UID lama.

Request body:
```json
{
  "idSiswa": 1
}
```

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Kartu diterbitkan.",
  "data": {
    "idSiswa": 1,
    "kartuUid": "SIS-LWIFIQMT8MCY",
    "kartuStatus": "aktif",
    "kartuDiterbitkanAt": "2026-09-19T09:40:47.000000Z"
  }
}
```

### `POST /siswa/kartu/blokir`

Blokir kartu (`hilang`/`blokir`) -> scan ditolak.

Request body:
```json
{
  "idSiswa": 1,
  "status": "hilang"
}
```

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Kartu diblokir (hilang).",
  "data": {
    "idSiswa": 1,
    "kartuStatus": "hilang"
  }
}
```

### `POST /guru/kartu/terbitkan`

Kartu guru (prefix `GUR-`); karyawan `KAR-` serupa via /karyawan/kartu/terbitkan.

Request body:
```json
{
  "idGuru": 1
}
```

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Kartu diterbitkan.",
  "data": {
    "idGuru": 1,
    "kartuUid": "GUR-OQQF9KJMOPDF",
    "kartuStatus": "aktif",
    "kartuDiterbitkanAt": "2026-09-19T09:40:48.000000Z"
  }
}
```

### `GET /kartu/qr?data=<uid>`

Mengembalikan **image/svg+xml** (bukan JSON envelope) - QR siap cetak ke kartu fisik. Role SuperAdmin/Admin. Tidak di-capture di sini karena respons berupa SVG.

### `POST /akademik/absensi/keluar`

`jenis`: pulang_awal/izin_kegiatan/lomba/pulang_sakit. `disetujuiOleh` = id user penyetuju (diinject Gateway).

Request body:
```json
{
  "jenis": "pulang_awal",
  "siswa_id": 1,
  "keterangan": "dijemput orang tua"
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "Izin keluar tercatat.",
  "data": {
    "idKeluar": 56,
    "siswaId": 1,
    "tanggal": "2026-09-19",
    "jamKeluar": "2026-09-19 16:40:49",
    "jenis": "pulang_awal",
    "keterangan": "dijemput orang tua",
    "disetujuiOleh": 1,
    "terminalId": null
  }
}
```

### `GET /akademik/absensi/keluar`

Daftar izin keluar (default hari ini WIB; filter `tanggal`/`siswa_id`).

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Daftar izin keluar tanggal 2026-09-19.",
  "data": [
    {
      "idKeluar": 48,
      "siswaId": 1,
      "tanggal": "2026-09-19",
      "jamKeluar": "2026-09-19 13:56:59",
      "jenis": "pulang_awal",
      "keterangan": "test 135115",
      "disetujuiOleh": 1,
      "terminalId": null
    },
    {
      "idKeluar": 49,
      "siswaId": 1,
      "tanggal": "2026-09-19",
      "jamKeluar": "2026-09-19 14:08:29",
      "jenis": "pulang_awal",
      "keterangan": "test 140332",
      "disetujuiOleh": 1,
      "terminalId": null
    },
    {
      "idKeluar": 50,
      "siswaId": 1,
      "tanggal": "2026-09-19",
      "jamKeluar": "2026-09-19 14:15:27",
      "jenis": "pulang_awal",
      "keterangan": "test 141031",
      "disetujuiOleh": 1,
      "terminalId": null
    },
    {
      "idKeluar": 51,
      "siswaId": 1,
      "tanggal": "2026-09-19",
      "jamKeluar": "2026-09-19 15:25:32",
      "jenis": "pulang_awal",
      "keterangan": "test 152037",
      "disetujuiOleh": 1,
      "terminalId": null
    },
    {
      "idKeluar": 52,
      "siswaId": 1,
      "tanggal": "2026-09-19",
      "jamKeluar": "2026-09-19 15:28:08",
      "jenis": "pulang_awal",
      "keterangan": "dijemput orang tua",
      "disetujuiOleh": 1,
      "terminalId": null
    },
    {
      "idKeluar": 53,
      "siswaId": 1,
      "tanggal": "2026-09-19",
      "jamKeluar": "2026-09-19 16:03:02",
      "jenis": "pulang_awal",
      "keterangan": "test 155801",
      "disetujuiOleh": 1,
      "terminalId": null
    },
    {
      "idKeluar": 54,
      "siswaId": 1,
      "tanggal": "2026-09-19",
      "jamKeluar": "2026-09-19 16:05:42",
      "jenis": "pulang_awal",
      "keterangan": "dijemput orang tua",
      "disetujuiOleh": 1,
      "terminalId": null
    },
    {
      "idKeluar": 55,
      "siswaId": 1,
      "tanggal": "2026-09-19",
      "jamKeluar": "2026-09-19 16:38:01",
      "jenis": "pulang_awal",
      "keterangan": "test 163305",
      "disetujuiOleh": 1,
      "terminalId": null
    },
    {
      "idKeluar": 56,
      "siswaId": 1,
      "tanggal": "2026-09-19",
      "jamKeluar": "2026-09-19 16:40:49",
      "jenis": "pulang_awal",
      "keterangan": "dijemput orang tua",
      "disetujuiOleh": 1,
      "terminalId": null
    }
  ]
}
```

### `GET /akademik/absensi/rekap/harian/kelas/1`

Per siswa: hadir/terlambat/izin/sakit/alpa + total. `namaLengkap` disertakan. Override rentang: `tanggal_dari`/`tanggal_sampai`.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Rekap harian kelas id:1.",
  "data": {
    "kelasId": 1,
    "tahunAjaran": "2024\/2025",
    "semester": "2",
    "dari": "2026-09-01",
    "sampai": "2026-09-19",
    "siswa": [
      {
        "siswaId": 1,
        "hadir": 0,
        "terlambat": 2,
        "izin": 0,
        "sakit": 0,
        "alpa": 0,
        "total": 2,
        "namaLengkap": "Andi Wijaya"
      }
    ]
  }
}
```

### `GET /akademik/absensi/rekap/harian/siswa/1`

Ringkasan + detail per hari.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Rekap harian siswa id:1.",
  "data": {
    "siswaId": 1,
    "dari": "2026-09-01",
    "sampai": "2026-09-19",
    "ringkasan": {
      "hadir": 0,
      "terlambat": 2,
      "izin": 0,
      "sakit": 0,
      "alpa": 0,
      "total": 2
    },
    "detail": [
      {
        "tanggal": "2026-09-05",
        "jamMasuk": "2026-09-05 14:14:09",
        "status": "terlambat",
        "metode": "scan"
      },
      {
        "tanggal": "2026-09-19",
        "jamMasuk": "2026-09-19 13:57:06",
        "status": "terlambat",
        "metode": "scan"
      }
    ]
  }
}
```

### `GET /akademik/absensi/rekap/pelajaran/siswa/1`

Ringkasan absensi per pelajaran 1 siswa.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Rekap pelajaran siswa id:1.",
  "data": {
    "siswaId": 1,
    "dari": "2026-09-01",
    "sampai": "2026-09-19",
    "ringkasan": {
      "hadir": 0,
      "izin": 0,
      "sakit": 0,
      "alpa": 0,
      "total": 0
    }
  }
}
```

### `GET /akademik/absensi/rekap/pegawai/guru/1`

Rekap pegawai; path `/rekap/pegawai/{guru|karyawan}/{id}`.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Rekap pegawai guru id:1.",
  "data": {
    "subjekTipe": "guru",
    "subjekId": 1,
    "dari": "2026-09-01",
    "sampai": "2026-09-19",
    "ringkasan": {
      "hadir": 0,
      "terlambat": 2,
      "izin": 0,
      "sakit": 0,
      "dinas_luar": 0,
      "alpa": 0,
      "total": 2
    },
    "detail": [
      {
        "tanggal": "2026-09-05",
        "jamMasuk": "2026-09-05 14:14:09",
        "jamPulang": null,
        "status": "terlambat",
        "metode": "scan"
      },
      {
        "tanggal": "2026-09-19",
        "jamMasuk": "2026-09-19 13:57:09",
        "jamPulang": null,
        "status": "terlambat",
        "metode": "pin"
      }
    ]
  }
}
```

### `POST /absensi/pin/buka`

Admin membuka jendela PIN untuk pegawai (time-boxed, sekali pakai).

Request body:
```json
{
  "subjek_tipe": "guru",
  "durasi_menit": 10,
  "subjek_id": 1
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "Jendela PIN dibuka.",
  "data": {
    "idPinWindow": 123,
    "subjekTipe": "guru",
    "subjekId": 1,
    "berlakuSampai": "2026-09-19 16:50:52",
    "durasiMenit": 10
  }
}
```

### `POST /akademik/wali`

Tetapkan wali kelas (satu wali per kelas/semester; 409 jika sudah ada).

Request body:
```json
{
  "guru_id": 1,
  "semester": 2,
  "kelas_id": 1,
  "tahun_ajaran": "2024/2025"
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "Wali kelas berhasil ditetapkan.",
  "data": {
    "idWaliKelas": 2,
    "guruId": 1,
    "kelasId": 1,
    "tahunAjaran": "2024\/2025",
    "semester": "2"
  }
}
```

### `GET /akademik/kelas/1/wali`

Wali aktif satu kelas.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Wali kelas id:1.",
  "data": [
    {
      "idWaliKelas": 2,
      "guruId": 1,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2"
    }
  ]
}
```

### `DELETE /akademik/wali/2`

Batalkan penugasan wali (soft delete).

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Penugasan wali kelas berhasil dihapus.",
  "data": []
}
```

### `POST /absensi/scan`

Scan & absen PIN butuh autentikasi **terminal** (header X-Terminal-Id/Token), bukan Bearer. Tanpa header terminal -> 401.

Request body:
```json
{
  "kartu_uid": "SIS-XXXX"
}
```

Response (HTTP 401):
```json
{
  "resCode": 401,
  "resPhrase": "Unauthorized",
  "resStatus": "fail",
  "resMsg": "Terminal tidak terautentikasi.",
  "data": []
}
```

---

## Endpoint /saya, Laporan Angkatan & Ulangan Harian

### `GET /akademik/absensi/rekap/pegawai/saya`

Rekap absensi DIRI SENDIRI untuk pegawai (guru/karyawan). Subjek diresolve dari email token; bentuknya identik dengan `rekap/pegawai/{tipe}/{id}` sehingga DTO-nya sama. 404 bila akun bukan pegawai.

Response (HTTP 404):
```json
{
  "resCode": 404,
  "resPhrase": "Not Found",
  "resStatus": "fail",
  "resMsg": "Akun ini tidak terhubung ke data guru maupun karyawan.",
  "data": []
}
```

### `GET /akademik/nilai/ranking/angkatan?tingkat=1&tahun_ajaran=2024%2F2025&semester=2`

Peringkat SE-ANGKATAN. `peringkat` bisa kembar dan MELOMPAT (1,2,2,4,5) -- jangan pakai indeks baris. Seri diurutkan alfabet oleh server; jangan di-sort ulang. Mapel belum dinilai dihitung 0 (lihat `belumDinilai`/`jumlahMapel`). Siswa non-aktif sudah dibuang server.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Peringkat se-angkatan.",
  "data": {
    "tingkat": 1,
    "jurusan": null,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "rataRataAngkatan": 61.25,
    "totalSiswa": 4,
    "ranking": [
      {
        "peringkat": 1,
        "siswaId": 4,
        "namaLengkap": "Dedi Pratama",
        "nisn": "0123456784",
        "kelas": "X IPS 4",
        "rataRata": 89.25,
        "predikat": "B",
        "jumlahMapel": 2,
        "belumDinilai": 0
      },
      {
        "peringkat": 2,
        "siswaId": 7,
        "namaLengkap": "Bella Sari",
        "nisn": "990615222502",
        "kelas": "X IPS 2",
        "rataRata": 80.17,
        "predikat": "B",
        "jumlahMapel": 1,
        "belumDinilai": 0
      },
      {
        "peringkat": 3,
        "siswaId": 6,
        "namaLengkap": "Andi Pratama",
        "nisn": "990615222501",
        "kelas": "X IPS 4",
        "rataRata": 75.57,
        "predikat": "C",
        "jumlahMapel": 2,
        "belumDinilai": 0
      },
      {
        "peringkat": 4,
        "siswaId": 1,
        "namaLengkap": "Andi Wijaya",
        "nisn": "0123456781",
        "kelas": "X MIPA 1",
        "rataRata": 0,
        "predikat": "E",
        "jumlahMapel": 2,
        "belumDinilai": 2
      }
    ]
  }
}
```

### `GET /akademik/nilai/ranking/angkatan?tingkat=1&tahun_ajaran=2024%2F2025&semester=2&detail=1`

`detail=1` menambah array `nilai` per siswa (per mapel + predikat). Field `nilai` TIDAK ada saat detail=0 -> jadikan opsional di DTO.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Peringkat se-angkatan.",
  "data": {
    "tingkat": 1,
    "jurusan": null,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "rataRataAngkatan": 61.25,
    "totalSiswa": 4,
    "ranking": [
      {
        "peringkat": 1,
        "siswaId": 4,
        "namaLengkap": "Dedi Pratama",
        "nisn": "0123456784",
        "kelas": "X IPS 4",
        "rataRata": 89.25,
        "predikat": "B",
        "jumlahMapel": 2,
        "belumDinilai": 0,
        "nilai": [
          {
            "pengampuMapelId": 13,
            "mapelId": 1,
            "nilaiAkhir": 88.5,
            "dinilai": true,
            "predikat": "B"
          },
          {
            "pengampuMapelId": 14,
            "mapelId": 2,
            "nilaiAkhir": 90,
            "dinilai": true,
            "predikat": "A"
          }
        ]
      },
      {
        "peringkat": 2,
        "siswaId": 7,
        "namaLengkap": "Bella Sari",
        "nisn": "990615222502",
        "kelas": "X IPS 2",
        "rataRata": 80.17,
        "predikat": "B",
        "jumlahMapel": 1,
        "belumDinilai": 0,
        "nilai": [
          {
            "pengampuMapelId": 6,
            "mapelId": 1,
            "nilaiAkhir": 80.17,
            "dinilai": true,
            "predikat": "B"
          }
        ]
      },
      {
        "peringkat": 3,
        "siswaId": 6,
        "namaLengkap": "Andi Pratama",
        "nisn": "990615222501",
        "kelas": "X IPS 4",
        "rataRata": 75.57,
        "predikat": "C",
        "jumlahMapel": 2,
        "belumDinilai": 0,
        "nilai": [
          {
            "pengampuMapelId": 13,
            "mapelId": 1,
            "nilaiAkhir": 73.13,
            "dinilai": true,
            "predikat": "C"
          },
          {
            "pengampuMapelId": 14,
            "mapelId": 2,
            "nilaiAkhir": 78,
            "dinilai": true,
            "predikat": "C"
          }
        ]
      },
      {
        "peringkat": 4,
        "siswaId": 1,
        "namaLengkap": "Andi Wijaya",
        "nisn": "0123456781",
        "kelas": "X MIPA 1",
        "rataRata": 0,
        "predikat": "E",
        "jumlahMapel": 2,
        "belumDinilai": 2,
        "nilai": [
          {
            "pengampuMapelId": 4,
            "mapelId": 1,
            "nilaiAkhir": 0,
            "dinilai": false,
            "predikat": "E"
          },
          {
            "pengampuMapelId": 5,
            "mapelId": 2,
            "nilaiAkhir": 0,
            "dinilai": false,
            "predikat": "E"
          }
        ]
      }
    ]
  }
}
```

### `GET /class/all?tingkat=1&jurusan=MIPA`

Filter kelas per angkatan untuk dropdown laporan (`tingkat` 1/2/3, `jurusan` opsional).

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "List Ruang Kelas.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "idKelas": 1,
        "namaKelas": "X MIPA 1",
        "tingkat": "1",
        "jurusan": "MIPA",
        "limitSiswa": "40"
      },
      {
        "idKelas": 6,
        "namaKelas": "X MIPA 4",
        "tingkat": "1",
        "jurusan": "MIPA",
        "limitSiswa": "40"
      },
      {
        "idKelas": 106,
        "namaKelas": "X MIPA 99",
        "tingkat": "1",
        "jurusan": "MIPA",
        "limitSiswa": "35"
      }
    ],
    "from": 1,
    "last_page": 1,
    "links": [
      {
        "query": "tingkat=1&jurusan=MIPA&page=1",
        "label": "1",
        "page": 1,
        "active": true
      }
    ],
    "per_page": 5,
    "to": 3,
    "total": 3
  }
}
```


**Ekspor laporan angkatan** `GET akademik/nilai/ranking/angkatan/export?...&format=csv|pdf` mengembalikan BERKAS, bukan JSON -- karena itu tidak dicontohkan di sini. Respons: `Content-Type: text/csv; charset=UTF-8` atau `application/pdf`, plus `Content-Disposition: attachment; filename=\

---

## Contoh Response DELETE (dari cleanup data sementara)

### `DELETE /akademik/nilai/27`

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Nilai berhasil dihapus.",
  "data": []
}
```

### `DELETE /akademik/pengampu/24`

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Penugasan pengampu mapel berhasil dihapus.",
  "data": []
}
```

### `DELETE /akademik/kelas/assign/9`

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Siswa berhasil dikeluarkan dari kelas.",
  "data": []
}
```

### `DELETE /class/129`

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Ruang kelas dengan id:129 berhasil dihapus.",
  "data": []
}
```

---

## Perilaku per Role (Guru / Siswa / Karyawan)

### `GET /guru?idGuru=1`

Detail guru DILIHAT NON-PENGELOLA (Guru, Siswa, karyawan biasa): field pribadi (nik, alamat, telephone, tanggalLahir, dll.) DISARING oleh Gateway -- field-nya TIDAK DIKIRIM, bukan dikirim kosong. Bandingkan dengan versi lengkap di bagian Guru. Buat semua field DTO detail nullable, kalau tidak parser akan melempar MissingFieldException dan layar jadi blank.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Guru dengan id:1.",
  "data": {
    "idGuru": 1,
    "email": "andi.susanto2@sekolah.com",
    "nip": "9876543210123401",
    "namaLengkap": "Andi Susanto",
    "foto": "data:image\/webp;base64,UklGRqpHAABXRUJQVlA4IJ5H...<dipotong>",
    "statusKepegawaian": "PNS",
    "jabatan": "Guru Senior",
    "pendidikanTerakhir": "S1"
  }
}
```

### `GET /siswa?idSiswa=4`

Detail siswa LAIN dilihat ROLE SISWA: 200 tapi hanya INFO PUBLIK (idSiswa, namaLengkap, jenisKelamin, status, foto). NISN, tempat/tanggal lahir, alamat, telepon, dan seluruh data orang tua TIDAK dikirim. Bandingkan dengan `GET /siswa/saya` di bawah yang tetap lengkap.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Siswa dengan id:4.",
  "data": {
    "idSiswa": 4,
    "namaLengkap": "Dedi Pratama",
    "jenisKelamin": "Laki-Laki",
    "status": "Aktif",
    "foto": "data:image\/webp;base64,UklGRi5TAABXRUJQVlA4ICJT...<dipotong>"
  }
}
```

### `GET /siswa/all?page=1&per_page=3`

Daftar siswa DILIHAT ROLE SISWA: tiap baris hanya idSiswa, namaLengkap, jenisKelamin, status. Meta paginasi TIDAK berubah, jadi parser daftar tetap satu untuk semua role.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "List Data Siswa.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "idSiswa": 1,
        "namaLengkap": "Andi Wijaya",
        "jenisKelamin": "Laki-Laki",
        "status": "Aktif"
      },
      {
        "idSiswa": 4,
        "namaLengkap": "Dedi Pratama",
        "jenisKelamin": "Laki-Laki",
        "status": "Aktif"
      },
      {
        "idSiswa": 6,
        "namaLengkap": "Andi Pratama",
        "jenisKelamin": "Laki-Laki",
        "status": "Aktif"
      }
    ],
    "from": 1,
    "last_page": 3,
    "links": [
      {
        "query": "per_page=3&search_publik=1&page=1",
        "label": "1",
        "page": 1,
        "active": true
      },
      {
        "query": "per_page=3&search_publik=1&page=2",
        "label": "2",
        "page": 2,
        "active": false
      },
      {
        "query": "per_page=3&search_publik=1&page=3",
        "label": "3",
        "page": 3,
        "active": false
      },
      {
        "query": "per_page=3&search_publik=1&page=2",
        "label": "Next &raquo;",
        "page": 2,
        "active": false
      }
    ],
    "per_page": 3,
    "to": 3,
    "total": 7
  }
}
```

### `GET /siswa/saya`

Profil DIRI SENDIRI untuk role Siswa -- LENGKAP, termasuk `foto` (data-URI base64). Tidak ikut disaring karena ini datanya sendiri; `GET /siswa?idSiswa=` untuk siswa lain hanya versi publik. idSiswa diresolve dari email token.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Siswa dengan id:1.",
  "data": {
    "idSiswa": 1,
    "email": "andi.siswa1@sekolah.com",
    "nisn": "0123456781",
    "namaLengkap": "Andi Wijaya",
    "telephone": "081234561",
    "jenisKelamin": "Laki-Laki",
    "status": "Aktif",
    "statusDate": null,
    "tempatLahir": "Bandung",
    "tanggalLahir": "2008-05-20",
    "agama": null,
    "tanggalMasuk": "2023-07-10",
    "alamat": "Jl. Baru No.1 Updated",
    "foto": "data:image\/webp;base64,UklGRpIvAABXRUJQVlA4IIYv...<dipotong>",
    "kartu_uid": "SIS-I1XUPIJAFXWL",
    "kartu_status": "aktif",
    "kartu_diterbitkan_at": "2026-09-19T09:40:48.000000Z",
    "namaAyah": null,
    "namaIbu": "Siti Aminah",
    "pekerjaanAyah": null,
    "pekerjaanIbu": null,
    "noTelpAyah": null,
    "noTelpIbu": null,
    "namaWali": null,
    "hubunganWali": null,
    "noTelpWali": null,
    "created_at": "2026-06-15T00:50:37.000000Z",
    "updated_at": "2026-09-19T09:40:48.000000Z",
    "deleted_at": null
  }
}
```

### `GET /akademik/nilai/saya?tahun_ajaran=2024%2F2025&semester=2`

Khusus role Siswa; siswa_id di-resolve server dari email token.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Nilai siswa id:1.",
  "data": []
}
```

### `GET /akademik/raport/saya?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Raport siswa id:1.",
  "data": {
    "siswaId": 1,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "bobot": {
      "bobotHarian": 40,
      "bobotUts": 30,
      "bobotUas": 30
    },
    "nilai": [],
    "rataRata": null
  }
}
```

### `GET /akademik/nilai/ranking/saya?tahun_ajaran=2024%2F2025&semester=2`

Hanya posisi diri sendiri -- tanpa daftar siswa lain.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Peringkat kelas id:1.",
  "data": {
    "kelasId": 1,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "totalSiswa": 2,
    "peringkat": null,
    "rataRata": null
  }
}
```

### `POST /akademik/nilai`

Role Siswa tidak boleh menulis nilai (403).

Request body:
```json
{
  "pengampu_mapel_id": 1,
  "siswa_kelas_id": 1,
  "nilai_harian": 80
}
```

Response (HTTP 403):
```json
{
  "resCode": 403,
  "resPhrase": "Forbidden",
  "resStatus": "fail",
  "resMsg": "You do not have permission to access this page.",
  "data": []
}
```

### `POST /class`

Karyawan read-only: POST ditolak 403.

Request body:
```json
{
  "limitSiswa": 30,
  "jurusan": "MIPA",
  "tingkat": 1,
  "noKelas": 1
}
```

Response (HTTP 403):
```json
{
  "resCode": 403,
  "resPhrase": "Forbidden",
  "resStatus": "fail",
  "resMsg": "You do not have permission to access this page.",
  "data": []
}
```

### `GET /guru?idGuru=1`

Detail guru DILIHAT KARYAWAN BIASA: sama tersaringnya dengan viewer Siswa. Sebelumnya role Karyawan menerima PII penuh -- itu sudah ditutup.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Guru dengan id:1.",
  "data": {
    "idGuru": 1,
    "email": "andi.susanto2@sekolah.com",
    "nip": "9876543210123401",
    "namaLengkap": "Andi Susanto",
    "foto": "data:image\/webp;base64,UklGRqpHAABXRUJQVlA4IJ5H...<dipotong>",
    "statusKepegawaian": "PNS",
    "jabatan": "Guru Senior",
    "pendidikanTerakhir": "S1"
  }
}
```

### `GET /akademik/nilai/kelas/1?tahun_ajaran=2024%2F2025&semester=2`

Karyawan biasa TIDAK boleh membaca data akademik siswa: 403. Berlaku juga untuk raport, ranking kelas, dan rekap absensi siswa. Administrator Sekolah tetap 200.

Response (HTTP 403):
```json
{
  "resCode": 403,
  "resPhrase": "Forbidden",
  "resStatus": "fail",
  "resMsg": "You do not have permission to access this page.",
  "data": []
}
```

### `GET /siswa/all?page=1&per_page=3`

Direktori siswa ditutup seluruhnya untuk karyawan biasa: 403, daftar maupun detail.

Response (HTTP 403):
```json
{
  "resCode": 403,
  "resPhrase": "Forbidden",
  "resStatus": "fail",
  "resMsg": "You do not have permission to access this page.",
  "data": []
}
```

### `GET /akademik/absensi/rekap/pegawai/saya`

Yang TETAP boleh karyawan biasa: rekap absensi DIRINYA SENDIRI. Subjek diresolve dari email token.

Response (HTTP 404):
```json
{
  "resCode": 404,
  "resPhrase": "Not Found",
  "resStatus": "fail",
  "resMsg": "Akun ini tidak terhubung ke data guru maupun karyawan.",
  "data": []
}
```


> **Catatan:** contoh di atas terekam **404**, bukan 200. Akun `akuntest.karyawan` punya user di Gateway tapi belum punya record di tabel `karyawans`, sehingga subjeknya tak bisa diresolve. Gate role-nya sendiri LOLOS (bukan 403) -- karyawan biasa memang berhak atas endpoint ini. **Menjalankan `seed-test-accounts.ps1` TIDAK memperbaikinya**: skrip itu sudah mencoba `POST /karyawan`, tapi ditolak 422 karena emailnya dipegang akun yang masih aktif (guard anti data-yatim). Pemulihan yang ada hanya menangani record **terhapus**, bukan \

### `POST /refresh`

Tukar token valid dengan token baru 8 jam; token lama dicabut; device_name diwarisi.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Token refreshed.",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI...<dipotong>",
    "user": "Akun Test Karyawan",
    "email": "akuntest.karyawan@example.com",
    "role": "Karyawan"
  }
}
```

### `POST /password`

Contoh 422: current_password salah.

Request body:
```json
{
  "current_password": "PasswordSalah999",
  "new_password": "BaruBanget123",
  "confirm_password": "BaruBanget123"
}
```

Response (HTTP 422):
```json
{
  "resCode": 422,
  "resPhrase": "Unprocessible Entity",
  "resStatus": "fail",
  "resMsg": "Password saat ini tidak sesuai.",
  "data": []
}
```

### `POST /logout`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Logged out.",
  "data": []
}
```

### `POST /login`

Login **Administrator Sekolah**. Perhatikan `role` tetap `Karyawan` sementara `isAdminSekolah` bernilai true -- INILAH penanda yang dipakai app untuk membuka menu manajemen. Jangan gating dengan role.

Request body:
```json
{
  "email": "akuntest.adminsekolah@example.com",
  "device_name": "android",
  "password": "AdminSekolahTest123"
}
```

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Access granted.",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI...<dipotong>",
    "user": "Akun Test Administrator Sekolah",
    "email": "akuntest.adminsekolah@example.com",
    "role": "Karyawan",
    "isAdminSekolah": true,
    "isPetugasAcara": false,
    "mustChangePassword": false
  }
}
```

### `GET /user`

`isAdminSekolah` juga tersedia di GET /user, jadi app tidak perlu menyimpan hasil login untuk menentukan menu.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Data user.",
  "data": {
    "id": 151,
    "name": "Akun Test Administrator Sekolah",
    "email": "akuntest.adminsekolah@example.com",
    "role": "Karyawan",
    "must_change_password": false,
    "email_verified_at": null,
    "created_at": "2026-08-28T08:12:26.000000Z",
    "updated_at": "2026-09-05T10:07:34.000000Z",
    "deleted_at": null,
    "isAdminSekolah": true,
    "isPetugasAcara": false
  }
}
```

### `POST /akademik/pengampu`

Administrator Sekolah BOLEH menulis data akademik: hasilnya 404 (mapel tidak ada), BUKAN 403 -- artinya pembatasan role sudah terlewat.

Request body:
```json
{
  "guru_id": 1,
  "semester": "2",
  "tahun_ajaran": "2024/2025",
  "kelas_id": 1,
  "mapel_id": 99999
}
```

Response (HTTP 404):
```json
{
  "resCode": 404,
  "resPhrase": "Not Found",
  "resStatus": "fail",
  "resMsg": "Mata pelajaran tidak ditemukan.",
  "data": []
}
```

### `POST /register`

Batas Administrator Sekolah: TIDAK boleh membuat akun Admin/SuperAdmin (422). Hanya Guru/Siswa/Karyawan.

Request body:
```json
{
  "email": "coba.admin.tu@example.com",
  "name": "Coba Admin",
  "password": "Rahasia123",
  "confirm_password": "Rahasia123",
  "role": "Admin"
}
```

Response (HTTP 422):
```json
{
  "resCode": 422,
  "resPhrase": "Unprocessible Entity",
  "resStatus": "fail",
  "resMsg": "Role tidak valid. Karyawan hanya boleh membuat: Guru, Siswa, Karyawan.",
  "data": [
    "Role tidak valid. Karyawan hanya boleh membuat: Guru, Siswa, Karyawan."
  ]
}
```

### `POST /akademik/pengampu`

Karyawan BIASA (bukan Administrator Sekolah) tetap 403 pada endpoint yang sama -- pembanding langsung untuk contoh di atas.

Request body:
```json
{
  "guru_id": 1,
  "semester": "2",
  "tahun_ajaran": "2024/2025",
  "kelas_id": 1,
  "mapel_id": 1
}
```

Response (HTTP 403):
```json
{
  "resCode": 403,
  "resPhrase": "Forbidden",
  "resStatus": "fail",
  "resMsg": "You do not have permission to access this page.",
  "data": []
}
```

---

## Acara (kalender bulanan)

### `GET /akademik/acara?dari=2035-01-01&sampai=2035-01-31`

Rentang tanpa acara membalas `data: []`, BUKAN 404 -- bulan kosong itu keadaan normal. Query memakai BERSINGGUNGAN, bukan termuat: acara 28 Jan-3 Feb muncul di query Januari MAUPUN Februari, jadi de-duplikasi pakai idAcara bila menggabungkan beberapa bulan.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Daftar acara.",
  "data": []
}
```

### `POST /akademik/acara`

Body snake_case, respons camelCase. `seharian=true` memaksa jamMulai/jamSelesai jadi null.

Request body:
```json
{
  "seharian": true,
  "kategori": "upacara",
  "tanggal_mulai": "2035-01-06",
  "tanggal_selesai": "2035-01-06",
  "judul": "Upacara Contoh 164156",
  "lokasi": "Lapangan"
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "Acara berhasil dibuat.",
  "data": {
    "idAcara": 108,
    "judul": "Upacara Contoh 164156",
    "kategori": "upacara",
    "tanggalMulai": "2035-01-06",
    "tanggalSelesai": "2035-01-06",
    "seharian": true,
    "jamMulai": null,
    "jamSelesai": null,
    "lokasi": "Lapangan",
    "keterangan": null
  }
}
```

### `PATCH /akademik/acara/108`

PATCH parsial. Konsistensi tanggal/jam diperiksa terhadap nilai GABUNGAN (dikirim + tersimpan), bukan hanya yang dikirim.

Request body:
```json
{
  "judul": "Upacara (revisi)"
}
```

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Acara berhasil diperbarui.",
  "data": {
    "idAcara": 108,
    "judul": "Upacara (revisi)",
    "kategori": "upacara",
    "tanggalMulai": "2035-01-06",
    "tanggalSelesai": "2035-01-06",
    "seharian": true,
    "jamMulai": null,
    "jamSelesai": null,
    "lokasi": "Lapangan",
    "keterangan": null
  }
}
```

### `POST /akademik/acara`

422: `seharian=false` mewajibkan jam_mulai + jam_selesai.

Request body:
```json
{
  "seharian": false,
  "kategori": "rapat",
  "tanggal_selesai": "2035-01-11",
  "judul": "Tanpa jam",
  "tanggal_mulai": "2035-01-11"
}
```

Response (HTTP 422):
```json
{
  "resCode": 422,
  "resPhrase": "Unprocessible Entity",
  "resStatus": "fail",
  "resMsg": "jam_mulai dan jam_selesai wajib diisi bila seharian = false.",
  "data": []
}
```


> **Anti-bentrok acara vs pekan ujian.** `POST`/`PATCH akademik/acara` menolak **422**
> bila rentangnya bersinggungan dengan periode `jenis = ujian`. Detail periode yang
> menghalangi ikut di `data.bentrok` (`idPeriode`, `nama`, `jenis`, `berlakuDari`,
> `berlakuSampai`) supaya app bisa menandai rentang terlarang di date-picker, bukan
> sekadar menampilkan pesan buntu. Tidak dicontohkan di sini karena butuh periode ujian
> aktif di database.
>
> `ramadan` sengaja TIDAK dilindungi -- sebulan penuh, sekolah tetap berjalan, jadi
> melindunginya akan memblokir seluruh agenda selama sebulan.

---

## Bulk nama & mode foto

### `GET /siswa/nama?ids=1,2`

id + nama saja, TERMASUK entitas yang sudah dihapus (withTrashed) -- inilah yang membuat nama historis di riwayat ter-resolve alih-alih tampil `#<id>`. Tanpa `ids` mengembalikan SELURUHNYA tanpa batas halaman (ringan: 2 kolom, tanpa foto/PII), jadi cocok untuk cache nama sekolah besar. Tersedia juga di guru/karyawan/mapel/class. BUKAN pengganti /all untuk dropdown -- memuat entitas non-aktif.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Daftar nama.",
  "data": [
    {
      "idSiswa": 1,
      "namaLengkap": "Andi Wijaya"
    },
    {
      "idSiswa": 2,
      "namaLengkap": "Budi Santoso"
    }
  ]
}
```

### `GET /siswa/all?foto=1&per_page=3`

Mode berfoto: `foto` berupa URL ke endpoint BER-AUTENTIKASI, bukan Base64 dan bukan /storage (foto ada di disk private). Klien wajib mengirim header Authorization; tanpa itu 401. per_page 1-25 (default 5) di mode ini; tanpa `foto=1` batasnya 200 dan field foto tidak ada sama sekali.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "List Data Siswa.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "idSiswa": 1,
        "namaLengkap": "Andi Wijaya",
        "nisn": "0123456781",
        "jenisKelamin": "Laki-Laki",
        "tempatLahir": "Bandung",
        "tanggalLahir": "2008-05-20",
        "tanggalMasuk": "2023-07-10",
        "status": "Aktif",
        "foto": "\/api\/siswa\/foto\/1"
      },
      {
        "idSiswa": 4,
        "namaLengkap": "Dedi Pratama",
        "nisn": "0123456784",
        "jenisKelamin": "Laki-Laki",
        "tempatLahir": "Semarang",
        "tanggalLahir": "2008-09-25",
        "tanggalMasuk": "2023-07-10",
        "status": "Aktif",
        "foto": "\/api\/siswa\/foto\/4"
      },
      {
        "idSiswa": 6,
        "namaLengkap": "Andi Pratama",
        "nisn": "990615222501",
        "jenisKelamin": "Laki-Laki",
        "tempatLahir": "Jakarta",
        "tanggalLahir": "2007-03-10",
        "tanggalMasuk": "2022-07-15",
        "status": "Aktif",
        "foto": "\/api\/siswa\/foto\/6"
      }
    ],
    "from": 1,
    "last_page": 3,
    "links": [
      {
        "query": "per_page=3&foto=1&page=1",
        "label": "1",
        "page": 1,
        "active": true
      },
      {
        "query": "per_page=3&foto=1&page=2",
        "label": "2",
        "page": 2,
        "active": false
      },
      {
        "query": "per_page=3&foto=1&page=3",
        "label": "3",
        "page": 3,
        "active": false
      },
      {
        "query": "per_page=3&foto=1&page=2",
        "label": "Next &raquo;",
        "page": 2,
        "active": false
      }
    ],
    "per_page": 3,
    "to": 3,
    "total": 7
  }
}
```

### `GET /siswa/all?foto=1&per_page=26`

422: di mode foto per_page maksimum 25 (foto berat).

Response (HTTP 422):
```json
{
  "resCode": 422,
  "resPhrase": "Unprocessible Entity",
  "resStatus": "fail",
  "resMsg": "per_page maksimum 25 saat foto=1 (foto berat).",
  "data": []
}
```

---

## Nama relasi pada endpoint akademik

### `GET /akademik/kelas/1/siswa/riwayat`

Riwayat kini menyertakan `namaLengkap` di samping `siswaId`. Nama diambil dengan withTrashed sehingga siswa yang sudah lulus/pindah/dihapus IKUT ter-resolve -- sebelumnya tampil `#<id>` karena klien meresolusi dari roster aktif. Urutannya terbaru->terlama dari server. Bila service nama bermasalah, barisnya tetap dikembalikan TANPA field nama (bukan gagal), jadi tetap sediakan fallback #id.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Riwayat lengkap siswa di kelas id:1.",
  "data": [
    {
      "idSiswaKelas": 4,
      "siswaId": 1,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "namaLengkap": "Andi Wijaya"
    },
    {
      "idSiswaKelas": 2,
      "siswaId": 2,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "1",
      "deletedAt": "2026-06-19T01:21:45.000000Z",
      "namaLengkap": "Budi Santoso"
    }
  ]
}
```

### `GET /akademik/jadwal/kelas/1?tahun_ajaran=2024%2F2025&semester=2`

Jadwal menyertakan namaMapel/namaGuru/namaKelas. Berlaku juga di jadwal guru/siswa/pengampu dan varian /riwayat, serta di kelas/{id}/pengampu dan guru|mapel riwayat.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Jadwal kelas id:1.",
  "data": [
    {
      "idJadwal": 7,
      "pengampuMapelId": 4,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "hari": "Senin",
      "jamMulaiId": 1,
      "jamSelesaiId": 9,
      "keMulai": 1,
      "keSelesai": 3,
      "pukul": "07:00:00 - 09:15:00",
      "ruangan": "Kelas A",
      "namaGuru": "Andi Susanto",
      "namaMapel": "Matematika Lanjutan 1781687275",
      "namaKelas": "X MIPA 1"
    }
  ]
}
```


> **Email bisa dipakai ulang setelah dihapus.** `users.email` dan `{tabel}.email`
> unik di level DB sementara modelnya soft-delete, jadi baris yang "dihapus" menahan
> emailnya. Sekarang baris lamanya **DIPULIHKAN**, bukan ditolak:
>
> | Kondisi | Hasil |
> |---|---|
> | email dipegang akun/record **aktif** | **422** |
> | email dipegang akun/record **terhapus** | **201** + `dipulihkan: true` |
> | `nip`/`nik`/`nisn` dipegang record lain | **422** |
>
> `id` yang dikembalikan adalah **id LAMA** -- disengaja, supaya tautan ke riwayat
> akademik tetap utuh. Data lama ditimpa data baru, token lama dicabut, dan setiap
> pemulihan dicatat ke audit log (`action = restored`). Tidak dicontohkan di sini
> karena butuh siklus buat-hapus-buat.

---

## Contoh Error Umum

### `GET /user`

401 tanpa token.

Response (HTTP 401):
```json
{
  "resCode": 401,
  "resPhrase": "Unauthenticated",
  "resStatus": "fail",
  "resMsg": "Unauthenticated",
  "data": "Unauthenticated."
}
```

### `GET /user`

401 token invalid/kedaluwarsa -> app harus hapus sesi lokal dan kembali ke Login.

Response (HTTP 401):
```json
{
  "resCode": 401,
  "resPhrase": "Unauthenticated",
  "resStatus": "fail",
  "resMsg": "Unauthenticated",
  "data": "Unauthenticated."
}
```

### `POST /login`

Login gagal = HTTP 400 (bukan 401) -- jangan trigger auto-logout.

Request body:
```json
{
  "password": "salah-password-123",
  "email": "superadmin@example.com"
}
```

Response (HTTP 400):
```json
{
  "resCode": 400,
  "resPhrase": "Bad Request",
  "resStatus": "fail",
  "resMsg": "Invalid user credentials.",
  "data": []
}
```

### `POST /akademik/kelas/assign`

404 validasi cross-service: siswa_id tidak ada di SiswaService.

Request body:
```json
{
  "kelas_id": 1,
  "semester": 2,
  "siswa_id": 99999,
  "tahun_ajaran": "2024/2025"
}
```

Response (HTTP 404):
```json
{
  "resCode": 404,
  "resPhrase": "Not Found",
  "resStatus": "fail",
  "resMsg": "Siswa tidak ditemukan atau sudah tidak aktif.",
  "data": []
}
```

### `POST /mapel`

422 validasi: resMsg berisi pesan pertama; bentuk data BERVARIASI antar modul -- jangan parsing data untuk 422.

Request body:
```json
{
  "keterangan": "tanpa field wajib"
}
```

Response (HTTP 422):
```json
{
  "resCode": 422,
  "resPhrase": "Unprocessible Entity",
  "resStatus": "fail",
  "resMsg": "The kode field is required.",
  "data": {
    "kode": [
      "The kode field is required."
    ],
    "namaPelajaran": [
      "The nama pelajaran field is required."
    ]
  }
}
```

### `POST /akademik/pengaturan-nilai`

409 konflik bisnis: pengaturan semester ini sudah ada. Tampilkan resMsg apa adanya.

Request body:
```json
{
  "bobot_uts": 30,
  "semester": 2,
  "bobot_uas": 30,
  "bobot_harian": 40,
  "tahun_ajaran": "2024/2025"
}
```

Response (HTTP 409):
```json
{
  "resCode": 409,
  "resPhrase": "Conflict",
  "resStatus": "fail",
  "resMsg": "Pengaturan nilai untuk semester 2 tahun ajaran 2024\/2025 sudah ada. Gunakan PATCH untuk mengubah.",
  "data": []
}
```

### `POST /karyawan`

422 email sudah dipakai akun lain. Gateway menolak SEBELUM menulis record domain -- kalau tidak, recordnya terlanjur tersimpan tanpa akun login dan pemanggil hanya menerima 500. `users` memakai soft delete sedangkan emailnya unik di DB, jadi akun yang sudah DIHAPUS pun masih memegang emailnya.

Request body:
```json
{
  "email": "akuntest.karyawan@example.com",
  "nip": "8000009",
  "jabatan": "Satpam",
  "namaLengkap": "Contoh Email Ganda"
}
```

Response (HTTP 422):
```json
{
  "resCode": 422,
  "resPhrase": "Unprocessible Entity",
  "resStatus": "fail",
  "resMsg": "Email sudah terpakai akun lain yang masih aktif. Pakai email berbeda.",
  "data": []
}
```


> **Respons 500 tidak memuat detail internal.** `resMsg`-nya selalu berbentuk
> `"Terjadi kesalahan di server. Sertakan kode A1B2C3D4 saat melaporkannya."` --
> aman ditampilkan apa adanya ke pengguna. Detail aslinya (termasuk SQL) ada di log
> server dengan kode rujukan yang sama. Tidak dicontohkan di sini karena memicunya
> butuh membuat kondisi galat sungguhan.
>
> Service juga SELALU membalas envelope `resCode`/`resMsg`, termasuk untuk error
> yang tidak tertangkap. Sebelumnya bentuknya bawaan Laravel (`message`/`exception`/
> `trace`) yang tidak bisa di-parse DTO envelope -- satu DTO kini cukup untuk semua.


> **Upload foto: dimensi 360x480 s/d 6000x6000 px**, di luar itu **422**
> `The foto field has invalid image dimensions`. Tidak dicontohkan di sini karena
> butuh multipart. **Klien wajib memperkecil sebelum unggah** -- foto HP kelas atas
> melebihi 6000 px walau sudah di-crop 3:4 (iPhone 48 MP = 8064x6048 -> crop jadi
> +-4536x6048). Perkecil sisi terpanjang ke +-1600 px setelah crop.
>
> **`per_page` maksimum 200** di semua endpoint berpaginasi; di luar 1-200 -> **422**.

### `POST /login` -- 429 rate limit (contoh terdokumentasi, tidak di-capture ulang agar tidak mengunci akun)

Sejak throttle API menyala, **429 bisa muncul di endpoint mana pun**, tidak hanya
login: 240/menit per user (login/refresh/password tetap 5/menit per IP). Respons
membawa header `Retry-After`, `X-RateLimit-Limit`, `X-RateLimit-Remaining`,
dan `X-RateLimit-Reset`; `data.retryAfter` mengulang detik tunggunya supaya
klien tidak perlu membaca header.

Response (HTTP 429):
```json
{"resCode":429,"resPhrase":"Too Many Requests","resStatus":"fail","resMsg":"Terlalu banyak percobaan. Coba lagi dalam 43 detik.","data":{"retryAfter":43}}
```

### Wajib ganti password saat login pertama (akun auto-created)

Akun guru/siswa yang dibuat otomatis memakai password default dan ditandai
wajib ganti password. Response login-nya `"mustChangePassword": true`, dan
SEMUA endpoint lain diblokir 403 sampai `POST /password` dilakukan
(pengecualian: `/password`, `/user`, `/logout`, `/logout-all`).

Response (HTTP 403) di endpoint mana pun selama flag aktif (capture asli):
```json
{
  "resCode": 403,
  "resPhrase": "Forbidden",
  "resStatus": "fail",
  "resMsg": "Anda wajib mengganti password sebelum dapat mengakses fitur lain. Gunakan POST \/password.",
  "data": {
    "mustChangePassword": true
  }
}
```

Bedakan 403 ini dari 403 role: cek `data.mustChangePassword == true` -> arahkan
ke layar Ganti Password wajib. `GET /user` juga menyertakan flag yang sama
dengan nama `must_change_password` (snake_case) untuk pengecekan saat app start.
