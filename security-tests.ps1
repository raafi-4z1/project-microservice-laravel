# =============================================================
#  SECURITY TEST SUITE — Microservice Laravel
#  Menguji postur keamanan dari sudut pandang KLIEN JARINGAN.
#  Jalankan dari PC penguji (mis. 192.168.12.181), bukan dari host backend,
#  agar pemeriksaan "service internal terekspos ke LAN" bermakna.
#
#  Usage (dari PC penguji):
#    $env:TEST_BASE_URL       = "https://gateway.test/api"   # via hosts entry ke IP backend
#    $env:TEST_BACKEND_HOST   = "192.168.12.168"             # IP backend (untuk probe Host-spoof)
#    $env:TEST_ADMIN_PASSWORD = "PasswordSuperAdmin"
#    powershell -ExecutionPolicy Bypass -File security-tests.ps1
#
#  TEST_BASE_URL      : wajib pakai Host gateway.test (Apache routing per Host header).
#                       Di PC penguji tambahkan hosts: <IP backend>  gateway.test
#  TEST_BACKEND_HOST  : opsional; jika di-set, menguji apakah service internal
#                       (akademikservice.test dst.) bisa dijangkau langsung via
#                       Host-header spoofing ke IP backend. Kosongkan untuk skip.
#  TEST_ADMIN_PASSWORD: opsional; jika di-set, mengaktifkan uji auth/RBAC/throttle.
#
#  Kode keluar: 0 jika tidak ada FAIL, 1 jika ada temuan FAIL.
# =============================================================

$BaseUrl     = if ($env:TEST_BASE_URL) { $env:TEST_BASE_URL } else { "https://gateway.test/api" }
$BackendHost = $env:TEST_BACKEND_HOST
$AdminEmail  = if ($env:TEST_ADMIN_EMAIL) { $env:TEST_ADMIN_EMAIL } else { "superadmin@example.com" }
$AdminPass   = $env:TEST_ADMIN_PASSWORD

try {
    Add-Type @"
using System.Net;
using System.Security.Cryptography.X509Certificates;
public class TrustAllSec : ICertificatePolicy {
    public bool CheckValidationResult(ServicePoint sp, X509Certificate cert, WebRequest req, int prob) { return true; }
}
"@
} catch {}
[System.Net.ServicePointManager]::CertificatePolicy = New-Object TrustAllSec
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$script:PASS = 0; $script:FAIL = 0; $script:WARN = 0; $script:SKIP = 0

function Pass([string]$m) { Write-Host "  [PASS] $m" -ForegroundColor Green;  $script:PASS++ }
function Fail([string]$m) { Write-Host "  [FAIL] $m" -ForegroundColor Red;    $script:FAIL++ }
function Warn([string]$m) { Write-Host "  [WARN] $m" -ForegroundColor Yellow; $script:WARN++ }
function Skip([string]$m) { Write-Host "  [SKIP] $m" -ForegroundColor DarkGray; $script:SKIP++ }
function Sec([string]$t)  { Write-Host ""; Write-Host "== $t" -ForegroundColor Cyan }
function Info([string]$m) { Write-Host "       $m" -ForegroundColor DarkGray }

