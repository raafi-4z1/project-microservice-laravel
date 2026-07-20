# =============================================================
#  BACKUP DATABASES — Microservice Laravel
#  Dump semua database (baca nama+kredensial dari .env tiap service),
#  simpan sebagai .sql terkompres, lalu hapus backup lebih tua dari retensi.
#
#  Usage:
#    powershell -ExecutionPolicy Bypass -File backup-databases.ps1
#
#  Dua mode:
#    daily    (default) : rotasi harian, disimpan di db-backups\, dihapus > retensi.
#                         Untuk pemulihan bencana jangka pendek.
#    semester           : arsip jangka panjang per periode akademik. Nama diberi
#                         label semester AKTIF (dibaca dari DB), disimpan di
#                         db-backups\archive\, dan TIDAK PERNAH dihapus otomatis.
#                         Jalankan saat menutup/ganti semester.
#      powershell -File backup-databases.ps1              # daily
#      $env:BACKUP_MODE="semester"; powershell -File backup-databases.ps1
#
#  Opsional (override via env var sebelum menjalankan):
#    $env:BACKUP_DIR      = "D:\backups\sim-sekolah"   # default: .\db-backups
#    $env:RETENTION_DAYS  = "30"                        # default: 14 (mode daily saja)
#    $env:BACKUP_MODE     = "semester"                  # default: daily
#    $env:MYSQLDUMP       = "C:\path\mysqldump.exe"     # default: auto-deteksi Laragon
#
#  Jadwalkan lewat Task Scheduler (lihat catatan di README/percakapan).
#  Output .sql BERISI DATA — folder backup di-gitignore.
# =============================================================

$ErrorActionPreference = 'Stop'
$Root = $PSScriptRoot
$BackupDir     = if ($env:BACKUP_DIR)     { $env:BACKUP_DIR }     else { Join-Path $Root "db-backups" }
$RetentionDays = if ($env:RETENTION_DAYS) { [int]$env:RETENTION_DAYS } else { 14 }
$Mode          = if ($env:BACKUP_MODE)    { $env:BACKUP_MODE.ToLower() } else { "daily" }

# ── Temukan mysqldump ──
$MysqlDump = $env:MYSQLDUMP
if (-not $MysqlDump) {
    $MysqlDump = (Get-ChildItem "C:\laragon\bin\mysql" -Recurse -Filter "mysqldump.exe" -ErrorAction SilentlyContinue | Select-Object -First 1).FullName
}
if (-not $MysqlDump -or -not (Test-Path $MysqlDump)) {
    $cmd = Get-Command mysqldump.exe -ErrorAction SilentlyContinue
    if ($cmd) { $MysqlDump = $cmd.Source }
}
if (-not $MysqlDump) {
    Write-Host "ERROR: mysqldump.exe tidak ditemukan. Set `$env:MYSQLDUMP." -ForegroundColor Red
    exit 1
}

# ── Baca satu key dari file .env ──
function Get-EnvValue([string]$envPath, [string]$key) {
    if (-not (Test-Path $envPath)) { return $null }
    $line = Select-String -Path $envPath -Pattern "^\s*$key\s*=" -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not $line) { return $null }
    $val = ($line.Line -replace "^\s*$key\s*=", "").Trim()
    return $val.Trim('"').Trim("'")
}

$Services = @("Gateway","AkademikService","GuruService","SiswaService","KaryawanService","MapelService","ClassMicroservices")

$stamp = Get-Date -Format "yyyy-MM-dd_HHmmss"

