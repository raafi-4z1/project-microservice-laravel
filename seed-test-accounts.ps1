# =============================================================
#  SEED TEST ACCOUNTS — Microservice Laravel (untuk dev/testing app client)
#  Usage:
#    $env:TEST_ADMIN_PASSWORD = "PasswordSuperAdmin"
#    powershell -ExecutionPolicy Bypass -File seed-test-accounts.ps1
#
#  Membuat/mereset 4 akun test (idempotent, aman dijalankan berulang):
#    - Admin    : akuntest.admin@example.com    / AdminTest123
#    - Karyawan : akuntest.karyawan@example.com / KaryawanTest123
#    - Guru     : <email guru asli pertama yang punya pengampu> / GuruTest123
#    - Siswa    : <email siswa asli pertama yang terdaftar di kelas> / SiswaTest123
#  Akun Guru & Siswa memakai akun yang terhubung ke record asli di
#  GuruService/SiswaService agar endpoint /saya dan alur nilai bisa diuji.
# =============================================================

if (-not $env:TEST_ADMIN_PASSWORD) {
    Write-Host "ERROR: set dulu `$env:TEST_ADMIN_PASSWORD" -ForegroundColor Red
    exit 1
}

# Default ke gateway.test, BUKAN IP LAN: skrip ini dijalankan di mesin server,
# tempat gateway.test selalu menunjuk 127.0.0.1 lewat hosts Laragon. IP LAN
# berasal dari DHCP dan sudah berpindah .173 -> .162; mematoknya membuat skrip
# gagal "Unable to connect" setiap kali router membagi IP berbeda.
# Untuk menyeed server lain dari PC berbeda: $env:TEST_BASE_URL = "https://<IP>/api"
$script:GAGAL = 0
$BaseUrl = if ($env:TEST_BASE_URL) { $env:TEST_BASE_URL } else { "https://gateway.test/api" }

try {
    Add-Type @"
using System.Net;
using System.Security.Cryptography.X509Certificates;
public class TrustAllSeed : ICertificatePolicy {
    public bool CheckValidationResult(ServicePoint sp, X509Certificate cert, WebRequest req, int prob) { return true; }
}
"@
} catch {}
[System.Net.ServicePointManager]::CertificatePolicy = New-Object TrustAllSeed
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$script:TOKEN = $null

function Api {
    param([string]$Method, [string]$Path, [hashtable]$Body = $null, [string]$Token = $null)
    $tok = if ($Token) { $Token } else { $script:TOKEN }
    $headers = @{ 'Accept' = 'application/json' }
    if ($tok) { $headers['Authorization'] = "Bearer $tok" }
    $params = @{ Uri = "$BaseUrl/$Path"; Method = $Method; Headers = $headers; UseBasicParsing = $true; TimeoutSec = 30 }
    if ($Body -ne $null) {
        $headers['Content-Type'] = 'application/json'
        $params['Body'] = ($Body | ConvertTo-Json -Depth 6)
    }
    # Login, refresh, dan ganti password dibatasi 5/menit, dan skrip ini melakukan
    # belasan login beruntun (tiap akun: cek penanda + lepas penanda). Tanpa
    # menunggu, login ke-6 dan seterusnya membalas 429 — dan dulu 429 itu terbaca
    # sebagai "login gagal" yang diam-diam dilewati, meninggalkan akun test dengan
    # penanda wajib-ganti masih menyala sambil mencetak baris sukses.
    for ($coba = 1; $coba -le 3; $coba++) {
        try {
            return (Invoke-WebRequest @params).Content | ConvertFrom-Json
        } catch {
            $er = $_.Exception.Response
            $hasil = $null
            if ($er) {
                try {
                    $rd = [System.IO.StreamReader]::new($er.GetResponseStream())
                    $tx = $rd.ReadToEnd(); $rd.Close(); $er.Close()
                    if ($tx) { $hasil = $tx | ConvertFrom-Json }
                } catch {}
            }
            if ($hasil -and $hasil.resCode -eq 429 -and $coba -lt 3) {
                $tunggu = 61
                if ($hasil.data -and $hasil.data.retryAfter) { $tunggu = [int]$hasil.data.retryAfter + 1 }
                Write-Host "           (429 di $Path - tunggu ${tunggu}s lalu ulangi)" -ForegroundColor DarkGray
                Start-Sleep -Seconds $tunggu
                continue
            }
            if ($hasil) { return $hasil }
            return [PSCustomObject]@{ resCode = 0; resMsg = $_.Exception.Message; data = $null }
        }
    }
}

