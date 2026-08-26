# Contoh Response API - SIM Sekolah (hasil capture asli)

Dokumen ini berisi response JSON **asli** yang direkam dari Gateway pada 2026-08-17,
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
| **Administrator Sekolah** | akuntest.adminsekolah@example.com | AdminSekolahTest123 | Karyawan bertanda `isAdminSekolah: true` — boleh manajemen akademik. Pakai untuk menguji gating menu |
| Guru | andi.susanto2@sekolah.com | GuruTest123 | Terhubung record guru id=1 (punya pengampu) |
| Siswa | andi.siswa1@sekolah.com | SiswaTest123 | Terhubung record siswa id=1 (terdaftar di kelas) |

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
    "created_at": "2026-06-15T14:31:39.000000Z",
    "updated_at": "2026-07-14T03:56:46.000000Z",
    "deleted_at": null,
    "isAdminSekolah": false
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
        "created_at": "2026-06-15T14:31:39.000000Z",
        "isAdminSekolah": false
      },
      {
        "id": 2,
        "name": "Ahmad Admin",
        "email": "ahmad.admin@sekolah.com",
        "role": "Admin",
        "must_change_password": false,
        "created_at": "2026-06-15T14:42:38.000000Z",
        "isAdminSekolah": false
      },
      {
        "id": 3,
        "name": "Budi Guru",
        "email": "budi.guru@sekolah.com",
        "role": "Guru",
        "must_change_password": false,
        "created_at": "2026-06-15T14:42:54.000000Z",
        "isAdminSekolah": false
      }
    ],
    "first_page_url": "https:\/\/gateway.test\/api\/users?page=1",
    "from": 1,
    "last_page": 12,
    "last_page_url": "https:\/\/gateway.test\/api\/users?page=12",
    "links": [
      {
        "url": null,
        "label": "&laquo; Previous",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?page=1",
        "label": "1",
        "active": true
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?page=2",
        "label": "2",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?page=3",
        "label": "3",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?page=4",
        "label": "4",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?page=5",
        "label": "5",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?page=6",
        "label": "6",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?page=7",
        "label": "7",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?page=8",
        "label": "8",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?page=9",
        "label": "9",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?page=10",
        "label": "10",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?page=11",
        "label": "11",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?page=12",
        "label": "12",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?page=2",
        "label": "Next &raquo;",
        "active": false
      }
    ],
    "next_page_url": "https:\/\/gateway.test\/api\/users?page=2",
    "path": "https:\/\/gateway.test\/api\/users",
    "per_page": 3,
    "prev_page_url": null,
    "to": 3,
    "total": 36
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
        "created_at": "2026-07-11T03:47:59.000000Z",
        "isAdminSekolah": false
      },
      {
        "id": 184,
        "name": "Akun Test Administrator Sekolah",
        "email": "akuntest.adminsekolah@example.com",
        "role": "Karyawan",
        "must_change_password": false,
        "created_at": "2026-08-17T03:47:35.000000Z",
        "isAdminSekolah": true
      }
    ],
    "first_page_url": "https:\/\/gateway.test\/api\/users?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "https:\/\/gateway.test\/api\/users?page=1",
    "links": [
      {
        "url": null,
        "label": "&laquo; Previous",
        "active": false
      },
      {
        "url": "https:\/\/gateway.test\/api\/users?page=1",
        "label": "1",
        "active": true
      },
      {
        "url": null,
        "label": "Next &raquo;",
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
    "created_at": "2026-07-11T03:47:59.000000Z",
    "isAdminSekolah": false
  }
}
```

### `POST /register`

Request body:
```json
{
  "email": "sampleuser_111744@example.com",
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
    "email": "sampleuser_111744@example.com",
    "role": "Karyawan"
  }
}
```

### `POST /users/192/password`

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
    "email": "sampleuser_111744@example.com",
    "role": "Karyawan"
  }
}
```

