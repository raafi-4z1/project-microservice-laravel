# =============================================================
#  MICROSERVICE LARAVEL — COMPREHENSIVE TEST SUITE
#  Usage : powershell -ExecutionPolicy Bypass -File run-tests.ps1
#  Edit bagian CONFIG di bawah untuk menyesuaikan environment.
# =============================================================

# ==================== CONFIG ====================
# Kredensial TIDAK ditulis di file ini agar aman di-commit ke git.
# Set environment variable sebelum menjalankan:
#   $env:TEST_ADMIN_EMAIL    = "superadmin@example.com"
#   $env:TEST_ADMIN_PASSWORD = "PasswordSuperAdmin"
#   powershell -ExecutionPolicy Bypass -File run-tests.ps1
#
# Uji dari PC lain di LAN: arahkan ke IP backend + Host header gateway.test
#   $env:TEST_BASE_URL = "https://192.168.x.x/api"   # ganti dengan IP server
#   (SSL bypass sudah aktif; jika cert bukan untuk IP, request tetap jalan)
$CFG = @{
    BaseUrl       = $(if ($env:TEST_BASE_URL) { $env:TEST_BASE_URL } else { "https://gateway.test/api" })

    # Kredensial SuperAdmin (wajib) — dari environment variable
    AdminEmail    = $(if ($env:TEST_ADMIN_EMAIL)    { $env:TEST_ADMIN_EMAIL }    else { "superadmin@example.com" })
    AdminPassword = $env:TEST_ADMIN_PASSWORD

    # Semester yang akan digunakan untuk test Akademik
    # Kosongkan agar otomatis ambil dari /semester/aktif
    TahunAjaran   = ""          # contoh: "2024/2025"
    Semester      = ""          # contoh: "2"

    # Hapus data test setelah selesai? ($true / $false)
    SkipCleanup   = $false

    # Timeout per request (detik)
    Timeout       = 30
}
# ================================================

# Guard: hentikan jika password belum di-set
if (-not $CFG.AdminPassword) {
    Write-Host "ERROR: Password SuperAdmin belum di-set." -ForegroundColor Red
    Write-Host 'Jalankan dulu:  $env:TEST_ADMIN_PASSWORD = "PasswordSuperAdmin"' -ForegroundColor Yellow
    exit 1
}

# ──────────────────────────────────────────────
#  SSL BYPASS — untuk domain *.test lokal
# ──────────────────────────────────────────────
try {
    Add-Type @"
using System.Net;
using System.Security.Cryptography.X509Certificates;
public class TrustAllCerts : ICertificatePolicy {
    public bool CheckValidationResult(ServicePoint sp, X509Certificate cert, WebRequest req, int prob) { return true; }
}
"@
} catch {}
[System.Net.ServicePointManager]::CertificatePolicy = New-Object TrustAllCerts
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

# ──────────────────────────────────────────────
#  STATE
# ──────────────────────────────────────────────
$script:TOKEN    = $null   # token SuperAdmin
$script:PASS     = 0
$script:FAIL     = 0
$script:SKIP     = 0
$script:CREATED  = @{}     # track ID yang dibuat untuk cleanup
$script:LAST_CHK = $false  # hasil Chk terakhir (digunakan oleh if ($script:LAST_CHK))
$script:LOGINS   = @()     # timestamp login untuk pacing (throttle 5/menit)

# Timestamp unik agar nama test data tidak bentrok
$TS = (Get-Date -Format "HHmmss")

# ──────────────────────────────────────────────
#  HELPER FUNCTIONS
# ──────────────────────────────────────────────
# Jaga agar login < 5/menit (endpoint /login & /oauth/token di-throttle).
# Suite melakukan banyak login; lewat LAN yang cepat bisa menumpuk dalam satu
# jendela 60s dan kena 429. Guard ini menahan login ke-5 sampai yang tertua lewat.
function Wait-LoginSlot {
    $cutoff = (Get-Date).AddSeconds(-60)
    $script:LOGINS = @($script:LOGINS | Where-Object { $_ -gt $cutoff })
    if ($script:LOGINS.Count -ge 4) {
        $oldest = [DateTime]($script:LOGINS | Sort-Object | Select-Object -First 1)
        $wait = [int][math]::Ceiling(($oldest.AddSeconds(61) - (Get-Date)).TotalSeconds)
        if ($wait -gt 0) {
            Write-Host "    (pacing: tunggu ${wait}s agar login tetap < 5/menit)" -ForegroundColor DarkGray
            Start-Sleep -Seconds $wait
        }
        $cutoff = (Get-Date).AddSeconds(-60)
        $script:LOGINS = @($script:LOGINS | Where-Object { $_ -gt $cutoff })
    }
    $script:LOGINS += (Get-Date)
}

function Api {
    param(
        [string]$Method,
        [string]$Path,
        [hashtable]$Body   = $null,
        [string]$Token     = $null,
        [string]$BodyType  = "json",  # json | form
        [hashtable]$ExtraHeaders = $null  # header tambahan (mis. X-Terminal-Id/Token)
    )
    # Pacing untuk endpoint login (throttle 5/menit) — lapis pertama cegah 429
    $isLogin = ($Path -eq "login" -or $Path -like "login`?*")
    if ($isLogin) { Wait-LoginSlot }

    $tok = if ($Token) { $Token } else { $script:TOKEN }
    $headers = @{ 'Accept' = 'application/json' }
    if ($tok) { $headers['Authorization'] = "Bearer $tok" }
    if ($ExtraHeaders) { foreach ($k in $ExtraHeaders.Keys) { $headers[$k] = $ExtraHeaders[$k] } }
    $uri = "$($CFG.BaseUrl)/$Path"
    $bodyJson = if ($Body -ne $null) { $Body | ConvertTo-Json -Depth 6 } else { $null }
    if ($bodyJson) { $headers['Content-Type'] = 'application/json' }

    $attempt = 0
    while ($true) {
        $attempt++
        $params = @{ Uri = $uri; Method = $Method; Headers = $headers; UseBasicParsing = $true; TimeoutSec = $CFG.Timeout }
        if ($bodyJson) { $params['Body'] = $bodyJson }
        $resp = $null
        try {
            $r = Invoke-WebRequest @params
            $resp = $r.Content | ConvertFrom-Json
        } catch {
            $er = $_.Exception.Response
            if ($er) {
                try {
                    $st = $er.GetResponseStream()
                    $rd = [System.IO.StreamReader]::new($st)
                    $tx = $rd.ReadToEnd(); $rd.Close(); $st.Close(); $er.Close()
                    if ($tx) { $resp = $tx | ConvertFrom-Json }
                } catch {}
            }
            if (-not $resp) { $resp = [PSCustomObject]@{ resCode = 0; resMsg = $_.Exception.Message; data = $null } }
        }
        # Login kena 429 (rate limit) -> tunggu jendela reset, coba lagi.
        # Mencegah token null yang men-cascade jadi kegagalan lintas-phase.
        if ($isLogin -and $resp.resCode -eq 429 -and $attempt -le 2) {
            Write-Host "    (login 429 — tunggu 62s lalu coba lagi #$attempt)" -ForegroundColor DarkGray
            Start-Sleep -Seconds 62
            continue
        }
        return $resp
    }
}

function Chk {
    param([string]$Label, $Resp, [int]$Expect, [switch]$Silent)
    $code = $Resp.resCode
    if ($code -eq $Expect) {
        if (-not $Silent) { Write-Host "  [PASS $code] $Label" -ForegroundColor Green }
        $script:PASS++
        $script:LAST_CHK = $true
    } else {
        $msg = "(no msg)"
        if ($Resp.resMsg)    { $msg = $Resp.resMsg }
        elseif ($Resp.message) { $msg = $Resp.message }
        Write-Host "  [FAIL $code/$Expect] $Label -- $msg" -ForegroundColor Red
        $script:FAIL++
        $script:LAST_CHK = $false
    }
}

function Skip {
    param([string]$Label, [string]$Reason = "")
    $m = "  [SKIP] $Label"
    if ($Reason) { $m += " -- $Reason" }
    Write-Host $m -ForegroundColor Yellow
    $script:SKIP++
}

function Section([string]$Title) {
    Write-Host ""
    Write-Host "═══ $Title" -ForegroundColor Cyan
}

function Info([string]$Msg) {
    Write-Host "    $Msg" -ForegroundColor DarkGray
}

function NullOr($val, $default) {
    if ($null -ne $val -and $val -ne '') { return $val } else { return $default }
}

# ──────────────────────────────────────────────
#  PHASE 0 — AUTHENTICATION
# ──────────────────────────────────────────────
Section "Phase 0: Authentication"

# 0-A. Login gagal — wrong password
$r = Api POST "login" @{ email = $CFG.AdminEmail; password = "SalahPassword999" }
Chk "Login password salah (harus 400)" $r 400

# 0-B. Login gagal — email tidak ada
$r = Api POST "login" @{ email = "tidakada@example.com"; password = "Password123" }
Chk "Login email tidak ada (harus 400)" $r 400

# 0-C. Akses protected route tanpa token → 401
$r = Api GET "user"
Chk "Akses /user tanpa token (harus 401)" $r 401

# 0-D. Login SuperAdmin berhasil
$r = Api POST "login" @{ email = $CFG.AdminEmail; password = $CFG.AdminPassword }
Chk "Login SuperAdmin (harus 200)" $r 200; if ($script:LAST_CHK) {
    $script:TOKEN = $r.data.token
    Info "Token acquired | role=$($r.data.role)"

    # Response login harus punya field mustChangePassword (false untuk SuperAdmin)
    if ($r.data.PSObject.Properties.Name -contains 'mustChangePassword' -and $r.data.mustChangePassword -eq $false) {
        $script:PASS++; Write-Host "  [PASS] Field mustChangePassword=false di response login" -ForegroundColor Green
    } else {
        $script:FAIL++; Write-Host "  [FAIL] Field mustChangePassword hilang/salah di response login (nilai: $($r.data.mustChangePassword))" -ForegroundColor Red
    }
} else {
    Write-Host "FATAL: Login SuperAdmin gagal — test tidak bisa dilanjutkan." -ForegroundColor Red
    exit 1
}

# 0-E. GET /user — profil sendiri
$r = Api GET "user"
Chk "GET /user (profil sendiri)" $r 200
if ($r.data) { Info "User: $($r.data.email) | role=$($r.data.role)" }

# 0-F. Akses dengan token tidak valid → 401
$r = Api GET "user" -Token "invalid_token_xyz"
Chk "Akses /user dengan token invalid (harus 401)" $r 401

# ──────────────────────────────────────────────
#  PHASE 1 — USER MANAGEMENT & REGISTER
# ──────────────────────────────────────────────
Section "Phase 1: User Management & Register"

$testUserEmail    = "testuser_${TS}@example.com"
$testUserPassword = "TestPass123"
$testUserId       = $null

# 1-A. Register user baru (role Admin)
$r = Api POST "register" @{
    name             = "Test User $TS"
    email            = $testUserEmail
    password         = $testUserPassword
    confirm_password = $testUserPassword
    role             = "Admin"
}
Chk "POST /register (buat Admin test)" $r 201; if ($script:LAST_CHK) {
    Info "Registered: $testUserEmail"
}

# 1-B. Register ulang email yang sama → 422
$r = Api POST "register" @{
    name             = "Duplikat"
    email            = $testUserEmail
    password         = $testUserPassword
    confirm_password = $testUserPassword
    role             = "Admin"
}
Chk "POST /register email duplikat (harus 422)" $r 422

# 1-C. Register password tidak valid
$r = Api POST "register" @{
    name             = "Bad Pass"
    email            = "badpass_${TS}@example.com"
    password         = "abc"
    confirm_password = "abc"
    role             = "Guru"
}
Chk "POST /register password lemah (harus 422)" $r 422

# 1-D. Register role tidak valid
$r = Api POST "register" @{
    name             = "Bad Role"
    email            = "badrole_${TS}@example.com"
    password         = $testUserPassword
    confirm_password = $testUserPassword
    role             = "Hacker"
}
Chk "POST /register role tidak valid (harus 422)" $r 422

# 1-E. GET /users (paginated: items di $r.data.data, ambil per_page besar agar user baru ke-capture)
$r = Api GET "users`?per_page=200"
Chk "GET /users (list)" $r 200; if ($script:LAST_CHK) {
    $userList = @($r.data.data)
    Info "Total users: $($r.data.total)"
    foreach ($u in $userList) {
        if ($u.email -eq $testUserEmail) { $testUserId = $u.id; break }
    }
    if ($testUserId) { Info "Test user id=$testUserId" }
}