# Cari user by email (paginated /users + search), kembalikan id atau $null
function Find-UserId([string]$Email) {
    $r = Api GET "users`?search=$([uri]::EscapeDataString($Email))"
    foreach ($u in @($r.data.data)) { if ($u.email -eq $Email) { return $u.id } }
    return $null
}

# Pastikan akun ada dengan password yang diketahui: reset jika ada, register jika belum
# Akun yang dibuat lewat CRUD domain (POST /karyawan, /guru, /siswa) lahir dengan
# must_change_password = true, dan reset password oleh admin JUGA menyalakannya
# (password pilihan admin wajib dirotasi pemiliknya) — disengaja demi keamanan.
# Tapi untuk akun TEST hasilnya bikin repot:
# login berhasil namun endpoint lain 403 sampai passwordnya diganti sendiri.
# Jadi di sini flag-nya dilepas lewat alur resmi: reset ke password sementara,
# login, lalu ganti ke password final.
function Clear-MustChangePassword([string]$Email, [string]$UserId, [string]$Password) {
    $tmp = "$Password" + "Tmp1"
    $r = Api POST "users/$UserId/password" @{ new_password = $tmp; confirm_password = $tmp }
    if ($r.resCode -ne 200) { return $false }
    $lg = Api POST "login" @{ email = $Email; password = $tmp; device_name = "seed-script" }
    if ($lg.resCode -ne 200) { return $false }
    $ch = Api POST "password" @{ current_password = $tmp; new_password = $Password; confirm_password = $Password } -Token $lg.data.token
    return $ch.resCode -eq 200
}

function Ensure-Account([string]$Name, [string]$Email, [string]$Role, [string]$Password) {
    $id = Find-UserId $Email
    if ($id) {
        $r = Api POST "users/$id/password" @{ new_password = $Password; confirm_password = $Password }
        if ($r.resCode -ne 200) {
            Write-Host "  [GAGAL   ] Reset $Email -- $($r.resMsg)" -ForegroundColor Red; return $false
        }
        # Kalau akun masih ber-flag, lepaskan supaya langsung bisa dipakai menguji.
        # Sejak reset password oleh admin MENYALAKAN penanda wajib-ganti, langkah
        # ini bukan lagi sesekali — ia selalu terjadi. Karena itu kegagalan cek
        # login TIDAK boleh lagi jatuh ke cabang sukses: itulah yang dulu membuat
        # dua akun test tertinggal rusak sambil skrip mencetak [RESET].
        $cek = Api POST "login" @{ email = $Email; password = $Password; device_name = "seed-script" }
        if ($cek.resCode -ne 200) {
            Write-Host "  [GAGAL   ] $Role : $Email -- login verifikasi $($cek.resCode) $($cek.resMsg)" -ForegroundColor Red
            $script:GAGAL++
            return $false
        }
        if ($cek.data.mustChangePassword) {
            if (Clear-MustChangePassword $Email $id $Password) {
                Write-Host "  [RESET   ] $Role : $Email (flag ganti-password dilepas)" -ForegroundColor Green
            } else {
                Write-Host "  [GAGAL   ] $Role : $Email -- masih WAJIB ganti password" -ForegroundColor Red
                $script:GAGAL++
                return $false
            }
        } else {
            Write-Host "  [RESET   ] $Role : $Email" -ForegroundColor Green
        }
        return $true
    }
    $r = Api POST "register" @{ name = $Name; email = $Email; password = $Password; confirm_password = $Password; role = $Role }
    if ($r.resCode -eq 201) { Write-Host "  [REGISTER] $Role : $Email" -ForegroundColor Green; return $true }
    Write-Host "  [GAGAL   ] Register $Email -- $($r.resMsg)" -ForegroundColor Red; return $false
}