### `DELETE /users/192`

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
    "email": "sampleuser_111744@example.com",
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
    "last_page": 5,
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
    "total": 15
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
    "created_at": "2026-06-15T14:46:31.000000Z",
    "updated_at": "2026-06-19T09:39:56.000000Z",
    "deleted_at": null
  }
}
```

### `POST /mapel`

Request body:
```json
{
  "kode": "SMPL111744",
  "namaPelajaran": "Mapel Contoh 111744",
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
    "kode": "SMPL111744",
    "namaPelajaran": "Mapel Contoh 111744",
    "keterangan": "Dibuat oleh capture-api-samples.ps1",
    "updated_at": "2026-08-17T04:18:15.000000Z",
    "created_at": "2026-08-17T04:18:15.000000Z",
    "idPelajaran": 77
  }
}
```

### `POST /mapel/update`

Update parsial: kirim `idPelajaran` + field yang berubah saja.

Request body:
```json
{
  "keterangan": "Keterangan diubah",
  "idPelajaran": 77
}
```

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Mata Pelajaran dengan id:77 berhasil diupdate.",
  "data": {
    "idPelajaran": 77,
    "kode": "SMPL111744",
    "namaPelajaran": "Mapel Contoh 111744",
    "keterangan": "Keterangan diubah",
    "created_at": "2026-08-17T04:18:15.000000Z",
    "updated_at": "2026-08-17T04:18:18.000000Z",
    "deleted_at": null
  }
}
```

### `DELETE /mapel/77`

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Mata Pelajaran dengan id:77 berhasil dihapus.",
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
    "total": 12
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
    "created_at": "2026-06-15T14:47:07.000000Z",
    "updated_at": "2026-06-15T14:47:17.000000Z",
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
    "updated_at": "2026-08-17T04:18:31.000000Z",
    "created_at": "2026-08-17T04:18:31.000000Z",
    "idKelas": 104
  }
}
```

### `POST /class/update`

Request body:
```json
{
  "idKelas": 104,
  "limitSiswa": 32
}
```

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Ruang kelas dengan id:104 berhasil diupdate.",
  "data": {
    "idKelas": 104,
    "namaKelas": "XII IPS 96",
    "tingkat": "3",
    "jurusan": "IPS",
    "limitSiswa": "32",
    "created_at": "2026-08-17T04:18:31.000000Z",
    "updated_at": "2026-08-17T04:18:34.000000Z",
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
    "total": 8
  }
}
```

### `GET /guru?idGuru=1`

Detail LENGKAP -- hanya diterima SuperAdmin/Admin/Karyawan. Role Guru/Siswa menerima versi tersaring (lihat bagian Role Siswa di bawah).

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
    "agama": null,
    "statusPernikahan": null,
    "alamat": "Jl. Merdeka 1",
    "foto": "data:image\/webp;base64,UklGRoIBAABXRUJQVlA4IHYB...<dipotong>",
    "kartu_uid": "GUR-QWGT3XWNTERJ",
    "kartu_status": "aktif",
    "kartu_diterbitkan_at": "2026-08-17T04:11:06.000000Z",
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
    "created_at": "2026-06-15T14:49:12.000000Z",
    "updated_at": "2026-08-17T04:11:06.000000Z",
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