# 1-E2. GET /users?search= — cari user test by email unik
$r = Api GET "users`?search=testuser_$TS"
Chk "GET /users?search=testuser_$TS" $r 200; if ($script:LAST_CHK) {
    $found = @($r.data.data) | Where-Object { $_.email -eq $testUserEmail }
    if (@($found).Count -eq 1) { $script:PASS++; Write-Host "  [PASS] Search users menemukan test user by email" -ForegroundColor Green }
    else { $script:FAIL++; Write-Host "  [FAIL] Search users tidak menemukan $testUserEmail (total=$($r.data.total))" -ForegroundColor Red }
}

# Search tanpa hasil → total 0
$r = Api GET "users`?search=ZZZTIDAKADA999"
Chk "GET /users?search=ZZZTIDAKADA999 (harus 200, kosong)" $r 200; if ($script:LAST_CHK) {
    if ($r.data.total -eq 0) { $script:PASS++; Write-Host "  [PASS] Search users tanpa hasil: total=0" -ForegroundColor Green }
    else { $script:FAIL++; Write-Host "  [FAIL] Search ZZZTIDAKADA999 total=$($r.data.total) (harus 0)" -ForegroundColor Red }
}

# 1-F. GET /users/{id}
if ($testUserId) {
    $r = Api GET "users/$testUserId"
    Chk "GET /users/{id}" $r 200
} else { Skip "GET /users/{id}" "user id tidak ditemukan" }

# 1-G. Reset password user test
$resetPwDone = $false
if ($testUserId) {
    $newPw = "NewPass456"
    $r = Api POST "users/$testUserId/password" @{ new_password = $newPw; confirm_password = $newPw }
    Chk "POST /users/{id}/password (reset)" $r 200
    if ($script:LAST_CHK) { $resetPwDone = $true }
}

# 1-H. Login sebagai user test → dapatkan token untuk role test
$testToken = $null
$loginPw   = if ($resetPwDone) { "NewPass456" } else { $testUserPassword }
$loginTest = Api POST "login" @{ email = $testUserEmail; password = $loginPw }
Chk "Login sebagai Admin test" $loginTest 200; if ($script:LAST_CHK) {
    $testToken = $loginTest.data.token
    Info "Admin test token acquired"
}

# 1-I. POST /password — ganti password sendiri (pakai testToken)
if ($testToken) {
    $currentPw = if ($resetPwDone) { "NewPass456" } else { $testUserPassword }

    # Salah current_password → 422
    $r = Api POST "password" @{ current_password = "PasswordSalah999"; new_password = "NewPass789"; confirm_password = "NewPass789" } -Token $testToken
    Chk "POST /password current_password salah (harus 422)" $r 422

    # new_password sama dengan current → 422
    $r = Api POST "password" @{ current_password = $currentPw; new_password = $currentPw; confirm_password = $currentPw } -Token $testToken
    Chk "POST /password new sama dengan current (harus 422)" $r 422

    # confirm_password tidak cocok → 422
    $r = Api POST "password" @{ current_password = $currentPw; new_password = "NewSelf789"; confirm_password = "Mismatch789" } -Token $testToken
    Chk "POST /password confirm tidak cocok (harus 422)" $r 422

    # Berhasil ganti → 200, lalu re-login verifikasi
    $newSelfPw = "SelfNew789"
    $r = Api POST "password" @{ current_password = $currentPw; new_password = $newSelfPw; confirm_password = $newSelfPw } -Token $testToken
    Chk "POST /password ganti password sendiri (harus 200)" $r 200; if ($script:LAST_CHK) {
        Info "Password berhasil diganti ke $newSelfPw (token tetap valid — changePassword tidak mencabut token)"
    }
}

# 1-J. POST /refresh — tukar token valid dengan token baru (token lama dicabut)
$r = Api POST "refresh" -Token "invalid-token-xyz"
Chk "POST /refresh dengan token invalid (harus 401)" $r 401

if ($testToken) {
    $oldToken = $testToken
    $r = Api POST "refresh" -Token $testToken
    Chk "POST /refresh dengan token valid (harus 200)" $r 200; if ($script:LAST_CHK) {
        $testToken = $r.data.token
        Info "Token baru diterima untuk $($r.data.email)"

        # Token lama harus sudah dicabut → 401
        $r = Api GET "user" -Token $oldToken
        Chk "GET /user dengan token lama setelah refresh (harus 401)" $r 401

        # Token baru harus valid → 200
        $r = Api GET "user" -Token $testToken
        Chk "GET /user dengan token baru setelah refresh (harus 200)" $r 200
    }
}

# 1-K. Logout SuperAdmin → re-login
$r = Api POST "logout"
Chk "POST /logout SuperAdmin" $r 200

$relogin = Api POST "login" @{ email = $CFG.AdminEmail; password = $CFG.AdminPassword }
Chk "Re-login SuperAdmin setelah logout" $relogin 200; if ($script:LAST_CHK) {
    $script:TOKEN = $relogin.data.token
}

# ──────────────────────────────────────────────
#  PHASE 2 — CLASS CRUD
# ──────────────────────────────────────────────
Section "Phase 2: Class (Kelas) CRUD"

# 2-A. GET /class/all
$r = Api GET "class/all"
Chk "GET /class/all" $r 200; if ($script:LAST_CHK) {
    Info "Total kelas: $($r.data.total)"
}

# Ambil kelas pertama untuk test show
$kelasId = $null
if ($r.data -and $r.data.data -and $r.data.data.Count -gt 0) {
    $kelasId = $r.data.data[0].idKelas
    Info "Gunakan idKelas=$kelasId untuk test"
}

# 2-B. GET /class?idKelas={id}
if ($kelasId) {
    $r = Api GET "class`?idKelas=$kelasId"
    Chk "GET /class?idKelas=$kelasId (show)" $r 200
} else { Skip "GET /class?idKelas=" "tidak ada kelas di database" }

# 2-C. POST /class — buat kelas test
$r = Api POST "class" @{ noKelas = 99; tingkat = 1; jurusan = "MIPA"; limitSiswa = 30 }
$testKelasId = $null
if ($r.resCode -eq 201) {
    Chk "POST /class (buat kelas test)" $r 201
    $testKelasId = $r.data.idKelas
    $script:CREATED['kelas'] = $testKelasId
    Info "Created idKelas=$testKelasId"
} elseif ($r.resCode -eq 409) {
    $script:PASS++
    Write-Host "  [PASS] POST /class -- kelas sudah ada (409), skip create test" -ForegroundColor Green
    # Ambil id dari existing
    $all = (Api GET "class/all?per_page=100").data.data
    foreach ($k in $all) { if ($k.namaKelas -like "*MIPA*" -and $k.tingkat -eq 1) { $testKelasId = $k.idKelas; break } }
} else {
    Chk "POST /class (buat kelas test)" $r 201
}

# 2-D. POST /class/update — update kelas test
if ($testKelasId) {
    $r = Api POST "class/update" @{ idKelas = $testKelasId; limitSiswa = 35 }
    Chk "POST /class/update (ubah limitSiswa)" $r 202
}

# 2-E. POST /class tanpa field wajib → 422
$r = Api POST "class" @{ tingkat = 1 }
Chk "POST /class tanpa field wajib (harus 422)" $r 422

# 2-E2. GET /class/all?search= — cari by jurusan (kelas test jurusan=MIPA)
$r = Api GET "class/all`?search=MIPA"
Chk "GET /class/all?search=MIPA" $r 200; if ($script:LAST_CHK) {
    if ($r.data.total -ge 1) { $script:PASS++; Write-Host "  [PASS] Search class jurusan=MIPA menemukan $($r.data.total) kelas" -ForegroundColor Green }
    else { $script:FAIL++; Write-Host "  [FAIL] Search class jurusan=MIPA total=0 (harus >= 1)" -ForegroundColor Red }
}

# 2-F. Role test: Admin test bisa POST /class
if ($testToken) {
    $r = Api POST "class" @{ noKelas = 98; tingkat = 2; jurusan = "IPS"; limitSiswa = 25 } -Token $testToken
    if ($r.resCode -eq 201) {
        Chk "POST /class sebagai Admin (harus 201)" $r 201
        # Langsung hapus
        $tmpKelasId = $r.data.idKelas
        Api DELETE "class/$tmpKelasId" | Out-Null
    } elseif ($r.resCode -eq 409) {
        $script:PASS++
        Write-Host "  [PASS] POST /class sebagai Admin -- 409 kelas sudah ada" -ForegroundColor Green
    } else {
        Chk "POST /class sebagai Admin (harus 201/409)" $r 201
    }
}

# ──────────────────────────────────────────────
#  PHASE 3 — MAPEL CRUD
# ──────────────────────────────────────────────
Section "Phase 3: Mapel CRUD"

# 3-A. GET /mapel/all
$r = Api GET "mapel/all"
Chk "GET /mapel/all" $r 200; if ($script:LAST_CHK) {
    Info "Total mapel: $($r.data.total)"
}

$mapelId = $null
if ($r.data -and $r.data.data -and $r.data.data.Count -gt 0) {
    $mapelId = $r.data.data[0].idPelajaran
    Info "Gunakan idPelajaran=$mapelId"
}

# 3-B. GET /mapel?idPelajaran={id}
if ($mapelId) {
    $r = Api GET "mapel`?idPelajaran=$mapelId"
    Chk "GET /mapel?idPelajaran=$mapelId (show)" $r 200
} else { Skip "GET /mapel?idPelajaran=" "tidak ada mapel" }

# 3-C. POST /mapel — buat mapel test
$testKode  = "TST$TS"
$r = Api POST "mapel" @{ kode = $testKode; namaPelajaran = "Test Mapel $TS"; keterangan = "Dibuat oleh run-tests.ps1" }
$testMapelId = $null
if ($r.resCode -eq 201) {
    Chk "POST /mapel (buat mapel test)" $r 201
    $testMapelId = $r.data.idPelajaran
    $script:CREATED['mapel'] = $testMapelId
    Info "Created idPelajaran=$testMapelId kode=$testKode"
} else {
    Chk "POST /mapel (buat mapel test)" $r 201
}

# 3-D. POST /mapel/update
if ($testMapelId) {
    $r = Api POST "mapel/update" @{ idPelajaran = $testMapelId; keterangan = "Updated by test" }
    Chk "POST /mapel/update (update keterangan)" $r 202
}

# 3-E. POST /mapel tanpa field wajib → 422
$r = Api POST "mapel" @{ keterangan = "hanya keterangan" }
Chk "POST /mapel tanpa kode/namaPelajaran (harus 422)" $r 422

# 3-F. GET /mapel/all?search= — kode test unik harus menemukan tepat 1
if ($testMapelId) {
    $r = Api GET "mapel/all`?search=$testKode"
    Chk "GET /mapel/all?search=$testKode" $r 200; if ($script:LAST_CHK) {
        $found = @($r.data.data) | Where-Object { $_.kode -eq $testKode }
        if (@($found).Count -eq 1 -and $r.data.total -eq 1) { $script:PASS++; Write-Host "  [PASS] Search mapel kode=$testKode menemukan tepat 1 hasil" -ForegroundColor Green }
        else { $script:FAIL++; Write-Host "  [FAIL] Search mapel kode=$testKode total=$($r.data.total) (harus 1)" -ForegroundColor Red }
    }
}

# ──────────────────────────────────────────────
#  PHASE 4 — GURU (READ + VALIDATION)
# ──────────────────────────────────────────────
Section "Phase 4: Guru (Read + Validation)"

# 4-A. GET /guru/all
$r = Api GET "guru/all"
Chk "GET /guru/all" $r 200; if ($script:LAST_CHK) {
    Info "Total guru: $($r.data.total)"
}

$guruId = $null
$guruNama = $null
if ($r.data -and $r.data.data -and $r.data.data.Count -gt 0) {
    $guruId = $r.data.data[0].idGuru
    $guruNama = $r.data.data[0].namaLengkap
    Info "Gunakan idGuru=$guruId"
}

# 4-B. GET /guru?idGuru={id}
if ($guruId) {
    $r = Api GET "guru`?idGuru=$guruId"
    Chk "GET /guru?idGuru=$guruId (show)" $r 200
} else { Skip "GET /guru?idGuru=" "tidak ada guru" }

# 4-C. POST /guru tanpa foto → 422 (validasi berjalan)
$r = Api POST "guru" @{
    email = "testguru_${TS}@example.com"; namaLengkap = "Guru Test"; nip = "1234567890"
    nik = "9876543210"; telephone = "08111111111"; jenisKelamin = "Laki-Laki"
    tempatLahir = "Jakarta"; tanggalLahir = "1990-01-01"; alamat = "Jl Test No 1"
    statusKepegawaian = "PNS"; tanggalMasuk = "2020-01-01"; jabatan = "Guru Mata Pelajaran"
    pendidikanTerakhir = "S1"; jurusan = "Informatika"; universitas = "UI"; tahunLulus = "2015"
}
Chk "POST /guru tanpa foto (harus 422)" $r 422