# ── Login SuperAdmin ──
$login = Api POST "login" @{ email = "superadmin@example.com"; password = $env:TEST_ADMIN_PASSWORD; device_name = "seed-script" }
if ($login.resCode -ne 200) { Write-Host "ERROR: login SuperAdmin gagal -- $($login.resMsg)" -ForegroundColor Red; exit 1 }
$script:TOKEN = $login.data.token
Write-Host "Login SuperAdmin OK`n"

# ── Semester aktif (untuk cek keterhubungan guru/siswa) ──
$sem = (Api GET "akademik/semester/aktif").data
$tahun = $sem.tahunAjaran; $semester = $sem.semester
Write-Host "Semester aktif: $tahun sem $semester`n"

# ── 1-2. Admin & Karyawan biasa ──
Ensure-Account "Akun Test Admin" "akuntest.admin@example.com" "Admin" "AdminTest123" | Out-Null

# URUTAN PENTING: record karyawan dibuat DULU, baru passwordnya dipastikan.
# `POST /karyawan` sekalian membuat akun user-nya; kalau akun itu sudah dibuat
# lebih dulu lewat `register`, insert user-nya bentrok dan seluruh permintaan
# gagal 500 — sementara baris `karyawans` terlanjur masuk (tidak ada transaksi
# lintas-service). Pola ini sama dengan blok Administrator Sekolah di bawah.
#
# Karyawan biasa juga butuh record DOMAIN, bukan sekadar akun ber-role.
# Satu-satunya hak yang tersisa untuknya adalah `rekap/pegawai/saya`, dan endpoint
# itu meresolve subjek dari email token ke tabel `karyawans`. Tanpa record ini
# hasilnya 404 — gate role-nya lolos, tapi tidak ada yang bisa diuji, dan capture
# API pun merekam 404 seolah karyawan biasa memang tidak berhak.
$kwBiasaEmail = "akuntest.karyawan@example.com"
$adaKB = @((Api GET "karyawan/all`?per_page=100&search=$([uri]::EscapeDataString($kwBiasaEmail))").data.data |
           Where-Object { $_.email -eq $kwBiasaEmail })
if (@($adaKB).Count -eq 0) {
    # Akun user-nya SUDAH ADA tapi record karyawannya belum. Selama akunnya
    # AKTIF, `POST /karyawan` ditolak 422 oleh guard anti data-yatim ("email
    # dipakai akun aktif") — dan itu memang benar.
    #
    # Dulu ini jalan buntu: `users` memakai soft delete sedangkan `users.email`
    # unik di level DB, sehingga menghapus akunnya membuat emailnya terkunci
    # selamanya. Sejak email bisa dipakai ulang, `POST /karyawan` untuk email
    # milik akun TERHAPUS justru memulihkan baris akun yang SAMA (id tetap)
    # sekaligus membuat record karyawannya. Jadi urutannya: hapus akunnya dulu,
    # baru POST /karyawan. Tanpa perubahan perilaku API apa pun.
    $uLama = @((Api GET "users`?search=$([uri]::EscapeDataString($kwBiasaEmail))&per_page=5").data.data |
               Where-Object { $_.email -eq $kwBiasaEmail })
    $idLama = $null
    if ($uLama.Count -gt 0) {
        $idLama = $uLama[0].id
        $del = Api DELETE "users/$idLama"
        if ($del.resCode -ne 202) {
            Write-Host "  [GAGAL   ] Lepas akun $kwBiasaEmail untuk ditautkan ulang: $($del.resCode) $($del.resMsg)" -ForegroundColor Red
        }
    }
    $stampKB = Get-Date -Format "HHmmss"
    $mkKB = Api POST "karyawan" @{
        email = $kwBiasaEmail; nip = "8$stampKB"; namaLengkap = "Akun Test Karyawan Biasa"
        jabatan = "Satpam"; isAdminSekolah = $false
    }
    if ($mkKB.resCode -eq 201) {
        $idBaru = Find-UserId $kwBiasaEmail
        $catatanId = if ($idLama -and "$idBaru" -eq "$idLama") { " -- akun id=$idLama dipulihkan, bukan dibuat baru" } else { "" }
        Write-Host "  [TAUTKAN ] Record karyawan biasa : $kwBiasaEmail (karyawan id=$($mkKB.data.idKaryawan))$catatanId" -ForegroundColor Green
    } else {
        Write-Host "  [GAGAL   ] Record karyawan biasa : $($mkKB.resCode) $($mkKB.resMsg)" -ForegroundColor Red
        # Jangan tinggalkan akun dalam keadaan terhapus: pulihkan apa adanya.
        if ($idLama) { Api POST "users/$idLama/restore" | Out-Null }
    }
} else {
    # Pastikan penandanya TETAP mati — akun ini gunanya sebagai pembanding
    Api POST "karyawan/update" @{ idKaryawan = $adaKB[0].idKaryawan; isAdminSekolah = $false } | Out-Null
    Write-Host "  [ADA     ] Record karyawan biasa : $kwBiasaEmail (karyawan id=$($adaKB[0].idKaryawan))"
}
# Membuat karyawan otomatis membuat/memulihkan akun user-nya dengan password
# turunan email + wajib-ganti, jadi passwordnya dipastikan ulang di sini.
Ensure-Account "Akun Test Karyawan" $kwBiasaEmail "Karyawan" "KaryawanTest123" | Out-Null

