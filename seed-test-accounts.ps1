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

$BaseUrl = "https://gateway.test/api"

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
    try {
        return (Invoke-WebRequest @params).Content | ConvertFrom-Json
    } catch {
        $er = $_.Exception.Response
        if ($er) {
            try {
                $rd = [System.IO.StreamReader]::new($er.GetResponseStream())
                $tx = $rd.ReadToEnd(); $rd.Close(); $er.Close()
                if ($tx) { return $tx | ConvertFrom-Json }
            } catch {}
        }
        return [PSCustomObject]@{ resCode = 0; resMsg = $_.Exception.Message; data = $null }
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
# must_change_password = true, dan reset password oleh admin TIDAK melepasnya —
# itu memang disengaja demi keamanan. Tapi untuk akun TEST hasilnya bikin repot:
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
        # Kalau akun masih ber-flag, lepaskan supaya langsung bisa dipakai menguji
        $cek = Api POST "login" @{ email = $Email; password = $Password; device_name = "seed-script" }
        if ($cek.resCode -eq 200 -and $cek.data.mustChangePassword) {
            if (Clear-MustChangePassword $Email $id $Password) {
                Write-Host "  [RESET   ] $Role : $Email (flag ganti-password dilepas)" -ForegroundColor Green
            } else {
                Write-Host "  [RESET   ] $Role : $Email (PERINGATAN: masih wajib ganti password)" -ForegroundColor Yellow
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
$adaKB = @((Api GET "karyawan/all`?per_page=100&search=$([uri]::EscapeDataString($kwBiasaEmail))").data.data)
if (@($adaKB).Count -eq 0) {
    $stampKB = Get-Date -Format "HHmmss"
    $mkKB = Api POST "karyawan" @{
        email = $kwBiasaEmail; nip = "8$stampKB"; namaLengkap = "Akun Test Karyawan Biasa"
        jabatan = "Satpam"; isAdminSekolah = $false
    }
    if ($mkKB.resCode -eq 201) { Write-Host "  [BUAT    ] Record karyawan biasa : $kwBiasaEmail (karyawan id=$($mkKB.data.idKaryawan))" }
    else { Write-Host "  [GAGAL   ] Record karyawan biasa : $($mkKB.resCode) $($mkKB.resMsg)" -ForegroundColor Yellow }
} else {
    # Pastikan penandanya TETAP mati — akun ini gunanya sebagai pembanding
    Api POST "karyawan/update" @{ idKaryawan = $adaKB[0].idKaryawan; isAdminSekolah = $false } | Out-Null
    Write-Host "  [ADA     ] Record karyawan biasa : $kwBiasaEmail (karyawan id=$($adaKB[0].idKaryawan))"
}
# Membuat karyawan otomatis membuat/mereset akun user-nya, jadi passwordnya
# dipastikan ulang setelah blok di atas.
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

# ── Ringkasan ──
Write-Host ""
Write-Host "=============================================================="
Write-Host "  AKUN TEST SIAP DIPAKAI"
Write-Host "  Admin    : akuntest.admin@example.com    / AdminTest123"
Write-Host "  Karyawan : akuntest.karyawan@example.com / KaryawanTest123"
Write-Host "  Adm.Sek  : akuntest.adminsekolah@example.com / AdminSekolahTest123"
Write-Host "             (karyawan bertanda isAdminSekolah - boleh manajemen akademik)"
if ($guruEmail)  { Write-Host "  Guru     : $guruEmail / GuruTest123" }
if ($siswaEmail) { Write-Host "  Siswa    : $siswaEmail / SiswaTest123" }
Write-Host "=============================================================="