Detail lengkap termasuk data orang tua/wali. Role Siswa mendapat 403 (lihat bagian Role Siswa).

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
    "foto": "data:image\/webp;base64,UklGRoIBAABXRUJQVlA4IHYB...<dipotong>",
    "kartu_uid": "SIS-J4Y661TEZ9QG",
    "kartu_status": "aktif",
    "kartu_diterbitkan_at": "2026-08-17T04:11:03.000000Z",
    "namaAyah": null,
    "namaIbu": "Siti Aminah",
    "pekerjaanAyah": null,
    "pekerjaanIbu": null,
    "noTelpAyah": null,
    "noTelpIbu": null,
    "namaWali": null,
    "hubunganWali": null,
    "noTelpWali": null,
    "created_at": "2026-06-15T07:50:37.000000Z",
    "updated_at": "2026-08-17T04:11:03.000000Z",
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
    "idSemesterAktif": 4,
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
    "total_terdaftar": 6,
    "total_belum": 1,
    "siswa": [
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
      "kelasId": 2,
      "tahunAjaran": "2024\/2025",
      "semester": "2"
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
      "idSiswaKelas": 6,
      "siswaId": 4,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2"
    },
    {
      "idSiswaKelas": 8,
      "siswaId": 9,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2"
    },
    {
      "idSiswaKelas": 9,
      "siswaId": 7,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2"
    },
    {
      "idSiswaKelas": 10,
      "siswaId": 8,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2"
    },
    {
      "idSiswaKelas": 11,
      "siswaId": 11,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2"
    }
  ]
}
```

### `GET /akademik/siswa/1/kelas/riwayat`

Riwayat lengkap: `deletedAt` terisi jika pernah dipindah/dibatalkan.

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Riwayat lengkap kelas siswa id:1.",
  "data": [
    {
      "idSiswaKelas": 1,
      "siswaId": 1,
      "kelasId": 2,
      "tahunAjaran": "2024\/2025",
      "semester": "1"
    },
    {
      "idSiswaKelas": 4,
      "siswaId": 1,
      "kelasId": 2,
      "tahunAjaran": "2024\/2025",
      "semester": "2"
    }
  ]
}
```

### `POST /akademik/kelas/assign`

Request akademik memakai snake_case; response camelCase.

Request body:
```json
{
  "kelas_id": 104,
  "semester": 2,
  "siswa_id": 6,
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
    "idSiswaKelas": 7,
    "siswaId": 6,
    "kelasId": 104,
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
      "semester": "2"
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
      "semester": "2"
    },
    {
      "idPengampuMapel": 5,
      "guruId": 2,
      "mapelId": 2,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": "2"
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
      "semester": "2"
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
  "kelas_id": 104,
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
    "idPengampuMapel": 14,
    "guruId": 1,
    "mapelId": 1,
    "kelasId": 104,
    "tahunAjaran": "2024\/2025",
    "semester": 2
  }
}
```

---

## Akademik - Jadwal Pelajaran

### `POST /akademik/jadwal`

`pukul` adalah string siap tampil. `keMulai`/`keSelesai` = urutan jam.

Request body:
```json
{
  "hari": "Senin",
  "pengampu_mapel_id": 14,
  "catatan": "Dibuat oleh capture script",
  "jam_mulai_id": 1,
  "jam_selesai_id": 2,
  "ruangan": "Lab Contoh"
}
```

Response (HTTP 201):
```json
{
  "resCode": 201,
  "resPhrase": "Created",
  "resStatus": "success",
  "resMsg": "Jadwal pelajaran berhasil ditambahkan.",
  "data": {
    "idJadwal": 18,
    "pengampuMapelId": 14,
    "guruId": 1,
    "mapelId": 1,
    "kelasId": 104,
    "tahunAjaran": "2024\/2025",
    "semester": "2",
    "hari": "Senin",
    "jamMulaiId": 1,
    "jamSelesaiId": 2,
    "keMulai": 1,
    "keSelesai": 2,
    "pukul": "07:00:00 - 08:30:00",
    "ruangan": "Lab Contoh",
    "catatan": "Dibuat oleh capture script"
  }
}
```

### `GET /akademik/jadwal/kelas/1?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Jadwal kelas id:1.",
  "data": []
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
      "idJadwal": 18,
      "pengampuMapelId": 14,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 104,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "hari": "Senin",
      "jamMulaiId": 1,
      "jamSelesaiId": 2,
      "keMulai": 1,
      "keSelesai": 2,
      "pukul": "07:00:00 - 08:30:00",
      "ruangan": "Lab Contoh",
      "catatan": "Dibuat oleh capture script"
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
  "data": []
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
  "pengampu_mapel_id": 14,
  "siswa_kelas_id": 7,
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
    "idNilai": 21,
    "siswaKelasId": 7,
    "siswaId": 6,
    "pengampuMapelId": 14,
    "guruId": 1,
    "mapelId": 1,
    "kelasId": 104,
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

### `PATCH /akademik/nilai/21`

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
    "idNilai": 21,
    "siswaKelasId": 7,
    "siswaId": 6,
    "pengampuMapelId": 14,
    "guruId": 1,
    "mapelId": 1,
    "kelasId": 104,
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