# 4-D. GET /guru/all dengan pagination
$r = Api GET "guru/all`?per_page=2&page=1"
Chk "GET /guru/all?per_page=2 (pagination)" $r 200

# 4-E. POST /guru/update — ID tidak ada → 404
$r = Api POST "guru/update" @{ idGuru = 99999; jabatan = "Test Update" }
$ok = ($r.resCode -eq 404 -or $r.resCode -eq 422)
if ($ok) { $script:PASS++; Write-Host "  [PASS $($r.resCode)] POST /guru/update idGuru=99999 (tidak ada)" -ForegroundColor Green }
else     { $script:FAIL++; Write-Host "  [FAIL $($r.resCode)] POST /guru/update idGuru=99999 -- $($r.resMsg)" -ForegroundColor Red }

# 4-F. DELETE /guru/{id} — ID tidak ada → 404
$r = Api DELETE "guru/99999"
$ok = ($r.resCode -eq 404 -or $r.resCode -eq 422)
if ($ok) { $script:PASS++; Write-Host "  [PASS $($r.resCode)] DELETE /guru/99999 (tidak ada)" -ForegroundColor Green }
else     { $script:FAIL++; Write-Host "  [FAIL $($r.resCode)] DELETE /guru/99999 -- $($r.resMsg)" -ForegroundColor Red }

# 4-G. GET /guru/all?search= — cari by potongan nama guru pertama
if ($guruNama) {
    $kata = ($guruNama -split ' ')[0]
    $r = Api GET "guru/all`?search=$kata"
    Chk "GET /guru/all?search=$kata" $r 200; if ($script:LAST_CHK) {
        if ($r.data.total -ge 1) { $script:PASS++; Write-Host "  [PASS] Search guru '$kata' menemukan $($r.data.total) hasil" -ForegroundColor Green }
        else { $script:FAIL++; Write-Host "  [FAIL] Search guru '$kata' total=0 (harus >= 1)" -ForegroundColor Red }
    }
} else { Skip "GET /guru/all?search=" "tidak ada guru" }

# 4-H. GET /guru/all?search= lebih dari 100 karakter → 422
$longSearch = "x" * 101
$r = Api GET "guru/all`?search=$longSearch"
Chk "GET /guru/all?search >100 karakter (harus 422)" $r 422

# ──────────────────────────────────────────────
#  PHASE 5 — SISWA (READ + VALIDATION)
# ──────────────────────────────────────────────
Section "Phase 5: Siswa (Read + Validation)"

# 5-A. GET /siswa/all
$r = Api GET "siswa/all"
Chk "GET /siswa/all" $r 200; if ($script:LAST_CHK) {
    Info "Total siswa: $($r.data.total)"
}

$siswaId = $null
$siswaNama = $null
if ($r.data -and $r.data.data -and $r.data.data.Count -gt 0) {
    $siswaId = $r.data.data[0].idSiswa
    $siswaNama = $r.data.data[0].namaLengkap
    Info "Gunakan idSiswa=$siswaId"
}

# 5-B. GET /siswa?idSiswa={id}
if ($siswaId) {
    $r = Api GET "siswa`?idSiswa=$siswaId"
    Chk "GET /siswa?idSiswa=$siswaId (show)" $r 200
} else { Skip "GET /siswa?idSiswa=" "tidak ada siswa" }

# 5-C. POST /siswa tanpa foto → 422
$r = Api POST "siswa" @{
    email = "testsiswa_${TS}@example.com"; nisn = "1234567890"; namaLengkap = "Siswa Test"
    telephone = "08222222222"; jenisKelamin = "Perempuan"; tempatLahir = "Bandung"
    tanggalLahir = "2005-06-15"; tanggalMasuk = "2023-07-01"; alamat = "Jl Siswa No 2"
    namaIbu = "Ibu Test"
}
Chk "POST /siswa tanpa foto (harus 422)" $r 422

# 5-D. GET siswa dengan idSiswa tidak ada → 404/422
$r = Api GET "siswa`?idSiswa=99999"
$ok = ($r.resCode -eq 404 -or $r.resCode -eq 422)
if ($ok) { $script:PASS++; Write-Host "  [PASS $($r.resCode)] GET /siswa?idSiswa=99999 (tidak ada, harus 404/422)" -ForegroundColor Green }
else     { $script:FAIL++; Write-Host "  [FAIL $($r.resCode)] GET /siswa?idSiswa=99999 -- $($r.resMsg)" -ForegroundColor Red }

# 5-E. POST /siswa/update — ID tidak ada → 404
$r = Api POST "siswa/update" @{ idSiswa = 99999; alamat = "Test Update" }
$ok = ($r.resCode -eq 404 -or $r.resCode -eq 422)
if ($ok) { $script:PASS++; Write-Host "  [PASS $($r.resCode)] POST /siswa/update idSiswa=99999 (tidak ada)" -ForegroundColor Green }
else     { $script:FAIL++; Write-Host "  [FAIL $($r.resCode)] POST /siswa/update idSiswa=99999 -- $($r.resMsg)" -ForegroundColor Red }

# 5-F. DELETE /siswa/{id} — ID tidak ada → 404
$r = Api DELETE "siswa/99999"
$ok = ($r.resCode -eq 404 -or $r.resCode -eq 422)
if ($ok) { $script:PASS++; Write-Host "  [PASS $($r.resCode)] DELETE /siswa/99999 (tidak ada)" -ForegroundColor Green }
else     { $script:FAIL++; Write-Host "  [FAIL $($r.resCode)] DELETE /siswa/99999 -- $($r.resMsg)" -ForegroundColor Red }

# 5-G. GET /siswa/all?search= — cari by potongan nama siswa pertama
if ($siswaNama) {
    $kata = ($siswaNama -split ' ')[0]
    $r = Api GET "siswa/all`?search=$kata"
    Chk "GET /siswa/all?search=$kata" $r 200; if ($script:LAST_CHK) {
        if ($r.data.total -ge 1) { $script:PASS++; Write-Host "  [PASS] Search siswa '$kata' menemukan $($r.data.total) hasil" -ForegroundColor Green }
        else { $script:FAIL++; Write-Host "  [FAIL] Search siswa '$kata' total=0 (harus >= 1)" -ForegroundColor Red }
    }
} else { Skip "GET /siswa/all?search=" "tidak ada siswa" }

# ──────────────────────────────────────────────
#  PHASE 6 — SEMESTER AKTIF
# ──────────────────────────────────────────────
Section "Phase 6: Semester Aktif"

# 6-A. GET semester aktif
$r = Api GET "akademik/semester/aktif"
Chk "GET /akademik/semester/aktif" $r 200; if ($script:LAST_CHK) {
    $tahun    = NullOr $CFG.TahunAjaran $r.data.tahunAjaran
    $semester = NullOr $CFG.Semester    $r.data.semester
    Info "Semester aktif: $tahun Sem $semester"
} else {
    $tahun    = NullOr $CFG.TahunAjaran "2024/2025"
    $semester = NullOr $CFG.Semester    "2"
    Info "Gunakan fallback: $tahun Sem $semester"
}

# 6-B. GET riwayat semester
$r = Api GET "akademik/semester/riwayat"
Chk "GET /akademik/semester/riwayat" $r 200

# 6-C. POST validasi: tanggal_mulai wajib → 422
$r = Api POST "akademik/semester/aktif" @{ tahun_ajaran = $tahun; semester = [int]$semester }
Chk "POST /semester/aktif tanpa tanggal_mulai (harus 422)" $r 422

# 6-D. POST validasi: format tahun salah → 422
$r = Api POST "akademik/semester/aktif" @{ tahun_ajaran = "2024-2025"; semester = 1; tanggal_mulai = "2024-07-01" }
Chk "POST /semester/aktif format tahun salah (harus 422)" $r 422

# 6-E. POST validasi: semester di luar 1/2 → 422
$r = Api POST "akademik/semester/aktif" @{ tahun_ajaran = $tahun; semester = 3; tanggal_mulai = "2024-07-01" }
Chk "POST /semester/aktif semester=3 (harus 422)" $r 422

# ──────────────────────────────────────────────
#  PHASE 7 — JAM PELAJARAN CRUD
# ──────────────────────────────────────────────
Section "Phase 7: Jam Pelajaran CRUD"

# 7-A. GET list jam
$r = Api GET "akademik/jam"
Chk "GET /akademik/jam" $r 200; if ($script:LAST_CHK) {
    Info "Jam pelajaran tersedia: $($r.data.Count)"
}

# Helper: cari atau buat jam berdasarkan urutan ke-
function GetOrMakeJam($ke, $mulai, $selesai) {
    $all = (Api GET "akademik/jam").data
    foreach ($j in $all) { if ($j.ke -eq $ke) { return $j.idJam } }
    $cr = Api POST "akademik/jam" @{ ke = $ke; jam_mulai = $mulai; jam_selesai = $selesai }
    if ($cr.resCode -eq 201) {
        $script:PASS++
        Write-Host "  [PASS 201] POST /akademik/jam ke-$ke" -ForegroundColor Green
        return $cr.data.idJam
    }
    return $null
}

$jam1Id = GetOrMakeJam 1 "07:00" "07:45"
$jam2Id = GetOrMakeJam 2 "07:45" "08:30"
$jam3Id = GetOrMakeJam 3 "08:30" "09:15"
Info "Jam id: ke1=$jam1Id ke2=$jam2Id ke3=$jam3Id"

# 7-B. POST jam duplikat ke-1 → 409 konflik.
# (Dulu 422 karena memakai rule unique:jam_pelajaran,ke. Sejak `ke` boleh
# berulang antar periode/hari, unique dilepas dan duplikat (periode_id, hari, ke)
# dicek di app layer -> 409, konsisten dengan konflik lain: pengampu/wali/kelas penuh.)
$r = Api POST "akademik/jam" @{ ke = 1; jam_mulai = "07:00"; jam_selesai = "07:45" }
Chk "POST /akademik/jam ke-1 duplikat (harus 409)" $r 409

# 7-C. PATCH jam ke-1
if ($jam1Id) {
    $r = Api PATCH "akademik/jam/$jam1Id" @{ jam_mulai = "07:00"; jam_selesai = "07:45" }
    Chk "PATCH /akademik/jam/$jam1Id (update idempotent)" $r 200
}

# 7-D. Buat jam test sementara (ke=4) untuk test DELETE
# Cari ke ≤ 10 yang belum ada
$testJamId = $null
$allJam    = (Api GET "akademik/jam").data
$existingKe = @($allJam | ForEach-Object { $_.ke })
$freeKe = 4
for ($kx = 4; $kx -le 10; $kx++) {
    if ($existingKe -notcontains $kx) { $freeKe = $kx; break }
}
$rJamTmp = Api POST "akademik/jam" @{ ke = $freeKe; jam_mulai = "22:00"; jam_selesai = "22:45" }
if ($rJamTmp.resCode -eq 201) {
    Chk "POST /akademik/jam ke-$freeKe (test DELETE)" $rJamTmp 201
    $testJamId = $rJamTmp.data.idJam
} elseif ($rJamTmp.resCode -eq 422) {
    $script:PASS++
    Write-Host "  [PASS] POST /akademik/jam ke-$freeKe -- sudah ada (422)" -ForegroundColor Green
    foreach ($j in $allJam) { if ($j.ke -eq $freeKe) { $testJamId = $j.idJam; break } }
} else { Chk "POST /akademik/jam ke-$freeKe" $rJamTmp 201 }

# 7-E. DELETE jam test
if ($testJamId) {
    $r = Api DELETE "akademik/jam/$testJamId"
    Chk "DELETE /akademik/jam/$testJamId" $r 202
} else { Skip "DELETE /akademik/jam" "testJamId tidak ada" }

# ──────────────────────────────────────────────
#  PHASE 8 — SISWA KELAS
# ──────────────────────────────────────────────
Section "Phase 8: Siswa Kelas (Pembagian Kelas)"

# 8-A. GET siswa belum terdaftar (endpoint baru cross-service)
$r = Api GET "akademik/siswa/belum-terdaftar`?tahun_ajaran=$tahun&semester=$semester"
Chk "GET /akademik/siswa/belum-terdaftar" $r 200; if ($script:LAST_CHK) {
    Info "Total siswa=$($r.data.total_siswa) | terdaftar=$($r.data.total_terdaftar) | belum=$($r.data.total_belum)"
}