# ── Mode semester: baca semester aktif dari DB akademik untuk label arsip ──
$destDir = $BackupDir
$prefix  = "backup"
if ($Mode -eq "semester") {
    $mysqlCli = $MysqlDump -replace "mysqldump\.exe$", "mysql.exe"
    $akEnv = Join-Path $Root "AkademikService\.env"
    $akDb   = Get-EnvValue $akEnv "DB_DATABASE"
    $akUser = Get-EnvValue $akEnv "DB_USERNAME"; if (-not $akUser) { $akUser = "root" }
    $akPass = Get-EnvValue $akEnv "DB_PASSWORD"
    $akHost = Get-EnvValue $akEnv "DB_HOST"; if (-not $akHost) { $akHost = "127.0.0.1" }
    $label = $null
    if ((Test-Path $mysqlCli) -and $akDb) {
        if ($null -ne $akPass) { $env:MYSQL_PWD = $akPass }
        try {
            $row = & $mysqlCli --host=$akHost --user=$akUser -N -e `
                "SELECT CONCAT(REPLACE(tahun_ajaran,'/','-'),'_sem',semester) FROM semester_aktif WHERE is_aktif=1 LIMIT 1;" $akDb 2>$null
            if ($row) { $label = ($row | Select-Object -First 1).Trim() }
        } catch {} finally { Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue }
    }
    if (-not $label) { $label = "unknown"; Write-Host "  (peringatan: semester aktif tak terbaca, label='unknown')" -ForegroundColor Yellow }
    $destDir = Join-Path $BackupDir "archive"
    $prefix  = "semester_$label"
    New-Item -ItemType Directory -Force -Path $destDir | Out-Null
    Write-Host "Mode      : SEMESTER (arsip permanen) — label=$label"
} else {
    Write-Host "Mode      : DAILY (retensi $RetentionDays hari)"
}

$sessionDir = Join-Path $destDir "_tmp_$stamp"
New-Item -ItemType Directory -Force -Path $sessionDir | Out-Null

Write-Host "Backup ke : $destDir"
Write-Host "mysqldump : $MysqlDump`n"

$ok = 0; $failCount = 0
foreach ($svc in $Services) {
    $envPath = Join-Path $Root "$svc\.env"
    $db   = Get-EnvValue $envPath "DB_DATABASE"
    $user = Get-EnvValue $envPath "DB_USERNAME"; if (-not $user) { $user = "root" }
    $pass = Get-EnvValue $envPath "DB_PASSWORD"
    $host_ = Get-EnvValue $envPath "DB_HOST"; if (-not $host_) { $host_ = "127.0.0.1" }
    $port = Get-EnvValue $envPath "DB_PORT"; if (-not $port) { $port = "3306" }

    if (-not $db) { Write-Host "  [SKIP] $svc : DB_DATABASE kosong" -ForegroundColor Yellow; continue }

    $outFile = Join-Path $sessionDir "$db.sql"
    # MYSQL_PWD menghindari password tampil di daftar proses / command line
    if ($null -ne $pass) { $env:MYSQL_PWD = $pass } else { Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue }

    try {
        & $MysqlDump --host=$host_ --port=$port --user=$user `
            --single-transaction --quick --routines --triggers --events `
            --default-character-set=utf8mb4 --databases $db 2>$null |
            Out-File -FilePath $outFile -Encoding utf8
        if ((Test-Path $outFile) -and (Get-Item $outFile).Length -gt 0) {
            $kb = [math]::Round((Get-Item $outFile).Length / 1KB)
            Write-Host "  [OK]   $svc -> $db.sql ($kb KB)" -ForegroundColor Green
            $ok++
        } else {
            Write-Host "  [FAIL] $svc -> $db (file kosong; cek kredensial/DB)" -ForegroundColor Red
            $failCount++
        }
    } catch {
        Write-Host "  [FAIL] $svc -> $db : $($_.Exception.Message)" -ForegroundColor Red
        $failCount++
    } finally {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }
}

# ── Kompres sesi backup jadi satu .zip, lalu hapus folder mentah ──
if ($ok -gt 0) {
    $zip = Join-Path $destDir "${prefix}_$stamp.zip"
    Compress-Archive -Path "$sessionDir\*" -DestinationPath $zip -Force
    Remove-Item -Recurse -Force $sessionDir
    $zkb = [math]::Round((Get-Item $zip).Length / 1KB)
    Write-Host "`nArsip: $zip ($zkb KB)" -ForegroundColor Green
} else {
    Remove-Item -Recurse -Force $sessionDir -ErrorAction SilentlyContinue
}

# ── Retensi: hanya mode daily. Arsip semester TIDAK pernah dihapus otomatis. ──
if ($Mode -ne "semester") {
    $cutoff = (Get-Date).AddDays(-$RetentionDays)
    $old = Get-ChildItem $BackupDir -Filter "backup_*.zip" -ErrorAction SilentlyContinue | Where-Object { $_.LastWriteTime -lt $cutoff }
    foreach ($o in $old) { Remove-Item $o.FullName -Force; Write-Host "  [HAPUS] backup lama: $($o.Name)" -ForegroundColor DarkGray }
}

$ket = if ($Mode -eq "semester") { "arsip permanen (tanpa auto-hapus)" } else { "retensi $RetentionDays hari" }
Write-Host "`nSelesai. Sukses: $ok, Gagal: $failCount, Mode: $Mode ($ket)"
exit $(if ($failCount -gt 0) { 1 } else { 0 })