# Robust HTTP: bedakan gagal-koneksi (status 0) vs status HTTP
# -NoFollow: JANGAN ikuti redirect. Wajib untuk probe port 80, karena vhost :80
# mengarahkan ke https://%{HTTP_HOST}/... — host aslinya dipertahankan. Kalau
# redirect diikuti dari PC yang punya entri hosts *.test, request malah mendarat
# di service LOKAL milik penguji dan hasilnya salah baca. 301 itu sendiri sudah
# jawaban yang benar: request tidak pernah sampai ke service internal.
function Http {
    param([string]$Method, [string]$Uri, [hashtable]$Headers = @{}, [string]$Body = $null, [switch]$Raw, [switch]$NoFollow)
    $h = @{ Accept = 'application/json' }
    foreach ($k in $Headers.Keys) { $h[$k] = $Headers[$k] }
    # PS 5.1: Invoke-WebRequest -MaximumRedirection 0 melempar tanpa objek Response,
    # sehingga status 301 hilang dan redirect salah dibaca sebagai gagal koneksi.
    # Pakai HttpWebRequest yang mengembalikan status 3xx apa adanya.
    if ($NoFollow) {
        try {
            $req = [System.Net.HttpWebRequest]::Create($Uri)
            $req.Method = $Method; $req.AllowAutoRedirect = $false; $req.Timeout = 12000
            foreach ($k in $h.Keys) {
                if ($k -eq 'Host')   { $req.Host = $h[$k] }
                elseif ($k -eq 'Accept') { $req.Accept = $h[$k] }
                else { $req.Headers[$k] = $h[$k] }
            }
            $resp = $req.GetResponse()
            $txt = ([System.IO.StreamReader]::new($resp.GetResponseStream())).ReadToEnd()
            $st = [int]$resp.StatusCode; $hd = $resp.Headers; $resp.Close()
            return @{ status = $st; body = $txt; headers = $hd; ok = $true }
        } catch [System.Net.WebException] {
            $resp = $_.Exception.Response
            if ($resp) {
                $txt = ""
                try { $txt = ([System.IO.StreamReader]::new($resp.GetResponseStream())).ReadToEnd() } catch {}
                return @{ status = [int]$resp.StatusCode; body = $txt; headers = $resp.Headers; ok = $true }
            }
            return @{ status = 0; body = $_.Exception.Message; headers = $null; ok = $false }
        } catch {
            return @{ status = 0; body = $_.Exception.Message; headers = $null; ok = $false }
        }
    }
    $prm = @{ Uri = $Uri; Method = $Method; Headers = $h; UseBasicParsing = $true; TimeoutSec = 12 }
    if ($Body) { $h['Content-Type'] = 'application/json'; $prm.Body = $Body }
    try {
        $r = Invoke-WebRequest @prm
        return @{ status = [int]$r.StatusCode; body = $r.Content; headers = $r.Headers; ok = $true }
    } catch [System.Net.WebException] {
        $resp = $_.Exception.Response
        if ($resp) {
            $txt = ""
            try { $txt = ([System.IO.StreamReader]::new($resp.GetResponseStream())).ReadToEnd() } catch {}
            return @{ status = [int]$resp.StatusCode; body = $txt; headers = $resp.Headers; ok = $true }
        }
        return @{ status = 0; body = $_.Exception.Message; headers = $null; ok = $false }
    } catch {
        return @{ status = 0; body = $_.Exception.Message; headers = $null; ok = $false }
    }
}

# Derive scheme+host root dari BaseUrl (buang /api)
$root = $BaseUrl -replace '/api/?$', ''

Write-Host "=============================================================="
Write-Host "  SECURITY TEST — target: $BaseUrl"
if ($BackendHost) { Write-Host "  Backend host (probe internal): $BackendHost" }
Write-Host "=============================================================="

# ══════════════════ A. TRANSPORT ══════════════════
Sec "A. Transport (HTTPS / HTTP)"

$r = Http GET "$BaseUrl/akademik/semester/aktif"
if ($r.ok) { Pass "HTTPS Gateway terjangkau (status $($r.status))" }
else { Fail "HTTPS Gateway TIDAK terjangkau di $BaseUrl -- $($r.body)"; Info "Uji lain bergantung ini; pastikan hosts entry + backend expose 443" }

# HTTP polos harus ditolak/redirect (SESSION_SECURE_COOKIE=true -> niat HTTPS-only)
$httpRoot = $root -replace '^https://', 'http://'
$rh = Http GET "$httpRoot/api/akademik/semester/aktif" -NoFollow
if (-not $rh.ok) { Pass "HTTP polos (port 80) tidak melayani API (bagus)" }
elseif ($rh.status -in 301,302,308) { Pass "HTTP polos redirect ke HTTPS (status $($rh.status))" }
else { Warn "HTTP polos melayani API (status $($rh.status)) -- idealnya redirect 301 ke HTTPS atau ditutup" }

# ══════════════════ B. EKSPOSUR SERVICE INTERNAL ══════════════════
Sec "B. Eksposur Service Internal (batas HMAC)"
if ($BackendHost) {
    $internal = @("akademikservice.test","guruservice.test","siswaservice.test","mapelservice.test","classmicroservices.test")
    foreach ($svc in $internal) {
        # Diskriminator TANPA rate-limit: GET /api/user (spoof Host ke service internal).
        #  - Mendarat di GATEWAY (auth:api)               -> 401 "Unauthenticated"
        #  - Mendarat di SERVICE INTERNAL (HMAC duluan)   -> 401 "Request expired." /
        #    "Anda tidak memiliki akses." = BYPASS, hanya HMAC yang menjaga service internal
        #  - Koneksi ditolak                              -> tidak terekspos sama sekali (ideal)
        $reached = $false
        foreach ($scheme in @("https","http")) {
            if ($scheme -eq 'http') { $r = Http GET "${scheme}://${BackendHost}/api/user" @{ Host = $svc } -NoFollow }
            else                    { $r = Http GET "${scheme}://${BackendHost}/api/user" @{ Host = $svc } }
            if (-not $r.ok) { continue }
            $reached = $true
            if ($r.status -in 301,302,308) {
                # Ditangani vhost :80 (redirect ke HTTPS) — tidak pernah mencapai service internal.
                Pass "[$scheme] Host-spoof $svc dijawab redirect ke HTTPS -- tidak mendarat di service internal"
            } elseif ($r.body -match 'memiliki akses|Request expired') {
                Fail "[$scheme] $svc DAPAT DIAKSES LANGSUNG dari LAN (mendarat di service internal, dijaga HMAC saja) -- BYPASS GATEWAY"
            } elseif ($r.body -match 'Unauthenticated') {
                Pass "[$scheme] Host-spoof $svc diarahkan ke Gateway (Apache default vhost) -- service internal tidak terekspos"
            } else {
                Warn "[$scheme] $svc -> respons tak dikenali (status $($r.status)) -- tinjau manual"
            }
        }
        if (-not $reached) { Pass "$svc TIDAK terjangkau langsung dari LAN (koneksi ditolak) -- ideal" }
    }
} else {
    Skip "Probe service internal (set TEST_BACKEND_HOST=<IP backend> untuk mengaktifkan)"
}