# Pilih siswa untuk test enrollment
# Cari siswa yang belum terdaftar di semester ini
$testSiswaId     = $null
$testEnrollKelas = $kelasId   # pakai kelas dari Phase 2

if ($r.data -and $r.data.siswa -and $r.data.siswa.Count -gt 0) {
    $testSiswaId = $r.data.siswa[0].idSiswa
    Info "Akan enroll siswa id=$testSiswaId ke kelas id=$testEnrollKelas"
} elseif ($siswaId) {
    # Semua sudah terdaftar — ambil siswaId yang ada, lihat kelasnnya
    $testSiswaId = $siswaId
    Info "Semua siswa sudah terdaftar, gunakan siswa id=$siswaId"
}

# Ambil kelas yang valid untuk enroll (dari class/all)
$validKelasId = $null
$allKelas = (Api GET "class/all`?per_page=20").data.data
if ($allKelas -and $allKelas.Count -gt 0) {
    $validKelasId = $allKelas[0].idKelas
    Info "Valid kelas untuk enroll: idKelas=$validKelasId"
}

# 8-B. GET /kelas/{id}/siswa
if ($validKelasId) {
    $r = Api GET "akademik/kelas/$validKelasId/siswa`?tahun_ajaran=$tahun&semester=$semester"
    Chk "GET /akademik/kelas/{id}/siswa" $r 200
    if ($r.data) { Info "Siswa di kelas ${validKelasId}: $($r.data.Count) orang" }
}

# 8-C. POST /akademik/kelas/assign — cross-service ID tidak ada → 404
if ($validKelasId) {
    $r = Api POST "akademik/kelas/assign" @{
        siswa_id = 99999; kelas_id = $validKelasId; tahun_ajaran = $tahun; semester = [int]$semester
    }
    Chk "POST assign siswa_id=99999 (harus 404)" $r 404
}

# 8-D. POST /akademik/kelas/assign — enroll siswa test
$testSiswaKelasId    = $null
$testSiswaKelasKelas = $null

if ($testSiswaId -and $validKelasId) {
    $r = Api POST "akademik/kelas/assign" @{
        siswa_id = $testSiswaId; kelas_id = $validKelasId
        tahun_ajaran = $tahun; semester = [int]$semester
    }
    if ($r.resCode -eq 201) {
        Chk "POST /akademik/kelas/assign (enroll siswa)" $r 201
        $testSiswaKelasId    = $r.data.idSiswaKelas
        $testSiswaKelasKelas = $r.data.kelasId
        $script:CREATED['siswaKelas'] = $testSiswaKelasId
        Info "siswa_kelas_id=$testSiswaKelasId"
    } elseif ($r.resCode -eq 409) {
        $script:PASS++
        Write-Host "  [PASS] POST /akademik/kelas/assign -- siswa sudah terdaftar (409)" -ForegroundColor Green
        # Ambil existing
        $sk = (Api GET "akademik/siswa/$testSiswaId/kelas`?tahun_ajaran=$tahun&semester=$semester").data
        if ($sk -and $sk.Count -gt 0) {
            $testSiswaKelasId    = $sk[0].idSiswaKelas
            $testSiswaKelasKelas = $sk[0].kelasId
            Info "Gunakan siswa_kelas_id=$testSiswaKelasId (existing)"
        }
    } else {
        Chk "POST /akademik/kelas/assign" $r 201
    }
}

# 8-E. GET /siswa/{id}/kelas
if ($testSiswaId) {
    $r = Api GET "akademik/siswa/$testSiswaId/kelas`?tahun_ajaran=$tahun&semester=$semester"
    Chk "GET /akademik/siswa/{id}/kelas" $r 200
}

# 8-F. GET riwayat kelas
if ($validKelasId) {
    $r = Api GET "akademik/kelas/$validKelasId/siswa/riwayat`?tahun_ajaran=$tahun&semester=$semester"
    Chk "GET /akademik/kelas/{id}/siswa/riwayat" $r 200
}

if ($testSiswaId) {
    $r = Api GET "akademik/siswa/$testSiswaId/kelas/riwayat"
    Chk "GET /akademik/siswa/{id}/kelas/riwayat" $r 200
}

# 8-G. Cari kelas lain yang valid untuk test pindah kelas
$targetKelasId = $null
if ($allKelas -and $allKelas.Count -gt 1) {
    foreach ($kl in $allKelas) {
        if ($kl.idKelas -ne $testSiswaKelasKelas) { $targetKelasId = $kl.idKelas; break }
    }
}

# 8-H. PATCH /kelas/assign/{id} — pindah kelas
if ($testSiswaKelasId -and $targetKelasId) {
    $r = Api PATCH "akademik/kelas/assign/$testSiswaKelasId" @{ kelas_id = $targetKelasId }
    if ($r.resCode -eq 200 -or $r.resCode -eq 409) {
        if ($r.resCode -eq 200) {
            $script:PASS++; Write-Host "  [PASS 200] PATCH /akademik/kelas/assign/{id} (pindah kelas)" -ForegroundColor Green
            $testSiswaKelasKelas = $targetKelasId
        } else {
            $script:PASS++; Write-Host "  [PASS] PATCH -- kelas sama (409), diasumsikan sudah di sana" -ForegroundColor Green
        }
    } else { Chk "PATCH /akademik/kelas/assign/{id} (pindah kelas)" $r 200 }
}

# ──────────────────────────────────────────────
#  PHASE 9 — PENGAMPU MAPEL
# ──────────────────────────────────────────────
Section "Phase 9: Pengampu Mapel"

# Gunakan guru dan mapel pertama dari data
$pengampuGuruId  = $guruId
$pengampuMapelId = $mapelId
$pengampuKelasId = if ($testSiswaKelasKelas) { $testSiswaKelasKelas } else { $validKelasId }
$testPengampuId  = $null

# 9-A. Cross-service: guru_id tidak ada → 404
if ($pengampuMapelId -and $pengampuKelasId) {
    $r = Api POST "akademik/pengampu" @{
        guru_id = 99999; mapel_id = $pengampuMapelId; kelas_id = $pengampuKelasId
        tahun_ajaran = $tahun; semester = [int]$semester
    }
    Chk "POST /akademik/pengampu guru_id=99999 (harus 404)" $r 404

    $r = Api POST "akademik/pengampu" @{
        guru_id = $pengampuGuruId; mapel_id = 99999; kelas_id = $pengampuKelasId
        tahun_ajaran = $tahun; semester = [int]$semester
    }
    Chk "POST /akademik/pengampu mapel_id=99999 (harus 404)" $r 404

    $r = Api POST "akademik/pengampu" @{
        guru_id = $pengampuGuruId; mapel_id = $pengampuMapelId; kelas_id = 99999
        tahun_ajaran = $tahun; semester = [int]$semester
    }
    Chk "POST /akademik/pengampu kelas_id=99999 (harus 404)" $r 404
}

# 9-B. POST assign pengampu
if ($pengampuGuruId -and $pengampuMapelId -and $pengampuKelasId) {
    $r = Api POST "akademik/pengampu" @{
        guru_id = $pengampuGuruId; mapel_id = $pengampuMapelId; kelas_id = $pengampuKelasId
        tahun_ajaran = $tahun; semester = [int]$semester
    }
    if ($r.resCode -eq 201) {
        Chk "POST /akademik/pengampu (assign)" $r 201
        $testPengampuId = $r.data.idPengampuMapel
        $script:CREATED['pengampu'] = $testPengampuId
        Info "pengampu_id=$testPengampuId"
    } elseif ($r.resCode -eq 409) {
        $script:PASS++
        Write-Host "  [PASS] POST /akademik/pengampu -- sudah ada (409), cari id..." -ForegroundColor Green
        $pm = (Api GET "akademik/guru/$pengampuGuruId/mapel`?tahun_ajaran=$tahun&semester=$semester").data
        foreach ($p in $pm) {
            if ($p.mapelId -eq $pengampuMapelId -and $p.kelasId -eq $pengampuKelasId) {
                $testPengampuId = $p.idPengampuMapel; break
            }
        }
        Info "Gunakan pengampu_id=$testPengampuId (existing)"
    } else { Chk "POST /akademik/pengampu" $r 201 }
}

# 9-C. GET /kelas/{id}/pengampu
if ($pengampuKelasId) {
    $r = Api GET "akademik/kelas/$pengampuKelasId/pengampu`?tahun_ajaran=$tahun&semester=$semester"
    Chk "GET /akademik/kelas/{id}/pengampu" $r 200
    if ($r.data) { Info "Pengampu di kelas ${pengampuKelasId}: $($r.data.Count)" }
}

# 9-D. GET /guru/{id}/mapel
if ($pengampuGuruId) {
    $r = Api GET "akademik/guru/$pengampuGuruId/mapel`?tahun_ajaran=$tahun&semester=$semester"
    Chk "GET /akademik/guru/{id}/mapel" $r 200
}

# 9-E. GET /mapel/{id}/guru
if ($pengampuMapelId) {
    $r = Api GET "akademik/mapel/$pengampuMapelId/guru`?tahun_ajaran=$tahun&semester=$semester"
    Chk "GET /akademik/mapel/{id}/guru" $r 200
}

# 9-F. GET riwayat guru
if ($pengampuGuruId) {
    $r = Api GET "akademik/guru/$pengampuGuruId/mapel/riwayat`?tahun_ajaran=$tahun&semester=$semester"
    Chk "GET /akademik/guru/{id}/mapel/riwayat" $r 200
}

# 9-G. GET /akademik/mapel/{id}/guru/riwayat
if ($pengampuMapelId) {
    $r = Api GET "akademik/mapel/$pengampuMapelId/guru/riwayat"
    Chk "GET /akademik/mapel/{id}/guru/riwayat" $r 200
}

# 9-H. PATCH /akademik/pengampu/{id} — ganti guru
if ($testPengampuId) {
    # Validasi: guru_id tidak ada → 404
    $r = Api PATCH "akademik/pengampu/$testPengampuId" @{ guru_id = 99999 }
    Chk "PATCH /akademik/pengampu/{id} guru_id=99999 (harus 404)" $r 404

    # Coba ganti dengan guru lain jika ada
    $altGuruId = $null
    $allGurus = @((Api GET "guru/all`?per_page=10").data.data)
    foreach ($g in $allGurus) { if ($g.idGuru -ne $pengampuGuruId) { $altGuruId = $g.idGuru; break } }
    if ($altGuruId) {
        $r = Api PATCH "akademik/pengampu/$testPengampuId" @{ guru_id = $altGuruId }
        Chk "PATCH /akademik/pengampu/{id} ganti guru ke id=$altGuruId (harus 200)" $r 200; if ($script:LAST_CHK) {
            # Kembalikan agar jadwal/nilai test tetap konsisten
            Api PATCH "akademik/pengampu/$testPengampuId" @{ guru_id = $pengampuGuruId } | Out-Null
            Info "Guru dikembalikan ke id=$pengampuGuruId"
        }
    } else {
        Skip "PATCH /akademik/pengampu/{id} ganti guru" "hanya ada 1 guru di database"
    }
}

# ──────────────────────────────────────────────
#  PHASE 10 — JADWAL PELAJARAN CRUD
# ──────────────────────────────────────────────
Section "Phase 10: Jadwal Pelajaran CRUD"

$testJadwalId = $null