# -- 2b. Administrator Sekolah: karyawan NYATA bertanda isAdminSekolah --
# Bedanya dengan akun karyawan biasa di atas hanya pada penanda isAdminSekolah.
# Penanda itu yang membuka menu manajemen di app, jadi sesi Android butuh
# keduanya untuk membuktikan gating membaca penanda, bukan role.
$adminSekEmail = "akuntest.adminsekolah@example.com"
$adaTU = @((Api GET "karyawan/all`?per_page=100&search=$([uri]::EscapeDataString($adminSekEmail))").data.data)
if (@($adaTU).Count -eq 0) {
    $stamp = Get-Date -Format "HHmmss"
    $mk = Api POST "karyawan" @{
        email = $adminSekEmail; nip = "9$stamp"; namaLengkap = "Akun Test Administrator Sekolah"
        jabatan = "Tata Usaha"; isAdminSekolah = $true
    }
    if ($mk.resCode -eq 201) { Write-Host "  [BUAT    ] Administrator Sekolah : $adminSekEmail (karyawan id=$($mk.data.idKaryawan))" }
    else { Write-Host "  [GAGAL   ] Administrator Sekolah : $($mk.resCode) $($mk.resMsg)" -ForegroundColor Yellow }
} else {
    # Pastikan penandanya menyala walau karyawannya sudah ada dari sesi sebelumnya
    Api POST "karyawan/update" @{ idKaryawan = $adaTU[0].idKaryawan; isAdminSekolah = $true } | Out-Null
    Write-Host "  [ADA     ] Administrator Sekolah : $adminSekEmail (karyawan id=$($adaTU[0].idKaryawan))"
}
Ensure-Account "Akun Test Administrator Sekolah" $adminSekEmail "Karyawan" "AdminSekolahTest123" | Out-Null

# -- 2c. Petugas Acara: akun yang HANYA boleh mengelola agenda --
# Sesi app butuh akun ini untuk menguji gating menu Kalender DAN untuk membuktikan
# bahwa akun tersebut 403 di mana-mana selain acara. Tanpa akun seeded, perilaku
# paling mudah salah di app (warm-up cache yang memuntahkan 403) tak bisa diuji.
#
# Penandanya hanya bisa dinyalakan SuperAdmin/Admin, dan seed berjalan sebagai
# SuperAdmin — jadi dikirim langsung lewat register.
$petugasEmail = "akuntest.petugasacara@example.com"
$petugasPw    = "PetugasAcaraTest123"
$adaPA = @((Api GET "users`?search=$([uri]::EscapeDataString($petugasEmail))&per_page=5").data.data |
           Where-Object { $_.email -eq $petugasEmail })