# ══════════════════ C. AUTENTIKASI ══════════════════
Sec "C. Autentikasi"
$r = Http GET "$BaseUrl/users"
if ($r.status -eq 401) { Pass "Endpoint terproteksi tanpa token -> 401" }
else { Fail "GET /users tanpa token -> $($r.status) (harusnya 401)" }

$r = Http GET "$BaseUrl/users" @{ Authorization = "Bearer token-palsu-xyz" }
if ($r.status -eq 401) { Pass "Token invalid -> 401" }
else { Fail "Token invalid -> $($r.status) (harusnya 401)" }

$r = Http GET "$BaseUrl/user" @{ Authorization = "Bearer " }
if ($r.status -eq 401) { Pass "Bearer kosong -> 401" }
else { Warn "Bearer kosong -> $($r.status)" }

# ══════════════════ D. INFO DISCLOSURE ══════════════════
Sec "D. Info Disclosure"

# File sensitif tidak boleh terekspos
foreach ($f in @(".env",".git/config","composer.json","storage/logs/laravel.log","artisan")) {
    $r = Http GET "$root/$f"
    if ($r.ok -and $r.status -eq 200) { Fail "File sensitif TEREKSPOS: /$f (200, $($r.body.Length) bytes)" }
    else { Pass "/$f tidak terekspos ($(if($r.ok){$r.status}else{'no-conn'}))" }
}

# Security headers
$r = Http GET "$BaseUrl/akademik/semester/aktif"
if ($r.headers) {
    $checks = @{
        'Strict-Transport-Security' = 'HSTS — paksa HTTPS di browser'
        'X-Content-Type-Options'    = 'cegah MIME sniffing (nosniff)'
        'X-Frame-Options'           = 'cegah clickjacking'
    }
    foreach ($hn in $checks.Keys) {
        if ($r.headers[$hn]) { Pass "Header $hn ada" }
        else { Warn "Header $hn tidak ada -- $($checks[$hn])" }
    }
    if ($r.headers['X-Powered-By'] -or $r.headers['Server']) {
        Warn "Header versi server terekspos (X-Powered-By/Server) -- sebaiknya disembunyikan"
    } else { Pass "Header versi server tidak terekspos" }
} else { Skip "Security headers (tidak dapat membaca header)" }

# APP_DEBUG leak: picu error, cek apakah 'data' membocorkan pesan internal
$r = Http POST "$BaseUrl/login" @{} '{"email":"probe@x.com"'   # JSON rusak
$leaked = $false
if ($r.body -match 'SQLSTATE|Stack trace|vendor\\|/vendor/|Exception|Syntax error|at line \d+ of') { $leaked = $true }
if ($leaked) { Fail "Response error membocorkan detail internal (APP_DEBUG=true / handler mengembalikan getMessage). Body: $($r.body.Substring(0,[Math]::Min(120,$r.body.Length)))" }
else { Pass "Response error tidak membocorkan detail internal yang jelas" }