if ($testPengampuId -and $jam1Id -and $jam3Id) {
    # Hapus jadwal lama pengampu ini (clean state)
    $existingJadwal = (Api GET "akademik/jadwal/pengampu/$testPengampuId").data
    if ($existingJadwal -and $existingJadwal.Count -gt 0) {
        foreach ($jj in $existingJadwal) { Api DELETE "akademik/jadwal/$($jj.idJadwal)" | Out-Null }
        Info "Hapus $($existingJadwal.Count) jadwal lama (clean state)"
    }

    # 10-A. POST jadwal valid — coba hari satu per satu agar tidak konflik dengan jadwal guru lain
    $testJadwalId  = $null
    $usedHari      = $null
    $hariList      = @("Senin","Selasa","Rabu","Kamis","Jumat")
    foreach ($hari in $hariList) {
        $r = Api POST "akademik/jadwal" @{
            pengampu_mapel_id = $testPengampuId; hari = $hari
            jam_mulai_id = $jam1Id; jam_selesai_id = $jam3Id; ruangan = "Kelas Test"
        }
        if ($r.resCode -eq 201) {
            Chk "POST /akademik/jadwal ($hari, jam 1-3)" $r 201
            $testJadwalId = $r.data.idJadwal
            $usedHari     = $hari
            $script:CREATED['jadwal'] = $testJadwalId
            Info "jadwal_id=$testJadwalId hari=$usedHari"
            break
        } elseif ($r.resCode -eq 409) {
            Info "Hari $hari -- guru konflik (409), coba hari lain"
        }
    }
    if (-not $testJadwalId) {
        $script:FAIL++
        Write-Host "  [FAIL] POST /akademik/jadwal -- semua hari konflik, tidak bisa buat jadwal" -ForegroundColor Red
    }

    # 10-B. POST jadwal duplikat hari+jam yang sama → 409
    if ($usedHari) {
        $r = Api POST "akademik/jadwal" @{
            pengampu_mapel_id = $testPengampuId; hari = $usedHari
            jam_mulai_id = $jam1Id; jam_selesai_id = $jam3Id; ruangan = "Kelas Lain"
        }
        Chk "POST /akademik/jadwal duplikat $usedHari (harus 409)" $r 409
    } else { Skip "POST /akademik/jadwal duplikat" "jadwal test tidak berhasil dibuat" }

    # 10-C. POST hari Sabtu → 422
    $r = Api POST "akademik/jadwal" @{
        pengampu_mapel_id = $testPengampuId; hari = "Sabtu"
        jam_mulai_id = $jam1Id; jam_selesai_id = $jam3Id
    }
    Chk "POST /akademik/jadwal hari Sabtu (harus 422)" $r 422

    # 10-D. POST jam selesai < jam mulai → 422 (pilih hari yang tidak konflik)
    $hariLain = "Minggu"
    foreach ($hx in $hariList) { if ($hx -ne $usedHari) { $hariLain = $hx; break } }
    $r = Api POST "akademik/jadwal" @{
        pengampu_mapel_id = $testPengampuId; hari = $hariLain
        jam_mulai_id = $jam3Id; jam_selesai_id = $jam1Id
    }
    Chk "POST /akademik/jadwal jam_selesai < jam_mulai (harus 422)" $r 422

    # 10-E. GET jadwal by kelas
    $jadwalKelasId = if ($pengampuKelasId) { $pengampuKelasId } else { $validKelasId }
    if ($jadwalKelasId) {
        $r = Api GET "akademik/jadwal/kelas/$jadwalKelasId`?tahun_ajaran=$tahun&semester=$semester"
        Chk "GET /akademik/jadwal/kelas/{id}" $r 200
        if ($r.data) { Info "Jadwal di kelas ${jadwalKelasId}: $($r.data.Count)" }
    }

    # 10-F. GET jadwal by guru
    if ($pengampuGuruId) {
        $r = Api GET "akademik/jadwal/guru/$pengampuGuruId`?tahun_ajaran=$tahun&semester=$semester"
        Chk "GET /akademik/jadwal/guru/{id}" $r 200
    }

    # 10-G. GET jadwal by siswa
    if ($testSiswaId) {
        $r = Api GET "akademik/jadwal/siswa/$testSiswaId`?tahun_ajaran=$tahun&semester=$semester"
        Chk "GET /akademik/jadwal/siswa/{id}" $r 200
    }

    # 10-H. GET jadwal by pengampu
    $r = Api GET "akademik/jadwal/pengampu/$testPengampuId"
    Chk "GET /akademik/jadwal/pengampu/{id}" $r 200

    # 10-I. PATCH jadwal
    if ($testJadwalId) {
        $r = Api PATCH "akademik/jadwal/$testJadwalId" @{ ruangan = "Kelas Updated" }
        Chk "PATCH /akademik/jadwal/{id} (update ruangan)" $r 200
    }

    # 10-J. GET riwayat jadwal (by pengampu, kelas, guru)
    $r = Api GET "akademik/jadwal/pengampu/$testPengampuId/riwayat"
    Chk "GET /akademik/jadwal/pengampu/{id}/riwayat" $r 200

    if ($jadwalKelasId) {
        $r = Api GET "akademik/jadwal/kelas/$jadwalKelasId/riwayat"
        Chk "GET /akademik/jadwal/kelas/{id}/riwayat" $r 200
    }

    if ($pengampuGuruId) {
        $r = Api GET "akademik/jadwal/guru/$pengampuGuruId/riwayat"
        Chk "GET /akademik/jadwal/guru/{id}/riwayat" $r 200
    }

} else {
    Skip "Phase 10 Jadwal Pelajaran" "testPengampuId=$testPengampuId jam1Id=$jam1Id jam3Id=$jam3Id"
}

# ──────────────────────────────────────────────
#  PHASE 11 — PENGATURAN NILAI
# ──────────────────────────────────────────────
Section "Phase 11: Pengaturan Nilai"

$testPengNilaiId = $null

# 11-A. GET list pengaturan
$r = Api GET "akademik/pengaturan-nilai`?tahun_ajaran=$tahun&semester=$semester"
Chk "GET /akademik/pengaturan-nilai" $r 200

$existingPN = $null
if ($r.data) {
    foreach ($pn in $r.data) {
        if ($pn.tahunAjaran -eq $tahun -and [string]$pn.semester -eq [string]$semester) {
            $existingPN = $pn; break
        }
    }
}

# 11-B. POST pengaturan (atau gunakan yang ada)
if ($existingPN) {
    $testPengNilaiId = $existingPN.idPengaturan
    $script:PASS++
    Write-Host "  [PASS] Pengaturan nilai sudah ada (id=$testPengNilaiId): H=$($existingPN.bobotHarian)% UTS=$($existingPN.bobotUts)% UAS=$($existingPN.bobotUas)%" -ForegroundColor Green
} else {
    $r = Api POST "akademik/pengaturan-nilai" @{
        tahun_ajaran = $tahun; semester = [int]$semester; bobot_harian = 40; bobot_uts = 30; bobot_uas = 30
    }
    Chk "POST /akademik/pengaturan-nilai (40+30+30=100)" $r 201; if ($script:LAST_CHK) {
        $testPengNilaiId = $r.data.idPengaturan
        $script:CREATED['pengaturanNilai'] = $testPengNilaiId
        Info "pengaturan_id=$testPengNilaiId"
    }
}

# 11-C. POST duplikat → 409
$r = Api POST "akademik/pengaturan-nilai" @{
    tahun_ajaran = $tahun; semester = [int]$semester; bobot_harian = 50; bobot_uts = 25; bobot_uas = 25
}
Chk "POST pengaturan duplikat semester (harus 409)" $r 409

# 11-D. POST total bobot ≠ 100 → 422
$r = Api POST "akademik/pengaturan-nilai" @{
    tahun_ajaran = "2099/2100"; semester = 1; bobot_harian = 50; bobot_uts = 30; bobot_uas = 30
}
Chk "POST pengaturan total=110 (harus 422)" $r 422

# 11-E. PATCH update bobot (normalkan ke 40/30/30 agar kalkulasi nilai prediktabel)
$activeBobotH = 40; $activeBobotUts = 30; $activeBobotUas = 30
if ($testPengNilaiId) {
    $r = Api PATCH "akademik/pengaturan-nilai/$testPengNilaiId" @{
        bobot_harian = 40; bobot_uts = 30; bobot_uas = 30
    }
    Chk "PATCH /akademik/pengaturan-nilai/{id} (40+30+30=100)" $r 200; if ($script:LAST_CHK) {
        $activeBobotH   = if ($r.data.bobotHarian) { [int]$r.data.bobotHarian } else { 40 }
        $activeBobotUts = if ($r.data.bobotUts)    { [int]$r.data.bobotUts }    else { 30 }
        $activeBobotUas = if ($r.data.bobotUas)    { [int]$r.data.bobotUas }    else { 30 }
    }
} elseif ($existingPN) {
    $activeBobotH   = if ($existingPN.bobotHarian) { [int]$existingPN.bobotHarian } else { 40 }
    $activeBobotUts = if ($existingPN.bobotUts)    { [int]$existingPN.bobotUts }    else { 30 }
    $activeBobotUas = if ($existingPN.bobotUas)    { [int]$existingPN.bobotUas }    else { 30 }
}
Info "Bobot aktif: H=${activeBobotH}% UTS=${activeBobotUts}% UAS=${activeBobotUas}%"

# ──────────────────────────────────────────────
#  PHASE 12 — NILAI CRUD
# ──────────────────────────────────────────────
Section "Phase 12: Nilai CRUD"

$testNilaiId = $null

if ($testSiswaKelasId -and $testPengampuId) {
    Info "Nilai: siswa_kelas_id=$testSiswaKelasId pengampu_id=$testPengampuId"

    # Hapus nilai lama jika ada (clean state)
    $existingN = (Api GET "akademik/nilai/pengampu/$testPengampuId").data
    if ($existingN) {
        foreach ($n in $existingN) {
            if ($n.siswaKelasId -eq $testSiswaKelasId) {
                Api DELETE "akademik/nilai/$($n.idNilai)" | Out-Null
            }
        }
    }

    # 12-A. POST nilai — hanya harian
    $r = Api POST "akademik/nilai" @{
        siswa_kelas_id = $testSiswaKelasId; pengampu_mapel_id = $testPengampuId; nilai_harian = 80
    }
    Chk "POST /akademik/nilai (harian=80)" $r 201; if ($script:LAST_CHK) {
        $testNilaiId = $r.data.idNilai
        $script:CREATED['nilai'] = $testNilaiId
        Info "nilai_id=$testNilaiId"
        if ($r.data.nilaiAkhir -eq $null) {
            $script:PASS++; Write-Host "  [PASS] nilai_akhir = null (UTS/UAS belum)" -ForegroundColor Green
        } else {
            $script:FAIL++; Write-Host "  [FAIL] nilai_akhir seharusnya null: $($r.data.nilaiAkhir)" -ForegroundColor Red
        }
    }

    # 12-B. POST nilai out-of-range → 422
    $r = Api POST "akademik/nilai" @{
        siswa_kelas_id = $testSiswaKelasId; pengampu_mapel_id = $testPengampuId; nilai_harian = 150
    }
    Chk "POST /akademik/nilai harian=150 (harus 422)" $r 422

    if ($testNilaiId) {
        # 12-C. PATCH tambah UTS
        $r = Api PATCH "akademik/nilai/$testNilaiId" @{ nilai_uts = 75 }
        Chk "PATCH /akademik/nilai/{id} tambah UTS=75" $r 200; if ($script:LAST_CHK) {
            if ($r.data.nilaiAkhir -eq $null) {
                $script:PASS++; Write-Host "  [PASS] nilai_akhir null (UAS belum)" -ForegroundColor Green
            } else {
                $script:FAIL++; Write-Host "  [FAIL] nilai_akhir seharusnya null: $($r.data.nilaiAkhir)" -ForegroundColor Red
            }
        }

        # 12-D. PATCH tambah UAS — nilai_akhir harus dihitung
        $r = Api PATCH "akademik/nilai/$testNilaiId" @{ nilai_uas = 90 }
        Chk "PATCH /akademik/nilai/{id} tambah UAS=90" $r 200; if ($script:LAST_CHK) {
            $na  = [double]($r.data.nilaiAkhir)
            $exp = [math]::Round((80*$activeBobotH + 75*$activeBobotUts + 90*$activeBobotUas) / 100, 2)
            if ([math]::Round($na, 2) -eq $exp) {
                $script:PASS++; Write-Host "  [PASS] nilai_akhir = $na (expected $exp)" -ForegroundColor Green
            } else {
                $script:FAIL++; Write-Host "  [FAIL] nilai_akhir = $na (expected $exp)" -ForegroundColor Red
            }
        }
    }

    # 12-E. GET nilai by pengampu
    $r = Api GET "akademik/nilai/pengampu/$testPengampuId"
    Chk "GET /akademik/nilai/pengampu/{id}" $r 200
    if ($r.data) { Info "Records by pengampu: $($r.data.Count)" }

    # 12-F. GET nilai by kelas
    if ($testSiswaKelasKelas) {
        $r = Api GET "akademik/nilai/kelas/$testSiswaKelasKelas`?tahun_ajaran=$tahun&semester=$semester"
        Chk "GET /akademik/nilai/kelas/{id}" $r 200
    }

    # 12-G. GET nilai by siswa
    if ($testSiswaId) {
        $r = Api GET "akademik/nilai/siswa/$testSiswaId`?tahun_ajaran=$tahun&semester=$semester"
        Chk "GET /akademik/nilai/siswa/{id}" $r 200
        if ($r.data) { Info "Nilai siswa id=${testSiswaId}: $($r.data.Count) record" }
    }

} else {
    Skip "Phase 12 Nilai CRUD" "testSiswaKelasId=$testSiswaKelasId testPengampuId=$testPengampuId"
}

# ──────────────────────────────────────────────
#  PHASE 13 — RAPORT & RANKING
# ──────────────────────────────────────────────
Section "Phase 13: Raport & Ranking"