if (@($adaPA).Count -eq 0) {
    $mkPA = Api POST "register" @{
        name = "Akun Test Petugas Acara"; email = $petugasEmail
        password = $petugasPw; confirm_password = $petugasPw
        role = "Karyawan"; isPetugasAcara = $true
    }
    if ($mkPA.resCode -eq 201) { Write-Host "  [BUAT    ] Petugas Acara : $petugasEmail" }
    else { Write-Host "  [GAGAL   ] Petugas Acara : $($mkPA.resCode) $($mkPA.resMsg)" -ForegroundColor Yellow }
} else {
    Write-Host "  [ADA     ] Petugas Acara : $petugasEmail"
}
Ensure-Account "Akun Test Petugas Acara" $petugasEmail "Karyawan" $petugasPw | Out-Null

# ── 3. Guru: pilih guru asli yang punya pengampu aktif (fallback: guru pertama) ──
$gurus = @((Api GET "guru/all`?per_page=50").data.data)
$guruPick = $null
foreach ($g in $gurus) {
    $m = Api GET "akademik/guru/$($g.idGuru)/mapel`?tahun_ajaran=$([uri]::EscapeDataString($tahun))&semester=$semester"
    if (@($m.data).Count -gt 0) { $guruPick = $g; break }
}
if (-not $guruPick -and $gurus.Count -gt 0) { $guruPick = $gurus[0] }
$guruEmail = $null
if ($guruPick) {
    $guruEmail = $guruPick.email
    Ensure-Account $guruPick.namaLengkap $guruEmail "Guru" "GuruTest123" | Out-Null
    Write-Host "           (guru id=$($guruPick.idGuru) - $($guruPick.namaLengkap))"
} else { Write-Host "  [SKIP] Tidak ada guru di database" -ForegroundColor Yellow }

# ── 4. Siswa: pilih siswa asli yang terdaftar di kelas semester ini (fallback: siswa pertama) ──
$siswas = @((Api GET "siswa/all`?per_page=50").data.data)
$siswaPick = $null
foreach ($s in $siswas) {
    $k = Api GET "akademik/siswa/$($s.idSiswa)/kelas`?tahun_ajaran=$([uri]::EscapeDataString($tahun))&semester=$semester"
    if (@($k.data).Count -gt 0) { $siswaPick = $s; break }
}
if (-not $siswaPick -and $siswas.Count -gt 0) { $siswaPick = $siswas[0] }
$siswaEmail = $null
if ($siswaPick) {
    # Email siswa hanya ada di endpoint detail
    $detail = (Api GET "siswa`?idSiswa=$($siswaPick.idSiswa)").data
    $siswaEmail = $detail.email
    Ensure-Account $siswaPick.namaLengkap $siswaEmail "Siswa" "SiswaTest123" | Out-Null
    Write-Host "           (siswa id=$($siswaPick.idSiswa) - $($siswaPick.namaLengkap))"
} else { Write-Host "  [SKIP] Tidak ada siswa di database" -ForegroundColor Yellow }