# ══════════════════ E. INJECTION SURFACE ══════════════════
Sec "E. Injection Surface (probe, butuh token)"
if ($AdminPass) {
    $lg = Http POST "$BaseUrl/login" @{} (@{ email = $AdminEmail; password = $AdminPass; device_name = "sectest" } | ConvertTo-Json)
    if ($lg.status -eq 200) {
        $tok = ($lg.body | ConvertFrom-Json).data.token
        $auth = @{ Authorization = "Bearer $tok" }
        # SQLi klasik di param search — binding aman harus mengembalikan 200 + hasil kosong, bukan 500
        $payload = [uri]::EscapeDataString("' OR '1'='1")
        $r = Http GET "$BaseUrl/users?search=$payload" $auth
        if ($r.status -eq 500) { Fail "SQLi probe di ?search memicu 500 -- kemungkinan query tidak ter-binding" }
        elseif ($r.status -eq 200) {
            $total = ($r.body | ConvertFrom-Json).data.total
            Pass "SQLi probe di ?search aman (200, total=$total, binding bekerja)"
        } else { Warn "SQLi probe -> status $($r.status)" }

        # Path traversal di detail (query param numerik)
        $r = Http GET "$BaseUrl/guru?idGuru=$([uri]::EscapeDataString('1 OR 1=1'))" $auth
        if ($r.status -eq 500) { Fail "idGuru non-numerik memicu 500" }
        else { Pass "idGuru non-numerik ditangani ($($r.status))" }

        Http POST "$BaseUrl/logout" $auth | Out-Null
    } else { Skip "Injection probe (login admin gagal: $($lg.status))" }
} else { Skip "Injection probe (set TEST_ADMIN_PASSWORD)" }

# ══════════════════ F. RBAC & TOKEN LIFECYCLE ══════════════════
Sec "F. RBAC & Token Lifecycle (butuh token admin)"
if ($AdminPass) {
    $lg = Http POST "$BaseUrl/login" @{} (@{ email = $AdminEmail; password = $AdminPass; device_name = "sectest2" } | ConvertTo-Json)
    if ($lg.status -eq 200) {
        $tok = ($lg.body | ConvertFrom-Json).data.token
        $auth = @{ Authorization = "Bearer $tok" }

        # Tidak bisa buat SuperAdmin via API
        $r = Http POST "$BaseUrl/register" $auth (@{ name="x"; email="sec_$(Get-Random)@x.com"; password="SecPass123"; confirm_password="SecPass123"; role="SuperAdmin" } | ConvertTo-Json)
        if ($r.status -eq 422) { Pass "Register role SuperAdmin ditolak (422)" }
        else { Fail "Register SuperAdmin -> $($r.status) (harusnya 422)" }

        # Password lemah ditolak
        $r = Http POST "$BaseUrl/register" $auth (@{ name="x"; email="sec_$(Get-Random)@x.com"; password="123"; confirm_password="123"; role="Karyawan" } | ConvertTo-Json)
        if ($r.status -eq 422) { Pass "Password lemah ditolak saat register (422)" }
        else { Warn "Password lemah -> $($r.status)" }

        # Token lifecycle: logout lalu pakai token yang sama -> 401
        Http POST "$BaseUrl/logout" $auth | Out-Null
        $r = Http GET "$BaseUrl/user" $auth
        if ($r.status -eq 401) { Pass "Token setelah logout ditolak (401) -- revocation bekerja" }
        else { Fail "Token setelah logout -> $($r.status) (harusnya 401)" }
    } else { Skip "RBAC test (login admin gagal: $($lg.status))" }
} else { Skip "RBAC & token lifecycle (set TEST_ADMIN_PASSWORD)" }

# ══════════════════ G. RATE LIMIT (jalan terakhir — mengunci login 1 menit) ══════════════════
Sec "G. Rate Limit / Brute-force (login akan terkunci 1 menit setelah ini)"
if ($AdminPass -and $env:SEC_TEST_RATELIMIT -eq "1") {
    $got429 = $false
    for ($i = 1; $i -le 7; $i++) {
        $r = Http POST "$BaseUrl/login" @{} (@{ email = "brute_$i@x.com"; password = "salah$i" } | ConvertTo-Json)
        if ($r.status -eq 429) { $got429 = $true; Info "Percobaan ke-$i -> 429 (throttle aktif)"; break }
    }
    if ($got429) { Pass "Login throttle aktif (429 sebelum percobaan ke-7)" }
    else { Fail "Login TIDAK throttle setelah 7 percobaan -- rentan brute-force" }
    Info "Login terkunci ~1 menit; tunggu sebelum menjalankan run-tests.ps1"
} else {
    Skip "Rate limit test (set SEC_TEST_RATELIMIT=1 untuk aktifkan; mengunci login 1 menit)"
}

# ══════════════════ RINGKASAN ══════════════════
Write-Host ""
Write-Host "=============================================================="
Write-Host "  RINGKASAN KEAMANAN"
Write-Host "  PASS : $($script:PASS)"  -ForegroundColor Green
Write-Host "  WARN : $($script:WARN) (hardening — bukan celah aktif)" -ForegroundColor Yellow
Write-Host "  FAIL : $($script:FAIL) (perlu tindakan)" -ForegroundColor $(if($script:FAIL){'Red'}else{'Green'})
Write-Host "  SKIP : $($script:SKIP)"
Write-Host "=============================================================="

exit $(if ($script:FAIL -gt 0) { 1 } else { 0 })