$raportSiswaId = if ($testSiswaId) { $testSiswaId } else { $siswaId }
$raportKelasId = if ($testSiswaKelasKelas) { $testSiswaKelasKelas } else { $validKelasId }

if ($raportSiswaId) {
    $r = Api GET "akademik/raport/siswa/$raportSiswaId`?tahun_ajaran=$tahun&semester=$semester"
    Chk "GET /akademik/raport/siswa/{id}" $r 200
    if ($r.data) {
        Info "Raport siswa ${raportSiswaId}: rata-rata=$($r.data.rataRata) | mapel=$($r.data.nilai.Count)"
        if ($r.data.bobot) {
            Info "Bobot: H=$($r.data.bobot.bobotHarian)% UTS=$($r.data.bobot.bobotUts)% UAS=$($r.data.bobot.bobotUas)%"
        }
    }
} else { Skip "GET /akademik/raport/siswa/{id}" "tidak ada siswa id" }

if ($raportKelasId) {
    $r = Api GET "akademik/raport/kelas/$raportKelasId`?tahun_ajaran=$tahun&semester=$semester"
    Chk "GET /akademik/raport/kelas/{id}" $r 200
    if ($r.data -and $r.data.siswa) { Info "Raport kelas ${raportKelasId}: $($r.data.siswa.Count) siswa" }

    $r = Api GET "akademik/nilai/ranking/kelas/$raportKelasId`?tahun_ajaran=$tahun&semester=$semester"
    Chk "GET /akademik/nilai/ranking/kelas/{id}" $r 200
    if ($r.data) { Info "Ranking kelas ${raportKelasId}: $($r.data.totalSiswa) siswa" }
} else { Skip "GET /akademik/raport/kelas & ranking" "tidak ada kelas id" }

# ──────────────────────────────────────────────
#  PHASE 14 — AUTHORIZATION TESTS
# ──────────────────────────────────────────────
Section "Phase 14: Authorization (Role) Tests"

# 14-A. Unauthenticated: protected route → 401
$r = Api GET "akademik/semester/aktif" -Token " "
Chk "GET /akademik/semester/aktif tanpa token (harus 401)" $r 401

# 14-B. Register role Siswa test untuk cek pembatasan
$siswaTestEmail = "testsiswarole_${TS}@example.com"
$siswaTestPw    = "SiswaPass123"
$siswaTestToken = $null
$siswaTestUserId = $null

$r = Api POST "register" @{
    name             = "Siswa Test Role $TS"
    email            = $siswaTestEmail
    password         = $siswaTestPw
    confirm_password = $siswaTestPw
    role             = "Siswa"
}
Chk "POST /register buat user Siswa (untuk role test)" $r 201; if ($script:LAST_CHK) {
    $loginS = Api POST "login" @{ email = $siswaTestEmail; password = $siswaTestPw }
    Chk "Login sebagai Siswa test" $loginS 200; if ($script:LAST_CHK) {
        $siswaTestToken = $loginS.data.token
        # Ambil id untuk cleanup
        $usrs = @((Api GET "users`?per_page=200").data.data)
        foreach ($u in $usrs) { if ($u.email -eq $siswaTestEmail) { $siswaTestUserId = $u.id; break } }
    }
}

# 14-C. Register role Karyawan untuk cek pembatasan
$kwTestEmail = "testkaryawan_${TS}@example.com"
$kwTestPw    = "KaryawanPass123"
$kwTestToken = $null
$kwTestUserId = $null

$r = Api POST "register" @{
    name             = "Karyawan Test $TS"
    email            = $kwTestEmail
    password         = $kwTestPw
    confirm_password = $kwTestPw
    role             = "Karyawan"
}
Chk "POST /register buat user Karyawan (untuk role test)" $r 201; if ($script:LAST_CHK) {
    $loginK = Api POST "login" @{ email = $kwTestEmail; password = $kwTestPw }
    Chk "Login sebagai Karyawan test" $loginK 200; if ($script:LAST_CHK) {
        $kwTestToken = $loginK.data.token
        $usrs = @((Api GET "users`?per_page=200").data.data)
        foreach ($u in $usrs) { if ($u.email -eq $kwTestEmail) { $kwTestUserId = $u.id; break } }
    }
}

# Role Siswa: tidak bisa buat kelas (harus 403)
if ($siswaTestToken) {
    $r = Api POST "class" @{ noKelas = 1; tingkat = 1; jurusan = "MIPA"; limitSiswa = 30 } -Token $siswaTestToken
    Chk "POST /class sebagai Siswa (harus 403)" $r 403

    $r = Api POST "akademik/jam" @{ ke = 50; jam_mulai = "12:00"; jam_selesai = "12:45" } -Token $siswaTestToken
    Chk "POST /akademik/jam sebagai Siswa (harus 403)" $r 403

    $r = Api POST "akademik/pengaturan-nilai" @{
        tahun_ajaran = $tahun; semester = [int]$semester; bobot_harian = 40; bobot_uts = 30; bobot_uas = 30
    } -Token $siswaTestToken
    Chk "POST /akademik/pengaturan-nilai sebagai Siswa (harus 403)" $r 403

    $r = Api POST "register" @{
        name="x"; email="x@x.com"; password=$siswaTestPw; confirm_password=$siswaTestPw; role="Admin"
    } -Token $siswaTestToken
    Chk "POST /register sebagai Siswa (harus 403)" $r 403

    $r = Api GET "users" -Token $siswaTestToken
    Chk "GET /users sebagai Siswa (harus 403)" $r 403

    # Privasi: Siswa tidak boleh lihat profil lengkap siswa lain
    $r = Api GET "siswa`?idSiswa=1" -Token $siswaTestToken
    Chk "GET /siswa?idSiswa=1 sebagai Siswa (harus 403 - privasi)" $r 403

    # Privasi: detail guru untuk Siswa disaring — field publik ada, field pribadi tidak
    if ($guruId) {
        $r = Api GET "guru`?idGuru=$guruId" -Token $siswaTestToken
        Chk "GET /guru?idGuru=$guruId sebagai Siswa (harus 200, field disaring)" $r 200; if ($script:LAST_CHK) {
            $d = $r.data
            $hasPublic  = ($null -ne $d.namaLengkap)
            $noPrivate  = ($null -eq $d.nik -and $null -eq $d.alamat -and $null -eq $d.telephone -and $null -eq $d.tanggalLahir)
            if ($hasPublic -and $noPrivate) { $script:PASS++; Write-Host "  [PASS] Field pribadi guru (nik/alamat/telephone/tanggalLahir) tersaring untuk Siswa" -ForegroundColor Green }
            else { $script:FAIL++; Write-Host "  [FAIL] Field pribadi guru bocor ke Siswa (nik=$($d.nik) alamat=$($d.alamat) telp=$($d.telephone))" -ForegroundColor Red }
        }
    }
}

# Role Karyawan: bisa baca Akademik, tidak bisa tulis
if ($kwTestToken) {
    $r = Api GET "akademik/semester/aktif" -Token $kwTestToken
    Chk "GET /akademik/semester/aktif sebagai Karyawan (harus 200)" $r 200

    # Karyawan (staf TU) tetap boleh lihat detail siswa
    if ($siswaId) {
        $r = Api GET "siswa`?idSiswa=$siswaId" -Token $kwTestToken
        Chk "GET /siswa?idSiswa=$siswaId sebagai Karyawan (harus 200)" $r 200
    }

    $r = Api POST "akademik/kelas/assign" @{
        siswa_id = 1; kelas_id = 1; tahun_ajaran = $tahun; semester = [int]$semester
    } -Token $kwTestToken
    Chk "POST /akademik/kelas/assign sebagai Karyawan (harus 403)" $r 403

    $r = Api DELETE "class/1" -Token $kwTestToken
    Chk "DELETE /class/{id} sebagai Karyawan (harus 403)" $r 403
}

# Admin test (dari Phase 1): bisa baca semua, tidak bisa register SuperAdmin
if ($testToken) {
    $r = Api GET "siswa/all" -Token $testToken
    Chk "GET /siswa/all sebagai Admin (harus 200)" $r 200

    $r = Api POST "register" @{
        name="SuperTest"; email="sup_${TS}@x.com"; password=$testUserPassword; confirm_password=$testUserPassword; role="SuperAdmin"
    } -Token $testToken
    Chk "POST /register role SuperAdmin sebagai Admin (harus 422)" $r 422
}

# Role Siswa: bisa akses endpoint /saya (self-service)
# 200 = siswa & data ditemukan; 404 = siswa belum terdaftar di SiswaService / belum ada data
# FAIL hanya jika: 401 (tidak terautentikasi), 403 (role salah), 500 (error server)
if ($siswaTestToken) {
    $r = Api GET "akademik/nilai/saya`?tahun_ajaran=$tahun&semester=$semester" -Token $siswaTestToken
    $ok = ($r.resCode -eq 200 -or $r.resCode -eq 404)
    if ($ok) { $script:PASS++; Write-Host "  [PASS $($r.resCode)] GET /akademik/nilai/saya sebagai Siswa (200/404 valid)" -ForegroundColor Green }
    else     { $script:FAIL++; Write-Host "  [FAIL $($r.resCode)] GET /akademik/nilai/saya sebagai Siswa -- $($r.resMsg)" -ForegroundColor Red }

    $r = Api GET "akademik/raport/saya`?tahun_ajaran=$tahun&semester=$semester" -Token $siswaTestToken
    $ok = ($r.resCode -eq 200 -or $r.resCode -eq 404)
    if ($ok) { $script:PASS++; Write-Host "  [PASS $($r.resCode)] GET /akademik/raport/saya sebagai Siswa (200/404 valid)" -ForegroundColor Green }
    else     { $script:FAIL++; Write-Host "  [FAIL $($r.resCode)] GET /akademik/raport/saya sebagai Siswa -- $($r.resMsg)" -ForegroundColor Red }

    $r = Api GET "akademik/nilai/ranking/saya`?tahun_ajaran=$tahun&semester=$semester" -Token $siswaTestToken
    $ok = ($r.resCode -eq 200 -or $r.resCode -eq 404)
    if ($ok) { $script:PASS++; Write-Host "  [PASS $($r.resCode)] GET /akademik/nilai/ranking/saya sebagai Siswa (200/404 valid)" -ForegroundColor Green }
    else     { $script:FAIL++; Write-Host "  [FAIL $($r.resCode)] GET /akademik/nilai/ranking/saya sebagai Siswa -- $($r.resMsg)" -ForegroundColor Red }

    # Siswa tidak bisa input nilai orang lain
    $r = Api POST "akademik/nilai" @{ siswa_kelas_id = 1; pengampu_mapel_id = 1; nilai_harian = 80 } -Token $siswaTestToken
    Chk "POST /akademik/nilai sebagai Siswa (harus 403)" $r 403
}

# Non-Siswa tidak bisa akses endpoint /saya (harus 403)
if ($testToken) {
    $r = Api GET "akademik/nilai/saya" -Token $testToken
    Chk "GET /akademik/nilai/saya sebagai Admin (harus 403)" $r 403

    $r = Api GET "akademik/raport/saya" -Token $testToken
    Chk "GET /akademik/raport/saya sebagai Admin (harus 403)" $r 403

    $r = Api GET "akademik/nilai/ranking/saya" -Token $testToken
    Chk "GET /akademik/nilai/ranking/saya sebagai Admin (harus 403)" $r 403
}

# Karyawan bisa baca nilai (bukan hanya semester)
if ($kwTestToken -and $testPengampuId) {
    $r = Api GET "akademik/nilai/pengampu/$testPengampuId" -Token $kwTestToken
    Chk "GET /akademik/nilai/pengampu/{id} sebagai Karyawan (harus 200)" $r 200
}

# ──────────────────────────────────────────────
#  PHASE 14.5 — MULTI-DEVICE SESSION
#  Satu sesi aktif per device: login web + android bisa bersamaan,
#  login ulang di device yang sama menendang sesi lama device itu saja.
#  CATATAN: ditaruh setelah Phase 14 karena login web di sini mencabut
#  $testToken (device sama), dan logout-all mencabut semua token test user.
# ──────────────────────────────────────────────
Section "Phase 14.5: Multi-Device Session (satu sesi per device)"

