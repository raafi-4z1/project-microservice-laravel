<#
  setup-android-project.ps1
  ---------------------------------------------------------------------------
  Menyiapkan bundle dokumen untuk membangun app Android "SIM Sekolah".
  Skrip ini menyalin dokumen acuan (dari git + file lokal yang gitignored) ke
  folder project Android, membuat CLAUDE.md + .gitignore, lalu mencetak langkah
  berikutnya. Aman di-commit (tidak berisi kredensial apa pun).

  Jalankan dari root repo backend:
    powershell -ExecutionPolicy Bypass -File setup-android-project.ps1
    powershell -ExecutionPolicy Bypass -File setup-android-project.ps1 -Target "D:\proyek\sim-sekolah-android"

  Prasyarat: api-sample-responses.md sudah di-generate
    $env:TEST_ADMIN_PASSWORD = "..."; .\capture-api-samples.ps1
#>
param(
    # Lokasi project Android (default: sibling folder di samping repo backend)
    [string]$Target = (Join-Path (Split-Path $PSScriptRoot -Parent) "sim-sekolah-android")
)

$ErrorActionPreference = "Stop"
$src  = $PSScriptRoot
$docs = Join-Path $Target "docs"
$uiux = Join-Path $docs "ui-ux"

Write-Host "Menyiapkan bundle Android di: $Target" -ForegroundColor Cyan
New-Item -ItemType Directory -Force -Path $uiux | Out-Null

# --- Salin dokumen acuan ke docs/ ---
$files = @(
    "android-app-prompt.md",
    "android-build-phases.md",
    "api-sample-responses.md",
    "Microservice Laravel.postman_collection.json"
)
$missing = @()
foreach ($f in $files) {
    $sp = Join-Path $src $f
    if (Test-Path $sp) {
        Copy-Item -LiteralPath $sp -Destination (Join-Path $docs $f) -Force
        Write-Host "  [OK]  docs/$f" -ForegroundColor Green
    } else {
        $missing += $f
        Write-Host "  [!!]  $f tidak ditemukan di repo" -ForegroundColor Yellow
    }
}

# --- Placeholder folder ui-ux ---
$keep = Join-Path $uiux "TARUH-SCREENSHOT-DISINI.txt"
if (-not (Test-Path $keep)) {
    Set-Content -LiteralPath $keep -Encoding UTF8 -Value "Taruh screenshot referensi UI/UX (.png/.jpg) desain sekolah di folder ini."
}

# --- CLAUDE.md (pointer file untuk project Android) ---
$claude = @'
# SIM Sekolah - Android

Klien Android (Kotlin + Compose Material 3, MVVM) untuk backend microservice
Laravel "SIM Sekolah". Semua request lewat Gateway.

## Dokumen - WAJIB dibaca ulang di awal tiap fase (sesi baru tak bawa ingatan)
- `docs/android-app-prompt.md` - spesifikasi lengkap app (tech stack, struktur
  folder, modul & endpoint, konvensi data, layar, keamanan)
- `docs/android-build-phases.md` - panduan eksekusi fase-per-fase + Gate + Jebakan
- `docs/api-sample-responses.md` - capture response ASLI + tabel akun test
  (acuan DTO - jangan menebak bentuk request/response, cek di sini)
- `docs/Microservice Laravel.postman_collection.json` - semua request siap coba
- `docs/ui-ux/` - screenshot acuan desain

## Jaringan (backend)
- Base URL dari emulator Android : https://10.0.2.2/api
- Base URL dari device fisik (LAN): https://IP-SERVER/api   <-- ganti IP-nya
- SSL: sertifikat mkcert lokal -> trust-all HANYA di build debug.
- Auth user: Bearer (OAuth2 Passport), header "Authorization: Bearer <token>".
- Mode Terminal (absensi kiosk): client TERPISAH dengan header
  X-Terminal-Id + X-Terminal-Token (bukan Bearer).

## Perintah build
- ./gradlew assembleDebug   -> build debug
- ./gradlew installDebug    -> pasang ke emulator/device
- ./gradlew assembleRelease -> build rilis (Fase 9)

## Urutan fase (satu fase = satu sesi = satu commit)
0.  Persiapan (manual)                    6A.  Karyawan (master data)
1.  Scaffold project                      6B.  Absensi: kartu, wali, rekap
2.  Core network + Auth                   6C.  Absensi: pelajaran, keluar, PIN
3.  Master data (Mapel/Kelas/Guru/Siswa)  6C-2. Periode khusus, jam per periode/
4.  Akademik dasar (+ cache ID->nama)           hari, pengaturan absensi
5.  Nilai + bobot                         6D.  Absensi: Mode Terminal (kiosk)
6.  Raport & ranking (+ mode /saya)       7.   User mgmt + profil + tema
                                          8.   QA per role + responsif
                                          9.   Build rilis (opsional)

Salin prompt tiap fase dari docs/android-build-phases.md, mulai dengan Plan Mode.

## Aturan kerja
- Verifikasi SELALU ke backend sungguhan dengan akun test (bukan mock).
- Jangan tulis token/password/data siswa ke Log/logcat.
- JANGAN commit docs/api-sample-responses.md (berisi akun test) - sudah di .gitignore.
- Kalau ragu bentuk data, cek docs/api-sample-responses.md dulu, bukan menebak.
'@
Set-Content -LiteralPath (Join-Path $Target "CLAUDE.md") -Encoding UTF8 -Value $claude

# --- .gitignore untuk project Android ---
$gi = @'
# Dikirim lewat jalur lain (berisi akun test + capture DB dev) - jangan commit
docs/api-sample-responses.md

# Android / Gradle
.gradle/
build/
/build/
local.properties
*.keystore
*.jks
key.properties
.idea/
.DS_Store
captures/
'@
Set-Content -LiteralPath (Join-Path $Target ".gitignore") -Encoding UTF8 -Value $gi

Write-Host ""
Write-Host "Selesai. Bundle siap di: $Target" -ForegroundColor Cyan
if ($missing.Count -gt 0) {
    Write-Host "PERINGATAN - file berikut belum ada, salin manual setelah tersedia:" -ForegroundColor Yellow
    foreach ($m in $missing) {
        if ($m -eq "api-sample-responses.md") {
            Write-Host "  - $m  (generate dulu: `$env:TEST_ADMIN_PASSWORD='...'; .\capture-api-samples.ps1)" -ForegroundColor Yellow
        } else {
            Write-Host "  - $m" -ForegroundColor Yellow
        }
    }
}
Write-Host ""
Write-Host "Langkah berikutnya:" -ForegroundColor White
Write-Host "  1. Taruh screenshot desain di docs/ui-ux/"
Write-Host "  2. Edit CLAUDE.md -> sesuaikan IP backend LAN (baris IP-SERVER)"
Write-Host "  3. Buat project Android di folder ini (Android Studio), atau init git"
Write-Host "  4. Mulai Fase 1: salin prompt dari docs/android-build-phases.md ke Claude Code (Plan Mode)"