### `PATCH /akademik/nilai/21`

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
    "idNilai": 21,
    "siswaKelasId": 7,
    "siswaId": 6,
    "pengampuMapelId": 14,
    "guruId": 1,
    "mapelId": 1,
    "kelasId": 104,
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

### `GET /akademik/nilai/pengampu/14?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Nilai pengampu id:14.",
  "data": [
    {
      "idNilai": 21,
      "siswaKelasId": 7,
      "siswaId": 6,
      "pengampuMapelId": 14,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 104,
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

### `GET /akademik/nilai/siswa/6?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Nilai siswa id:6.",
  "data": [
    {
      "idNilai": 15,
      "siswaKelasId": 7,
      "siswaId": 6,
      "pengampuMapelId": 4,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": 2,
      "nilaiHarian": 80,
      "ulanganHarian": [
        80,
        null,
        null,
        null,
        null
      ],
      "jumlahUlangan": 1,
      "nilaiUts": 80,
      "nilaiUas": 80,
      "nilaiAkhir": 80
    },
    {
      "idNilai": 16,
      "siswaKelasId": 7,
      "siswaId": 6,
      "pengampuMapelId": 5,
      "guruId": 2,
      "mapelId": 2,
      "kelasId": 1,
      "tahunAjaran": "2024\/2025",
      "semester": 2,
      "nilaiHarian": 80,
      "ulanganHarian": [
        80,
        null,
        null,
        null,
        null
      ],
      "jumlahUlangan": 1,
      "nilaiUts": 80,
      "nilaiUas": 80,
      "nilaiAkhir": 80
    },
    {
      "idNilai": 21,
      "siswaKelasId": 7,
      "siswaId": 6,
      "pengampuMapelId": 14,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 104,
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

### `GET /akademik/raport/siswa/6?tahun_ajaran=2024%2F2025&semester=2`

`tahun_ajaran` dan `semester` WAJIB di semua endpoint raport/ranking (422 jika kosong).

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Raport siswa id:6.",
  "data": {
    "siswaId": 6,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "bobot": {
      "bobotHarian": 40,
      "bobotUts": 30,
      "bobotUas": 30
    },
    "nilai": [
      {
        "idNilai": 15,
        "pengampuMapelId": 4,
        "guruId": 1,
        "mapelId": 1,
        "nilaiHarian": 80,
        "ulanganHarian": [
          80,
          null,
          null,
          null,
          null
        ],
        "nilaiUts": 80,
        "nilaiUas": 80,
        "nilaiAkhir": 80
      },
      {
        "idNilai": 16,
        "pengampuMapelId": 5,
        "guruId": 2,
        "mapelId": 2,
        "nilaiHarian": 80,
        "ulanganHarian": [
          80,
          null,
          null,
          null,
          null
        ],
        "nilaiUts": 80,
        "nilaiUas": 80,
        "nilaiAkhir": 80
      },
      {
        "idNilai": 21,
        "pengampuMapelId": 14,
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
    "rataRata": 80.8
  }
}
```

### `GET /akademik/raport/kelas/104?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Raport kelas id:104.",
  "data": {
    "kelasId": 104,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "bobot": {
      "bobotHarian": 40,
      "bobotUts": 30,
      "bobotUas": 30
    },
    "siswa": [
      {
        "siswaId": 6,
        "siswaKelasId": 7,
        "nilai": [
          {
            "pengampuMapelId": 4,
            "mapelId": 1,
            "nilaiHarian": 80,
            "ulanganHarian": [
              80,
              null,
              null,
              null,
              null
            ],
            "nilaiUts": 80,
            "nilaiUas": 80,
            "nilaiAkhir": 80
          },
          {
            "pengampuMapelId": 5,
            "mapelId": 2,
            "nilaiHarian": 80,
            "ulanganHarian": [
              80,
              null,
              null,
              null,
              null
            ],
            "nilaiUts": 80,
            "nilaiUas": 80,
            "nilaiAkhir": 80
          },
          {
            "pengampuMapelId": 14,
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
        "rataRata": 80.8
      }
    ]
  }
}
```

### `GET /akademik/nilai/ranking/kelas/104?tahun_ajaran=2024%2F2025&semester=2`

Response (HTTP 200):
```json
{
  "resCode": 200,
  "resPhrase": "Ok",
  "resStatus": "success",
  "resMsg": "Peringkat kelas id:104.",
  "data": {
    "kelasId": 104,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "totalSiswa": 1,
    "ranking": [
      {
        "peringkat": 1,
        "siswaId": 6,
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
    "idPeriode": 66,
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
    "idPeriode": 67,
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
    "idPeriode": 67,
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
    "idPeriode": 66,
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
      "idPeriode": 66,
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
      "idPeriode": 23,
      "nama": "Test Libur 075649",
      "tahunAjaran": "2024\/2025",
      "semester": 2,
      "jenis": "libur",
      "berlakuDari": "2030-02-10",
      "berlakuSampai": "2030-02-10",
      "kbmNormal": false,
      "keterangan": null
    },
    {
      "idPeriode": 67,
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
  "periode_id": 66,
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
  "resMsg": "Jam ke-1 berhasil ditambahkan (periode id:66, semua hari).",
  "data": {
    "idJam": 90,
    "periodeId": 66,
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
      "idPeriode": 66,
      "nama": "Ramadan (contoh)",
      "jenis": "ramadan"
    },
    "jam": [
      {
        "idJam": 90,
        "periodeId": 66,
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
      "idJadwal": 18,
      "pengampuMapelId": 14,
      "guruId": 1,
      "mapelId": 1,
      "kelasId": 104,
      "tahunAjaran": "2024\/2025",
      "semester": "2",
      "hari": "Senin",
      "jamMulaiId": 1,
      "jamSelesaiId": 2,
      "keMulai": 1,
      "keSelesai": 2,
      "pukul": "07:30:00 - 08:30:00",
      "ruangan": "Lab Contoh",
      "catatan": "Dibuat oleh capture script"
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
  "periode_id": 66,
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
    "idPengaturanAbsensi": 54,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "periodeId": 66,
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
      "idPeriode": 66,
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
  "resMsg": "Pengaturan absensi berlaku pada 2026-08-17.",
  "data": {
    "tanggal": "2026-08-17",
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

### `DELETE /akademik/pengaturan-absensi/54`

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

### `DELETE /akademik/periode/66`

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
    "kartuUid": "SIS-CPUYITEO9U0T",
    "kartuStatus": "aktif",
    "kartuDiterbitkanAt": "2026-08-17T04:21:16.000000Z"
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
    "kartuUid": "GUR-GLEHHQKN42ZB",
    "kartuStatus": "aktif",
    "kartuDiterbitkanAt": "2026-08-17T04:21:26.000000Z"
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
    "idKeluar": 37,
    "siswaId": 1,
    "tanggal": "2026-08-17",
    "jamKeluar": "2026-08-17 11:21:29",
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
  "resMsg": "Daftar izin keluar tanggal 2026-08-17.",
  "data": [
    {
      "idKeluar": 32,
      "siswaId": 1,
      "tanggal": "2026-08-17",
      "jamKeluar": "2026-08-17 09:50:03",
      "jenis": "pulang_awal",
      "keterangan": "test 093739",
      "disetujuiOleh": 1,
      "terminalId": null
    },
    {
      "idKeluar": 33,
      "siswaId": 1,
      "tanggal": "2026-08-17",
      "jamKeluar": "2026-08-17 10:08:50",
      "jenis": "pulang_awal",
      "keterangan": "test 095806",
      "disetujuiOleh": 1,
      "terminalId": null
    },
    {
      "idKeluar": 34,
      "siswaId": 1,
      "tanggal": "2026-08-17",
      "jamKeluar": "2026-08-17 10:27:18",
      "jenis": "pulang_awal",
      "keterangan": "test 101512",
      "disetujuiOleh": 1,
      "terminalId": null
    },
    {
      "idKeluar": 35,
      "siswaId": 1,
      "tanggal": "2026-08-17",
      "jamKeluar": "2026-08-17 11:05:02",
      "jenis": "pulang_awal",
      "keterangan": "test 105310",
      "disetujuiOleh": 1,
      "terminalId": null
    },
    {
      "idKeluar": 36,
      "siswaId": 1,
      "tanggal": "2026-08-17",
      "jamKeluar": "2026-08-17 11:11:09",
      "jenis": "pulang_awal",
      "keterangan": "dijemput orang tua",
      "disetujuiOleh": 1,
      "terminalId": null
    },
    {
      "idKeluar": 37,
      "siswaId": 1,
      "tanggal": "2026-08-17",
      "jamKeluar": "2026-08-17 11:21:29",
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
    "dari": "2026-08-01",
    "sampai": "2026-08-17",
    "siswa": [
      {
        "siswaId": 4,
        "hadir": 0,
        "terlambat": 0,
        "izin": 0,
        "sakit": 0,
        "alpa": 0,
        "total": 0,
        "namaLengkap": "Dedi Pratama"
      },
      {
        "siswaId": 9,
        "hadir": 0,
        "terlambat": 0,
        "izin": 0,
        "sakit": 0,
        "alpa": 0,
        "total": 0,
        "namaLengkap": "Dewi Kusuma"
      },
      {
        "siswaId": 7,
        "hadir": 0,
        "terlambat": 0,
        "izin": 0,
        "sakit": 0,
        "alpa": 0,
        "total": 0,
        "namaLengkap": "Bella Sari"
      },
      {
        "siswaId": 8,
        "hadir": 0,
        "terlambat": 0,
        "izin": 0,
        "sakit": 0,
        "alpa": 0,
        "total": 0,
        "namaLengkap": "Cahyo Nugraha"
      },
      {
        "siswaId": 11,
        "hadir": 0,
        "terlambat": 0,
        "izin": 0,
        "sakit": 0,
        "alpa": 0,
        "total": 0,
        "namaLengkap": "Ahmad Fauzi 1781687411"
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
    "dari": "2026-08-01",
    "sampai": "2026-08-17",
    "ringkasan": {
      "hadir": 0,
      "terlambat": 0,
      "izin": 0,
      "sakit": 0,
      "alpa": 0,
      "total": 0
    },
    "detail": []
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
    "dari": "2026-08-01",
    "sampai": "2026-08-17",
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
    "dari": "2026-08-01",
    "sampai": "2026-08-17",
    "ringkasan": {
      "hadir": 0,
      "terlambat": 0,
      "izin": 0,
      "sakit": 0,
      "dinas_luar": 0,
      "alpa": 0,
      "total": 0
    },
    "detail": []
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
    "idPinWindow": 74,
    "subjekTipe": "guru",
    "subjekId": 1,
    "berlakuSampai": "2026-08-17 11:31:48",
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
    "rataRataAngkatan": 55.03,
    "totalSiswa": 6,
    "ranking": [
      {
        "peringkat": 1,
        "siswaId": 9,
        "namaLengkap": "Dewi Kusuma",
        "nisn": "990615222504",
        "kelas": "X MIPA 1",
        "rataRata": 86.34,
        "predikat": "B",
        "jumlahMapel": 2,
        "belumDinilai": 0
      },
      {
        "peringkat": 2,
        "siswaId": 7,
        "namaLengkap": "Bella Sari",
        "nisn": "990615222502",
        "kelas": "X MIPA 1",
        "rataRata": 85.6,
        "predikat": "B",
        "jumlahMapel": 2,
        "belumDinilai": 0
      },
      {
        "peringkat": 3,
        "siswaId": 8,
        "namaLengkap": "Cahyo Nugraha",
        "nisn": "990615222503",
        "kelas": "X MIPA 1",
        "rataRata": 83.25,
        "predikat": "B",
        "jumlahMapel": 2,
        "belumDinilai": 0
      },
      {
        "peringkat": 4,
        "siswaId": 4,
        "namaLengkap": "Dedi Pratama",
        "nisn": "0123456784",
        "kelas": "X MIPA 1",
        "rataRata": 75,
        "predikat": "C",
        "jumlahMapel": 2,
        "belumDinilai": 0
      },
      {
        "peringkat": 5,
        "siswaId": 11,
        "namaLengkap": "Ahmad Fauzi 1781687411",
        "nisn": "1781687411",
        "kelas": "X MIPA 1",
        "rataRata": 0,
        "predikat": "E",
        "jumlahMapel": 2,
        "belumDinilai": 2
      },
      {
        "peringkat": 5,
        "siswaId": 1,
        "namaLengkap": "Andi Wijaya",
        "nisn": "0123456781",
        "kelas": "X IPS 2",
        "rataRata": 0,
        "predikat": "E",
        "jumlahMapel": 0,
        "belumDinilai": 0
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
    "rataRataAngkatan": 55.03,
    "totalSiswa": 6,
    "ranking": [
      {
        "peringkat": 1,
        "siswaId": 9,
        "namaLengkap": "Dewi Kusuma",
        "nisn": "990615222504",
        "kelas": "X MIPA 1",
        "rataRata": 86.34,
        "predikat": "B",
        "jumlahMapel": 2,
        "belumDinilai": 0,
        "nilai": [
          {
            "pengampuMapelId": 4,
            "mapelId": 1,
            "nilaiAkhir": 82.67,
            "dinilai": true,
            "predikat": "B"
          },
          {
            "pengampuMapelId": 5,
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
        "kelas": "X MIPA 1",
        "rataRata": 85.6,
        "predikat": "B",
        "jumlahMapel": 2,
        "belumDinilai": 0,
        "nilai": [
          {
            "pengampuMapelId": 4,
            "mapelId": 1,
            "nilaiAkhir": 86.2,
            "dinilai": true,
            "predikat": "B"
          },
          {
            "pengampuMapelId": 5,
            "mapelId": 2,
            "nilaiAkhir": 85,
            "dinilai": true,
            "predikat": "B"
          }
        ]
      },
      {
        "peringkat": 3,
        "siswaId": 8,
        "namaLengkap": "Cahyo Nugraha",
        "nisn": "990615222503",
        "kelas": "X MIPA 1",
        "rataRata": 83.25,
        "predikat": "B",
        "jumlahMapel": 2,
        "belumDinilai": 0,
        "nilai": [
          {
            "pengampuMapelId": 4,
            "mapelId": 1,
            "nilaiAkhir": 81.5,
            "dinilai": true,
            "predikat": "B"
          },
          {
            "pengampuMapelId": 5,
            "mapelId": 2,
            "nilaiAkhir": 85,
            "dinilai": true,
            "predikat": "B"
          }
        ]
      },
      {
        "peringkat": 4,
        "siswaId": 4,
        "namaLengkap": "Dedi Pratama",
        "nisn": "0123456784",
        "kelas": "X MIPA 1",
        "rataRata": 75,
        "predikat": "C",
        "jumlahMapel": 2,
        "belumDinilai": 0,
        "nilai": [
          {
            "pengampuMapelId": 4,
            "mapelId": 1,
            "nilaiAkhir": 75,
            "dinilai": true,
            "predikat": "C"
          },
          {
            "pengampuMapelId": 5,
            "mapelId": 2,
            "nilaiAkhir": 75,
            "dinilai": true,
            "predikat": "C"
          }
        ]
      },
      {
        "peringkat": 5,
        "siswaId": 11,
        "namaLengkap": "Ahmad Fauzi 1781687411",
        "nisn": "1781687411",
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
      },
      {
        "peringkat": 5,
        "siswaId": 1,
        "namaLengkap": "Andi Wijaya",
        "nisn": "0123456781",
        "kelas": "X IPS 2",
        "rataRata": 0,
        "predikat": "E",
        "jumlahMapel": 0,
        "belumDinilai": 0,
        "nilai": []
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
        "idKelas": 11,
        "namaKelas": "X MIPA 1",
        "tingkat": "1",
        "jurusan": "MIPA",
        "limitSiswa": "36"
      },
      {
        "idKelas": 29,
        "namaKelas": "X MIPA 99",
        "tingkat": "1",
        "jurusan": "MIPA",
        "limitSiswa": "35"
      },
      {
        "idKelas": 64,
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
    "to": 5,
    "total": 5
  }
}
```


**Ekspor laporan angkatan** `GET akademik/nilai/ranking/angkatan/export?...&format=csv|pdf` mengembalikan BERKAS, bukan JSON -- karena itu tidak dicontohkan di sini. Respons: `Content-Type: text/csv; charset=UTF-8` atau `application/pdf`, plus `Content-Disposition: attachment; filename=\

---

## Contoh Response DELETE (dari cleanup data sementara)

### `DELETE /akademik/nilai/21`

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

### `DELETE /akademik/jadwal/18`

Soft delete; jadwal masih terlihat di endpoint /riwayat.

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Jadwal pelajaran berhasil dihapus.",
  "data": []
}
```

### `DELETE /akademik/pengampu/14`

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

### `DELETE /akademik/kelas/assign/7`

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

### `DELETE /class/104`

Response (HTTP 202):
```json
{
  "resCode": 202,
  "resPhrase": "Accepted",
  "resStatus": "success",
  "resMsg": "Ruang kelas dengan id:104 berhasil dihapus.",
  "data": []
}
```

---

## Perilaku per Role (Guru / Siswa / Karyawan)

### `GET /guru?idGuru=1`

Detail guru DILIHAT ROLE SISWA/GURU: field pribadi (nik, alamat, telephone, tanggalLahir, dll.) DISARING oleh Gateway. Bandingkan dengan versi lengkap di bagian Guru. Buat semua field DTO detail nullable.

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
    "foto": "data:image\/webp;base64,UklGRoIBAABXRUJQVlA4IHYB...<dipotong>",
    "statusKepegawaian": "PNS",
    "jabatan": "Guru Senior",
    "pendidikanTerakhir": "S1"
  }
}
```

### `GET /siswa?idSiswa=2`

Role Siswa tidak boleh membuka detail siswa (403).

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

### `GET /siswa/saya`

Profil DIRI SENDIRI untuk role Siswa -- termasuk `foto` (data-URI base64). Ini satu-satunya jalur siswa ke datanya sendiri karena `GET /siswa?idSiswa=` diblokir untuk role Siswa. idSiswa diresolve dari email token.

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
    "foto": "data:image\/webp;base64,UklGRoIBAABXRUJQVlA4IHYB...<dipotong>",
    "kartu_uid": "SIS-VCCNQTYCSFEB",
    "kartu_status": "aktif",
    "kartu_diterbitkan_at": "2026-08-17T04:21:22.000000Z",
    "namaAyah": null,
    "namaIbu": "Siti Aminah",
    "pekerjaanAyah": null,
    "pekerjaanIbu": null,
    "noTelpAyah": null,
    "noTelpIbu": null,
    "namaWali": null,
    "hubunganWali": null,
    "noTelpWali": null,
    "created_at": "2026-06-15T07:50:37.000000Z",
    "updated_at": "2026-08-17T04:21:22.000000Z",
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
  "resMsg": "Peringkat kelas id:2.",
  "data": {
    "kelasId": 2,
    "tahunAjaran": "2024\/2025",
    "semester": 2,
    "totalSiswa": 0,
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
  "resPhrase": "",
  "resStatus": "",
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
    "id": 184,
    "name": "Akun Test Administrator Sekolah",
    "email": "akuntest.adminsekolah@example.com",
    "role": "Karyawan",
    "must_change_password": false,
    "email_verified_at": null,
    "created_at": "2026-08-17T03:47:35.000000Z",
    "updated_at": "2026-08-17T03:51:47.000000Z",
    "deleted_at": null,
    "isAdminSekolah": true
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
  "resPhrase": "",
  "resStatus": "",
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

### `POST /login` -- 429 rate limit (contoh terdokumentasi, tidak di-capture ulang agar tidak mengunci akun)

Response (HTTP 429):
```json
{"resCode":429,"resPhrase":"Too Many Requests","resStatus":"fail","resMsg":"Terlalu banyak percobaan login. Coba lagi dalam 1 menit.","data":[]}
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