if ($testUserEmail -and $newSelfPw) {
    # Login device "web"
    $rW = Api POST "login" @{ email = $testUserEmail; password = $newSelfPw; device_name = "web" }
    Chk "Login device=web (harus 200)" $rW 200
    $tokenWeb = $rW.data.token

    # Login device "android" — sesi web tidak boleh tertendang
    $rA = Api POST "login" @{ email = $testUserEmail; password = $newSelfPw; device_name = "android" }
    Chk "Login device=android (harus 200)" $rA 200
    $tokenAndroid = $rA.data.token

    if ($tokenWeb -and $tokenAndroid) {
        $r = Api GET "user" -Token $tokenWeb
        Chk "Sesi web tetap hidup setelah login android (harus 200)" $r 200

        # Login ulang device android → hanya sesi android lama yang tertendang
        $rA2 = Api POST "login" @{ email = $testUserEmail; password = $newSelfPw; device_name = "android" }
        Chk "Login ulang device=android (harus 200)" $rA2 200
        $tokenAndroid2 = $rA2.data.token

        $r = Api GET "user" -Token $tokenAndroid
        Chk "Token android lama tertendang setelah login ulang (harus 401)" $r 401

        $r = Api GET "user" -Token $tokenWeb
        Chk "Sesi web tetap hidup setelah login ulang android (harus 200)" $r 200

        # Refresh token android → device diwarisi, sesi web tidak terganggu
        $rRef = Api POST "refresh" -Token $tokenAndroid2
        Chk "POST /refresh token android (harus 200)" $rRef 200
        if ($script:LAST_CHK) { $tokenAndroid2 = $rRef.data.token }

        $r = Api GET "user" -Token $tokenWeb
        Chk "Sesi web tetap hidup setelah refresh android (harus 200)" $r 200

        # Logout-all → semua sesi (web + android) mati sekaligus
        $r = Api POST "logout-all" -Token $tokenWeb
        Chk "POST /logout-all (harus 200)" $r 200

        $r = Api GET "user" -Token $tokenWeb
        Chk "Token web mati setelah logout-all (harus 401)" $r 401

        $r = Api GET "user" -Token $tokenAndroid2
        Chk "Token android mati setelah logout-all (harus 401)" $r 401
    }
} else {
    Skip "Multi-device session tests" "test user Phase 1 tidak tersedia"
}

# ──────────────────────────────────────────────
#  PHASE 15 — CROSS-SERVICE VALIDATION
# ──────────────────────────────────────────────
Section "Phase 15: Cross-Service Validation"

if ($validKelasId) {
    $r = Api POST "akademik/kelas/assign" @{
        siswa_id = 99999; kelas_id = $validKelasId; tahun_ajaran = $tahun; semester = [int]$semester
    }
    Chk "Assign siswa_id=99999 → SiswaService 404" $r 404
}

if ($pengampuGuruId -and $pengampuMapelId) {
    $r = Api POST "akademik/pengampu" @{
        guru_id = 99999; mapel_id = $pengampuMapelId; kelas_id = $validKelasId
        tahun_ajaran = $tahun; semester = [int]$semester
    }
    Chk "Assign pengampu guru_id=99999 → GuruService 404" $r 404

    $r = Api POST "akademik/pengampu" @{
        guru_id = $pengampuGuruId; mapel_id = 99999; kelas_id = $validKelasId
        tahun_ajaran = $tahun; semester = [int]$semester
    }
    Chk "Assign pengampu mapel_id=99999 → MapelService 404" $r 404

    $r = Api POST "akademik/pengampu" @{
        guru_id = $pengampuGuruId; mapel_id = $pengampuMapelId; kelas_id = 99999
        tahun_ajaran = $tahun; semester = [int]$semester
    }
    Chk "Assign pengampu kelas_id=99999 → ClassService 404" $r 404
}

# GET pada ID yang tidak ada
$r = Api GET "class`?idKelas=99999"
$ok = ($r.resCode -eq 404 -or $r.resCode -eq 422)
if ($ok) { $script:PASS++; Write-Host "  [PASS $($r.resCode)] GET /class?idKelas=99999 → tidak ditemukan" -ForegroundColor Green }
else     { $script:FAIL++; Write-Host "  [FAIL $($r.resCode)] GET /class?idKelas=99999 seharusnya 404/422" -ForegroundColor Red }

$r = Api GET "guru`?idGuru=99999"
$ok = ($r.resCode -eq 404 -or $r.resCode -eq 422)
if ($ok) { $script:PASS++; Write-Host "  [PASS $($r.resCode)] GET /guru?idGuru=99999 → tidak ditemukan" -ForegroundColor Green }
else     { $script:FAIL++; Write-Host "  [FAIL $($r.resCode)] GET /guru?idGuru=99999 seharusnya 404/422" -ForegroundColor Red }

$r = Api GET "mapel`?idPelajaran=99999"
$ok = ($r.resCode -eq 404 -or $r.resCode -eq 422)
if ($ok) { $script:PASS++; Write-Host "  [PASS $($r.resCode)] GET /mapel?idPelajaran=99999 → tidak ditemukan" -ForegroundColor Green }
else     { $script:FAIL++; Write-Host "  [FAIL $($r.resCode)] GET /mapel?idPelajaran=99999 seharusnya 404/422" -ForegroundColor Red }

# ──────────────────────────────────────────────
#  PHASE 16 — CLEANUP
# ──────────────────────────────────────────────
Section "Phase 16: Absensi"

# 16-A. Kartu absensi (SuperAdmin/Admin)
$siswaKartuUid = $null
if ($siswaId) {
    $r = Api POST "siswa/kartu/terbitkan" @{ idSiswa = $siswaId }
    Chk "POST /siswa/kartu/terbitkan (siswa $siswaId)" $r 200; if ($script:LAST_CHK) { $siswaKartuUid = $r.data.kartuUid; Info "kartuUid siswa = $siswaKartuUid" }

    # QR: respon SVG (bukan JSON envelope) — cek langsung via Invoke-WebRequest
    if ($siswaKartuUid) {
        try {
            $qr = Invoke-WebRequest -Uri "$($CFG.BaseUrl)/kartu/qr?data=$siswaKartuUid" -Headers @{ Authorization = "Bearer $($script:TOKEN)" } -UseBasicParsing -TimeoutSec $CFG.Timeout
            if ($qr.StatusCode -eq 200 -and ("$($qr.Headers['Content-Type'])" -match 'svg')) {
                $script:PASS++; Write-Host "  [PASS] GET /kartu/qr -> SVG" -ForegroundColor Green
            } else {
                $script:FAIL++; Write-Host "  [FAIL] GET /kartu/qr -> status $($qr.StatusCode) ct=$($qr.Headers['Content-Type'])" -ForegroundColor Red
            }
        } catch { $script:FAIL++; Write-Host "  [FAIL] GET /kartu/qr -- $($_.Exception.Message)" -ForegroundColor Red }
    }

    $r = Api POST "siswa/kartu/blokir" @{ idSiswa = $siswaId; status = "hilang" }
    Chk "POST /siswa/kartu/blokir (siswa $siswaId)" $r 200
    # Terbitkan ulang -> UID baru & aktif kembali (agar bisa dipakai uji scan)
    $r = Api POST "siswa/kartu/terbitkan" @{ idSiswa = $siswaId }
    Chk "POST /siswa/kartu/terbitkan ulang (aktif kembali)" $r 200; if ($script:LAST_CHK) { $siswaKartuUid = $r.data.kartuUid }
} else { Skip "Kartu siswa" "siswaId tidak tersedia" }

if ($guruId) {
    $r = Api POST "guru/kartu/terbitkan" @{ idGuru = $guruId }
    Chk "POST /guru/kartu/terbitkan (guru $guruId)" $r 200
} else { Skip "Kartu guru" "guruId tidak tersedia" }

# 16-B. Absensi keluar (pulang awal) — disetujui Admin/Guru (wali)
if ($siswaId) {
    $r = Api POST "akademik/absensi/keluar" @{ siswa_id = $siswaId; jenis = "pulang_awal"; keterangan = "test $TS" }
    Chk "POST /akademik/absensi/keluar (pulang_awal)" $r 201
    $r = Api POST "akademik/absensi/keluar" @{ siswa_id = $siswaId; jenis = "bolos" }
    Chk "POST /akademik/absensi/keluar jenis invalid (harus 422)" $r 422
    $r = Api GET "akademik/absensi/keluar"
    Chk "GET /akademik/absensi/keluar (daftar)" $r 200
}

# 16-C. Rekap absensi (Admin)
if ($kelasId) {
    $r = Api GET "akademik/absensi/rekap/harian/kelas/$kelasId"
    Chk "GET /akademik/absensi/rekap/harian/kelas/$kelasId" $r 200
}
if ($siswaId) {
    $r = Api GET "akademik/absensi/rekap/harian/siswa/$siswaId"
    Chk "GET /akademik/absensi/rekap/harian/siswa/$siswaId" $r 200
    $r = Api GET "akademik/absensi/rekap/pelajaran/siswa/$siswaId"
    Chk "GET /akademik/absensi/rekap/pelajaran/siswa/$siswaId" $r 200
}
if ($guruId) {
    $r = Api GET "akademik/absensi/rekap/pegawai/guru/$guruId"
    Chk "GET /akademik/absensi/rekap/pegawai/guru/$guruId" $r 200
}

# 16-D. Jendela PIN — Admin buka + batasan role
if ($guruId) {
    $r = Api POST "absensi/pin/buka" @{ subjek_tipe = "guru"; subjek_id = $guruId; durasi_menit = 10 }
    Chk "POST /absensi/pin/buka (admin, guru $guruId)" $r 201
}
if ($siswaTestToken) {
    $r = Api POST "absensi/pin/buka" @{ subjek_tipe = "guru"; subjek_id = 1 } -Token $siswaTestToken
    Chk "POST /absensi/pin/buka sebagai Siswa (harus 403)" $r 403
    $r = Api POST "absensi/pin/atur" @{ pin = "1234" } -Token $siswaTestToken
    Chk "POST /absensi/pin/atur sebagai Siswa (harus 403)" $r 403
}

# 16-F. Wali Kelas (Admin) — dipakai enforcement izin keluar
if ($kelasId -and $guruId) {
    $r = Api POST "akademik/wali" @{ guru_id = $guruId; kelas_id = $kelasId; tahun_ajaran = $tahun; semester = [int]$semester }
    Chk "POST /akademik/wali (tetapkan wali)" $r 201
    $waliId = $null; if ($script:LAST_CHK) { $waliId = $r.data.idWaliKelas }
    $r = Api POST "akademik/wali" @{ guru_id = $guruId; kelas_id = $kelasId; tahun_ajaran = $tahun; semester = [int]$semester }
    Chk "POST /akademik/wali duplikat (harus 409)" $r 409
    $r = Api POST "akademik/wali" @{ guru_id = 999999; kelas_id = $kelasId; tahun_ajaran = $tahun; semester = [int]$semester }
    Chk "POST /akademik/wali guru tidak ada (harus 404)" $r 404
    $r = Api GET "akademik/kelas/$kelasId/wali"
    Chk "GET /akademik/kelas/$kelasId/wali" $r 200
    if ($waliId) {
        $r = Api DELETE "akademik/wali/$waliId"
        Chk "DELETE /akademik/wali/$waliId (cleanup)" $r 202
    }
}

# 16-G. Autentikasi terminal (scan) — negatif tanpa header; positif jika terminal disediakan
$r = Api POST "absensi/scan" @{ kartu_uid = "SIS-XXXX" }
Chk "POST /absensi/scan tanpa header terminal (harus 401)" $r 401

if ($env:TEST_TERMINAL_ID -and $env:TEST_TERMINAL_TOKEN -and $siswaKartuUid) {
    $th   = @{ 'X-Terminal-Id' = $env:TEST_TERMINAL_ID; 'X-Terminal-Token' = $env:TEST_TERMINAL_TOKEN }
    $body = @{ kartu_uid = $siswaKartuUid }
    if ($env:TEST_TERMINAL_LAT) { $body['lat'] = [double]$env:TEST_TERMINAL_LAT }
    if ($env:TEST_TERMINAL_LNG) { $body['lng'] = [double]$env:TEST_TERMINAL_LNG }
    $r = Api POST "absensi/scan" $body -ExtraHeaders $th
    if ($r.resCode -eq 201 -or $r.resCode -eq 200) { $script:PASS++; Write-Host "  [PASS $($r.resCode)] POST /absensi/scan (terminal) status=$($r.data.status)" -ForegroundColor Green }
    else { $script:FAIL++; Write-Host "  [FAIL $($r.resCode)] POST /absensi/scan (terminal) -- $($r.resMsg)" -ForegroundColor Red }
} else {
    Skip "POST /absensi/scan (terminal, positif)" "set TEST_TERMINAL_ID/TOKEN(/LAT/LNG) + daftarkan terminal via 'php artisan terminal:register'"
}

# 16-H. Periode khusus & pengaturan absensi (Admin)
# Pakai tanggal jauh di masa depan (2030) agar tidak mempengaruhi perilaku hari ini.
$perId = $null; $liburId = $null; $jamPerId = $null; $pengId = $null