# ── 5. Nilai siswa test di semester AKTIF ──
# Tanpa ini layar "Nilai Saya" / raport di app hanya pernah diuji dalam keadaan
# KOSONG — tampilan berisi data (rata-rata, slot ulangan yang sebagian terisi,
# nilai akhir) tidak pernah terlihat. Diisi lewat API, bukan DB, supaya rata-rata
# harian dan nilai akhir dihitung oleh logika yang sama dengan produksi.
#
# HANYA mengisi pasangan (siswa_kelas, pengampu) yang BELUM punya nilai.
# `POST /akademik/nilai` untuk slot yang sudah ada melakukan upsert, jadi kalau
# dikirim ulang tiap run, nilai yang sengaja diedit penguji akan tertimpa diam-diam.
if ($siswaPick) {
    $kSaya = @((Api GET "akademik/siswa/$($siswaPick.idSiswa)/kelas").data |
               Where-Object { $_.tahunAjaran -eq $tahun -and "$($_.semester)" -eq "$semester" })[0]
    if (-not $kSaya) {
        Write-Host "  [SKIP    ] Nilai siswa: $siswaEmail tidak terdaftar di kelas mana pun pada $tahun sem $semester" -ForegroundColor Yellow
    } else {
        $pengampuKelas = @((Api GET "akademik/kelas/$($kSaya.kelasId)/pengampu").data |
                           Where-Object { $_.tahunAjaran -eq $tahun -and "$($_.semester)" -eq "$semester" })
        $sudahAda = @((Api GET "akademik/nilai/siswa/$($siswaPick.idSiswa)`?tahun_ajaran=$([uri]::EscapeDataString($tahun))&semester=$semester").data)
        $pengampuTerisi = @($sudahAda | ForEach-Object {
            if ($_.pengampuMapelId) { "$($_.pengampuMapelId)" } elseif ($_.pengampu_mapel_id) { "$($_.pengampu_mapel_id)" }
        })

        # Pola sengaja bervariasi: slot ulangan tidak penuh (yang kosong = null)
        # supaya tampilan "5 slot, sebagian belum ada" ikut teruji.
        $pola = @(
            @{ nilai_harian_1 = 80; nilai_harian_2 = 85; nilai_harian_3 = 78; nilai_uts = 82; nilai_uas = 88 },
            @{ nilai_harian_1 = 75; nilai_harian_2 = 90; nilai_uts = 70; nilai_uas = 85 },
            @{ nilai_harian_1 = 92; nilai_harian_2 = 88; nilai_harian_3 = 95; nilai_harian_4 = 90; nilai_uts = 89; nilai_uas = 93 }
        )
        if ($pengampuKelas.Count -eq 0) {
            Write-Host "  [SKIP    ] Nilai siswa: kelas $($kSaya.namaKelas) belum punya pengampu di $tahun sem $semester" -ForegroundColor Yellow
        }
        $i = 0
        foreach ($pm in $pengampuKelas) {
            $nama = "$($pm.namaMapel)"
            if ($pengampuTerisi -contains "$($pm.idPengampuMapel)") {
                Write-Host "  [ADA     ] Nilai $nama untuk $($siswaPick.namaLengkap) (tidak ditimpa)"
            } else {
                $body = @{ siswa_kelas_id = $kSaya.idSiswaKelas; pengampu_mapel_id = $pm.idPengampuMapel } + $pola[$i % $pola.Count]
                $rn = Api POST "akademik/nilai" $body
                if ($rn.resCode -eq 201) {
                    Write-Host "  [BUAT    ] Nilai $nama untuk $($siswaPick.namaLengkap) (akhir=$($rn.data.nilaiAkhir))" -ForegroundColor Green
                } else {
                    Write-Host "  [GAGAL   ] Nilai $nama : $($rn.resCode) $($rn.resMsg)" -ForegroundColor Red
                }
            }
            $i++
        }
    }
}

# ── Ringkasan ──
Write-Host ""
Write-Host "=============================================================="
if ($script:GAGAL -gt 0) {
    Write-Host "  PERHATIAN: $($script:GAGAL) akun GAGAL disiapkan - lihat baris [GAGAL] di atas" -ForegroundColor Red
}
Write-Host "  AKUN TEST SIAP DIPAKAI"
Write-Host "  Admin    : akuntest.admin@example.com    / AdminTest123"
Write-Host "  Karyawan : akuntest.karyawan@example.com / KaryawanTest123"
Write-Host "  Petugas Acara : akuntest.petugasacara@example.com / PetugasAcaraTest123 (HANYA boleh kelola acara)"
Write-Host "  Adm.Sek  : akuntest.adminsekolah@example.com / AdminSekolahTest123"
Write-Host "             (karyawan bertanda isAdminSekolah - boleh manajemen akademik)"
if ($guruEmail)  { Write-Host "  Guru     : $guruEmail / GuruTest123" }
if ($siswaEmail) { Write-Host "  Siswa    : $siswaEmail / SiswaTest123" }
Write-Host "=============================================================="