$r = Api POST "akademik/periode" @{ nama = "Test Ramadan $TS"; tahun_ajaran = $tahun; semester = [int]$semester
                                    jenis = 'ramadan'; berlaku_dari = '2030-02-01'; berlaku_sampai = '2030-02-28' }
Chk "POST /akademik/periode (ramadan)" $r 201; if ($script:LAST_CHK) { $perId = $r.data.idPeriode }

$r = Api POST "akademik/periode" @{ nama = "Test Libur $TS"; tahun_ajaran = $tahun; semester = [int]$semester
                                    jenis = 'libur'; berlaku_dari = '2030-02-10'; berlaku_sampai = '2030-02-10' }
Chk "POST /akademik/periode (libur 1 hari)" $r 201
if ($script:LAST_CHK) {
    $liburId = $r.data.idPeriode
    if ($r.data.kbmNormal -eq $false) { $script:PASS++; Write-Host "  [PASS] periode libur -> kbmNormal=false otomatis" -ForegroundColor Green }
    else { $script:FAIL++; Write-Host "  [FAIL] periode libur seharusnya kbmNormal=false" -ForegroundColor Red }
}

# Resolusi: rentang TERPENDEK menang (libur 1 hari mengalahkan Ramadan)
if ($perId -and $liburId) {
    $r = Api GET "akademik/periode/aktif`?tanggal=2030-02-10"
    Chk "GET /akademik/periode/aktif (10 Feb)" $r 200
    if ($script:LAST_CHK) {
        if ($r.data.jenis -eq 'libur') { $script:PASS++; Write-Host "  [PASS] rentang terpendek menang: libur > ramadan" -ForegroundColor Green }
        else { $script:FAIL++; Write-Host "  [FAIL] 10 Feb seharusnya 'libur', dapat '$($r.data.jenis)'" -ForegroundColor Red }
    }
    $r = Api GET "akademik/periode/aktif`?tanggal=2030-02-05"
    Chk "GET /akademik/periode/aktif (05 Feb)" $r 200
    if ($script:LAST_CHK) {
        if ($r.data.jenis -eq 'ramadan') { $script:PASS++; Write-Host "  [PASS] 05 Feb -> ramadan" -ForegroundColor Green }
        else { $script:FAIL++; Write-Host "  [FAIL] 05 Feb seharusnya 'ramadan', dapat '$($r.data.jenis)'" -ForegroundColor Red }
    }
}

# Set jam milik periode + resolusi jam efektif per tanggal
if ($perId) {
    $r = Api POST "akademik/jam" @{ periode_id = $perId; ke = 1; jam_mulai = '07:30'; jam_selesai = '08:00' }
    Chk "POST /akademik/jam (set periode)" $r 201; if ($script:LAST_CHK) { $jamPerId = $r.data.idJam }

    $r = Api GET "akademik/jam`?tanggal=2030-02-05&hari=Senin"
    Chk "GET /akademik/jam?tanggal (set efektif periode)" $r 200
    if ($script:LAST_CHK) {
        $ke1 = @($r.data.jam | Where-Object { $_.ke -eq 1 })[0]
        if ($ke1 -and $ke1.jamMulai -like '07:30*') { $script:PASS++; Write-Host "  [PASS] jam efektif Ramadan ke-1 = $($ke1.jamMulai)" -ForegroundColor Green }
        else { $script:FAIL++; Write-Host "  [FAIL] jam efektif Ramadan ke-1 seharusnya 07:30, dapat $($ke1.jamMulai)" -ForegroundColor Red }
    }
}

# Pengaturan absensi override periode (periode-scoped: tidak menyentuh default semester)
if ($perId) {
    $body = @{ tahun_ajaran = $tahun; semester = [int]$semester; periode_id = $perId; batas_terlambat_siswa = '08:00' }
    $r = Api POST "akademik/pengaturan-absensi" $body
    Chk "POST /akademik/pengaturan-absensi (override periode)" $r 201; if ($script:LAST_CHK) { $pengId = $r.data.idPengaturanAbsensi }

    $r = Api POST "akademik/pengaturan-absensi" $body
    Chk "POST /akademik/pengaturan-absensi duplikat (harus 409)" $r 409

    $r = Api GET "akademik/pengaturan-absensi/efektif`?tanggal=2030-02-05"
    Chk "GET /akademik/pengaturan-absensi/efektif (dalam periode)" $r 200
    if ($script:LAST_CHK) {
        if ($r.data.sumber -eq 'periode' -and $r.data.pengaturan.batasTerlambatSiswa -like '08:00*') {
            $script:PASS++; Write-Host "  [PASS] ambang efektif ikut periode (08:00)" -ForegroundColor Green
        } else {
            $script:FAIL++; Write-Host "  [FAIL] efektif: sumber=$($r.data.sumber) batas=$($r.data.pengaturan.batasTerlambatSiswa)" -ForegroundColor Red
        }
    }
}

# Batasan role: Siswa tidak boleh menulis periode/pengaturan
if ($siswaTestToken) {
    $r = Api POST "akademik/periode" @{ nama = 'x'; tahun_ajaran = $tahun; semester = [int]$semester
                                        jenis = 'libur'; berlaku_dari = '2030-05-01'; berlaku_sampai = '2030-05-01' } -Token $siswaTestToken
    Chk "POST /akademik/periode sebagai Siswa (harus 403)" $r 403
}

# Hapus periode = cascade ke jam & pengaturan turunannya (sekaligus cleanup)
if ($perId) {
    $r = Api DELETE "akademik/periode/$perId"
    Chk "DELETE periode (ramadan)" $r 202
    # jam & pengaturan milik periode harus ikut terhapus
    $sisaJam = @((Api GET "akademik/jam`?periode_id=$perId").data).Count
    if ($sisaJam -eq 0) { $script:PASS++; Write-Host "  [PASS] cascade: jam periode ikut terhapus" -ForegroundColor Green }
    else { $script:FAIL++; Write-Host "  [FAIL] cascade: masih ada $sisaJam jam periode" -ForegroundColor Red }
    $sisaPeng = @((Api GET "akademik/pengaturan-absensi`?tahun_ajaran=$(Enc $tahun)&semester=$semester").data | Where-Object { $_.periodeId -eq $perId }).Count
    if ($sisaPeng -eq 0) { $script:PASS++; Write-Host "  [PASS] cascade: pengaturan periode ikut terhapus" -ForegroundColor Green }
    else { $script:FAIL++; Write-Host "  [FAIL] cascade: masih ada $sisaPeng pengaturan periode" -ForegroundColor Red }
}
if ($liburId) { $r = Api DELETE "akademik/periode/$liburId"; Chk "DELETE periode (libur, cleanup)" $r 202 }

# 16-I. durasi jendela PIN dibaca dari pengaturan absensi (bukan hardcode)
if ($guruId) {
    $pengDefId = $null
    $r = Api POST "akademik/pengaturan-absensi" @{ tahun_ajaran = $tahun; semester = [int]$semester; durasi_pin_window_menit = 20 }
    if ($r.resCode -eq 201) {
        $pengDefId = $r.data.idPengaturanAbsensi
        $r = Api POST "absensi/pin/buka" @{ subjek_tipe = 'guru'; subjek_id = $guruId }
        Chk "POST /absensi/pin/buka (durasi dari pengaturan)" $r 201
        if ($r.data.durasiMenit -eq 20) { $script:PASS++; Write-Host "  [PASS] durasi PIN ikut pengaturan (20)" -ForegroundColor Green }
        else { $script:FAIL++; Write-Host "  [FAIL] durasi PIN = $($r.data.durasiMenit), harusnya 20" -ForegroundColor Red }
        $r = Api POST "absensi/pin/buka" @{ subjek_tipe = 'guru'; subjek_id = $guruId; durasi_menit = 7 }
        if ($r.data.durasiMenit -eq 7) { $script:PASS++; Write-Host "  [PASS] override durasi_menit eksplisit menang (7)" -ForegroundColor Green }
        else { $script:FAIL++; Write-Host "  [FAIL] override durasi = $($r.data.durasiMenit), harusnya 7" -ForegroundColor Red }
        $r = Api DELETE "akademik/pengaturan-absensi/$pengDefId"; Chk "DELETE pengaturan default (cleanup)" $r 202
    } else { Skip "durasi PIN dari pengaturan" "gagal buat pengaturan default (mungkin sudah ada)" }
}

Section "Phase 17: Cleanup"

if ($CFG.SkipCleanup) {
    Write-Host "  [SKIP] Cleanup dilewati (SkipCleanup=`$true)" -ForegroundColor Yellow
} else {
    # Hapus nilai test
    if ($script:CREATED['nilai']) {
        $r = Api DELETE "akademik/nilai/$($script:CREATED['nilai'])"
        $ok = ($r.resCode -eq 202 -or $r.resCode -eq 200 -or $r.resCode -eq 404)
        if ($ok) { Write-Host "  [CLEAN] DELETE nilai id=$($script:CREATED['nilai'])" -ForegroundColor DarkGray }
    }

    # Hapus jadwal test
    if ($script:CREATED['jadwal']) {
        $r = Api DELETE "akademik/jadwal/$($script:CREATED['jadwal'])"
        $ok = ($r.resCode -eq 202 -or $r.resCode -eq 200 -or $r.resCode -eq 404)
        if ($ok) { Write-Host "  [CLEAN] DELETE jadwal id=$($script:CREATED['jadwal'])" -ForegroundColor DarkGray }
    }

    # Hapus pengampu test (jika dibuat baru)
    if ($script:CREATED['pengampu']) {
        $r = Api DELETE "akademik/pengampu/$($script:CREATED['pengampu'])"
        $ok = ($r.resCode -eq 202 -or $r.resCode -eq 200 -or $r.resCode -eq 404)
        if ($ok) { Write-Host "  [CLEAN] DELETE pengampu id=$($script:CREATED['pengampu'])" -ForegroundColor DarkGray }
    }

    # Hapus siswa dari kelas (siswa_kelas)
    if ($script:CREATED['siswaKelas']) {
        $r = Api DELETE "akademik/kelas/assign/$($script:CREATED['siswaKelas'])"
        $ok = ($r.resCode -eq 202 -or $r.resCode -eq 200 -or $r.resCode -eq 404)
        if ($ok) { Write-Host "  [CLEAN] DELETE siswa_kelas id=$($script:CREATED['siswaKelas'])" -ForegroundColor DarkGray }
    }

    # Hapus mapel test
    if ($script:CREATED['mapel']) {
        $r = Api DELETE "mapel/$($script:CREATED['mapel'])"
        $ok = ($r.resCode -eq 202 -or $r.resCode -eq 200 -or $r.resCode -eq 404)
        if ($ok) { Write-Host "  [CLEAN] DELETE mapel id=$($script:CREATED['mapel'])" -ForegroundColor DarkGray }
    }

    # Hapus kelas test
    if ($script:CREATED['kelas']) {
        $r = Api DELETE "class/$($script:CREATED['kelas'])"
        $ok = ($r.resCode -eq 202 -or $r.resCode -eq 200 -or $r.resCode -eq 404)
        if ($ok) { Write-Host "  [CLEAN] DELETE kelas id=$($script:CREATED['kelas'])" -ForegroundColor DarkGray }
    }

    # Hapus user test
    foreach ($uid in @($testUserId, $siswaTestUserId, $kwTestUserId)) {
        if ($uid) {
            $r = Api DELETE "users/$uid"
            $ok = ($r.resCode -eq 202 -or $r.resCode -eq 200 -or $r.resCode -eq 404)
            if ($ok) { Write-Host "  [CLEAN] DELETE user id=$uid" -ForegroundColor DarkGray }
        }
    }

    Write-Host "  Cleanup selesai." -ForegroundColor DarkGray
}

# ──────────────────────────────────────────────
#  SUMMARY
# ──────────────────────────────────────────────
$total = $script:PASS + $script:FAIL
Write-Host ""
Write-Host "══════════════════════════════════════════" -ForegroundColor White
Write-Host "  TEST SUMMARY" -ForegroundColor White
Write-Host "  PASS : $($script:PASS)" -ForegroundColor Green
if ($script:FAIL -gt 0) {
    Write-Host "  FAIL : $($script:FAIL)" -ForegroundColor Red
} else {
    Write-Host "  FAIL : 0" -ForegroundColor Green
}
Write-Host "  SKIP : $($script:SKIP)" -ForegroundColor Yellow
Write-Host "  TOTAL: $total" -ForegroundColor White
if ($script:FAIL -eq 0) {
    Write-Host "  Semua test PASS!" -ForegroundColor Green
} else {
    Write-Host "  Ada $($script:FAIL) test GAGAL." -ForegroundColor Red
}
Write-Host "══════════════════════════════════════════" -ForegroundColor White
