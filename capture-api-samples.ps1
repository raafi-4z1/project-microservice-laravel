# =============================================================
#  CAPTURE API SAMPLES — hasil: api-sample-responses.md
#  Merekam response JSON ASLI dari semua endpoint utama sebagai
#  referensi DTO untuk pengembangan app client (Android, dsb).
#
#  Usage:
#    $env:TEST_ADMIN_PASSWORD = "PasswordSuperAdmin"
#    powershell -ExecutionPolicy Bypass -File capture-api-samples.ps1
#
#  Prasyarat: seed-test-accounts.ps1 sudah dijalankan (akun test tersedia).
#  Catatan: membuat data sementara (kelas/mapel/pengampu/jadwal/nilai)
#  lalu menghapusnya kembali. Field foto & token dipotong di output.
# =============================================================

if (-not $env:TEST_ADMIN_PASSWORD) {
    Write-Host "ERROR: set dulu `$env:TEST_ADMIN_PASSWORD" -ForegroundColor Red
    exit 1
}

$BaseUrl = "https://gateway.test/api"
$OutFile = Join-Path $PSScriptRoot "api-sample-responses.md"

try {
    Add-Type @"
using System.Net;
using System.Security.Cryptography.X509Certificates;
public class TrustAllCap : ICertificatePolicy {
    public bool CheckValidationResult(ServicePoint sp, X509Certificate cert, WebRequest req, int prob) { return true; }
}
"@
} catch {}
[System.Net.ServicePointManager]::CertificatePolicy = New-Object TrustAllCap
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$script:TOKEN = $null
$TS = Get-Date -Format "HHmmss"
$MD = New-Object System.Text.StringBuilder

# ── API helper: kembalikan raw body + status ──
function RawApi {
    param([string]$Method, [string]$Path, [hashtable]$Body = $null, [string]$Token = $null, [switch]$NoAuth)
    $headers = @{ 'Accept' = 'application/json' }
    if (-not $NoAuth) {
        $tok = if ($Token) { $Token } else { $script:TOKEN }
        if ($tok) { $headers['Authorization'] = "Bearer $tok" }
    }
    $params = @{ Uri = "$BaseUrl/$Path"; Method = $Method; Headers = $headers; UseBasicParsing = $true; TimeoutSec = 30 }
    $bodyJson = $null
    if ($Body -ne $null) {
        $headers['Content-Type'] = 'application/json'
        $bodyJson = ($Body | ConvertTo-Json -Depth 6)
        $params['Body'] = $bodyJson
    }
    try {
        $r = Invoke-WebRequest @params
        return @{ code = [int]$r.StatusCode; raw = $r.Content; body = $bodyJson }
    } catch {
        $er = $_.Exception.Response
        if ($er) {
            try {
                $code = [int]$er.StatusCode
                $rd = [System.IO.StreamReader]::new($er.GetResponseStream())
                $tx = $rd.ReadToEnd(); $rd.Close(); $er.Close()
                return @{ code = $code; raw = $tx; body = $bodyJson }
            } catch {}
        }
        return @{ code = 0; raw = ('{"error":"' + $_.Exception.Message + '"}'); body = $bodyJson }
    }
}

# ── Pretty printer setia-byte (tanpa parse ulang, hindari bug ConvertTo-Json PS5.1) ──
function Format-Json([string]$json) {
    $sb = New-Object System.Text.StringBuilder
    $indent = 0; $inStr = $false; $esc = $false
    foreach ($ch in $json.ToCharArray()) {
        if ($esc) { [void]$sb.Append($ch); $esc = $false; continue }
        if ($inStr) {
            [void]$sb.Append($ch)
            if ($ch -eq '\') { $esc = $true } elseif ($ch -eq '"') { $inStr = $false }
            continue
        }
        if     ($ch -eq '"') { $inStr = $true; [void]$sb.Append($ch) }
        elseif ($ch -eq '{' -or $ch -eq '[') { $indent++; [void]$sb.Append($ch); [void]$sb.Append("`n" + ('  ' * $indent)) }
        elseif ($ch -eq '}' -or $ch -eq ']') { $indent--; [void]$sb.Append("`n" + ('  ' * $indent)); [void]$sb.Append($ch) }
        elseif ($ch -eq ',') { [void]$sb.Append($ch); [void]$sb.Append("`n" + ('  ' * $indent)) }
        elseif ($ch -eq ':') { [void]$sb.Append(': ') }
        elseif ($ch -notmatch '\s') { [void]$sb.Append($ch) }
    }
    $out = $sb.ToString() -replace '\{\s+\}', '{}' -replace '\[\s+\]', '[]'
    return $out
}

# ── Potong data panjang (foto base64, token) agar dokumen tetap ringkas ──
function Redact([string]$raw) {
    $ev = [System.Text.RegularExpressions.MatchEvaluator]{ param($m) $m.Value.Substring(0, 48) + '...<dipotong>' }
    # JSON meng-escape '/' menjadi '\/', jadi pola harus menoleransi keduanya
    $raw = [regex]::Replace($raw, 'data:image\\?/[a-z]+;base64,(?:[A-Za-z0-9+=]|\\/){200,}', $ev)
    $raw = [regex]::Replace($raw, '(?<="token":")[A-Za-z0-9._\-]{60,}(?=")', $ev)
    return $raw
}

function Add-Sample {
    param([string]$Method, [string]$Path, [hashtable]$Result, [string]$Note = "")
    [void]$MD.AppendLine("### ``$Method /$Path``")
    if ($Note) { [void]$MD.AppendLine(""); [void]$MD.AppendLine($Note) }
    if ($Result.body) {
        [void]$MD.AppendLine(""); [void]$MD.AppendLine("Request body:")
        [void]$MD.AppendLine('```json'); [void]$MD.AppendLine((Format-Json $Result.body)); [void]$MD.AppendLine('```')
    }
    [void]$MD.AppendLine(""); [void]$MD.AppendLine("Response (HTTP $($Result.code)):")
    [void]$MD.AppendLine('```json'); [void]$MD.AppendLine((Format-Json (Redact $Result.raw))); [void]$MD.AppendLine('```')
    [void]$MD.AppendLine("")
    Write-Host "  [$($Result.code)] $Method /$Path"
}

function Add-Section([string]$Title) {
    [void]$MD.AppendLine("---"); [void]$MD.AppendLine(""); [void]$MD.AppendLine("## $Title"); [void]$MD.AppendLine("")
    Write-Host "`n=== $Title ===" -ForegroundColor Cyan
}

# Catatan bebas (untuk endpoint yang responsnya BUKAN JSON, mis. unduhan berkas)
function Add-Note([string]$Text) {
    [void]$MD.AppendLine(""); [void]$MD.AppendLine($Text); [void]$MD.AppendLine("")
}

function Enc([string]$s) { return [uri]::EscapeDataString($s) }

# ══════════════════ HEADER DOKUMEN ══════════════════
$today = Get-Date -Format "yyyy-MM-dd"
[void]$MD.AppendLine(@"
# Contoh Response API - SIM Sekolah (hasil capture asli)

Dokumen ini berisi response JSON **asli** yang direkam dari Gateway pada $today,
sebagai referensi bentuk DTO untuk pengembangan aplikasi client (Android, dsb).

- Regenerate: ``powershell -ExecutionPolicy Bypass -File capture-api-samples.ps1``
  (set ```$env:TEST_ADMIN_PASSWORD`` dulu; jalankan ``seed-test-accounts.ps1`` sekali sebelumnya)
- Field ``foto`` (base64 WebP) dan ``token`` dipotong agar dokumen ringkas -- panjang aslinya ribuan karakter.
- Semua response memakai envelope tetap: ``resCode``, ``resPhrase``, ``resStatus`` (``success``/``fail``), ``resMsg``, ``data``.
- Endpoint list master data paginated: isi sebenarnya di ``data.data``, metadata paginator di ``data.current_page``, ``data.last_page``, ``data.total``, dst.

## Akun test (hasil seed-test-accounts.ps1)

| Role | Email | Password | Keterangan |
|---|---|---|---|
| Admin | akuntest.admin@example.com | AdminTest123 | Akun baru |
| Karyawan | akuntest.karyawan@example.com | KaryawanTest123 | Karyawan BIASA — tanpa hak manajemen (pembanding) |
| **Administrator Sekolah** | akuntest.adminsekolah@example.com | AdminSekolahTest123 | Karyawan bertanda `isAdminSekolah: true` — boleh manajemen akademik. Pakai untuk menguji gating menu |
| Guru | andi.susanto2@sekolah.com | GuruTest123 | Terhubung record guru id=1 (punya pengampu) |
| Siswa | andi.siswa1@sekolah.com | SiswaTest123 | Terhubung record siswa id=1 (terdaftar di kelas) |

## PENTING: bentuk response berbeda per role

Sebagian besar contoh di bawah direkam sebagai **SuperAdmin**, jadi memperlihatkan
bentuk **terlengkap**. Untuk viewer lain, Gateway **membuang** field data pribadi —
field-nya tidak dikirim sama sekali, bukan dikirim bernilai null.

| Detail | Pengelola (SuperAdmin/Admin/Adm. Sekolah) | Guru | Karyawan biasa | Siswa |
|---|:-:|:-:|:-:|:-:|
| ``guru?idGuru=`` | penuh | publik | publik | publik |
| ``karyawan?idKaryawan=`` | penuh | publik | publik | 403 |
| ``siswa?idSiswa=`` & ``siswa/all`` | penuh | penuh | **403** | publik |
| nilai / raport / ranking / rekap absensi siswa | ya | sesuai lingkup wali/pengampu | **403** | hanya ``/saya`` |

**Konsekuensi untuk klien:** buat SEMUA field pribadi di DTO detail **nullable**.
DTO non-nullable akan melempar ``MissingFieldException`` saat membuka layar dengan
akun karyawan biasa atau siswa — layarnya blank, bukan sekadar kosong. Contoh
nyata tiap bentuk ada di bagian **Perilaku per Role** di akhir dokumen.

"@)

# ══════════════════ 1. AUTH ══════════════════
Add-Section "Auth"
$sw = [System.Diagnostics.Stopwatch]::StartNew()

$r = RawApi POST "login" @{ email = "superadmin@example.com"; password = $env:TEST_ADMIN_PASSWORD; device_name = "web" }
if ($r.code -ne 200) { Write-Host "FATAL: login gagal" -ForegroundColor Red; exit 1 }
$r.body = ($r.body -replace [regex]::Escape($env:TEST_ADMIN_PASSWORD), 'PasswordSuperAdmin')
Add-Sample POST "login" $r "Login sukses. ``device_name`` opsional (default ``web``)."
$script:TOKEN = ($r.raw | ConvertFrom-Json).data.token

Add-Sample GET "user" (RawApi GET "user") "Profil akun yang sedang login."

# ══════════════════ 2. MANAJEMEN USER ══════════════════
Add-Section "Manajemen User (SuperAdmin/Admin)"
Add-Sample GET "users`?page=1&per_page=3" (RawApi GET "users`?page=1&per_page=3") "Bentuk paginator Laravel: perhatikan ``data.data`` (array user) di dalam ``data``."
$adm = RawApi GET "users`?search=akuntest.admin"
Add-Sample GET "users`?search=akuntest.admin" $adm "Search partial di nama/email. Bisa digabung ``role=`` (filter exact) dan ``per_page``."
$admId = (($adm.raw | ConvertFrom-Json).data.data | Select-Object -First 1).id
Add-Sample GET "users/$admId" (RawApi GET "users/$admId")

$tmpEmail = "sampleuser_$TS@example.com"
$r = RawApi POST "register" @{ name = "Sample User"; email = $tmpEmail; password = "SamplePass123"; confirm_password = "SamplePass123"; role = "Karyawan" }
Add-Sample POST "register" $r
$tmpId = $null
$u = RawApi GET "users`?search=sampleuser_$TS"
foreach ($x in @(($u.raw | ConvertFrom-Json).data.data)) { if ($x.email -eq $tmpEmail) { $tmpId = $x.id } }
if ($tmpId) {
    Add-Sample POST "users/$tmpId/password" (RawApi POST "users/$tmpId/password" @{ new_password = "ResetPass456"; confirm_password = "ResetPass456" }) "Reset password user lain. Semua token aktif milik target dicabut."
    Add-Sample DELETE "users/$tmpId" (RawApi DELETE "users/$tmpId") "Soft delete + semua token target dicabut."
}

# ══════════════════ 3. MAPEL ══════════════════
Add-Section "Mata Pelajaran"
Add-Sample GET "mapel/all`?page=1&per_page=3" (RawApi GET "mapel/all`?page=1&per_page=3")
Add-Sample GET "mapel`?idPelajaran=1" (RawApi GET "mapel`?idPelajaran=1")
$r = RawApi POST "mapel" @{ kode = "SMPL$TS"; namaPelajaran = "Mapel Contoh $TS"; keterangan = "Dibuat oleh capture-api-samples.ps1" }
Add-Sample POST "mapel" $r
$tmpMapelId = ($r.raw | ConvertFrom-Json).data.idPelajaran
if ($tmpMapelId) {
    Add-Sample POST "mapel/update" (RawApi POST "mapel/update" @{ idPelajaran = $tmpMapelId; keterangan = "Keterangan diubah" }) "Update parsial: kirim ``idPelajaran`` + field yang berubah saja."
    Add-Sample DELETE "mapel/$tmpMapelId" (RawApi DELETE "mapel/$tmpMapelId")
}

# ══════════════════ 4. KELAS ══════════════════
Add-Section "Kelas (Ruang Kelas)"
Add-Sample GET "class/all`?page=1&per_page=3" (RawApi GET "class/all`?page=1&per_page=3") "``namaKelas`` dibentuk server dari tingkat+jurusan+noKelas. ``deletedAt`` null = aktif."
Add-Sample GET "class`?idKelas=1" (RawApi GET "class`?idKelas=1")
$r = RawApi POST "class" @{ noKelas = 96; tingkat = 3; jurusan = "IPS"; limitSiswa = 30 }
Add-Sample POST "class" $r
$tmpKelasId = ($r.raw | ConvertFrom-Json).data.idKelas
if ($tmpKelasId) {
    Add-Sample POST "class/update" (RawApi POST "class/update" @{ idKelas = $tmpKelasId; limitSiswa = 32 })
}

# ══════════════════ 5. GURU ══════════════════
Add-Section "Guru"
Add-Sample GET "guru/all`?page=1&per_page=3" (RawApi GET "guru/all`?page=1&per_page=3") "List ringan tanpa foto/data pribadi."
Add-Sample GET "guru`?idGuru=1" (RawApi GET "guru`?idGuru=1") "Detail LENGKAP -- hanya diterima PENGELOLA (SuperAdmin, Admin, Administrator Sekolah). Guru, Siswa, dan karyawan biasa menerima versi tersaring (lihat bagian Perilaku per Role di bawah)."
Add-Sample GET "guru`?idGuru=99999" (RawApi GET "guru`?idGuru=99999") "ID tidak ada."

# ══════════════════ 6. SISWA ══════════════════
Add-Section "Siswa"
$siswaAllRaw = RawApi GET "siswa/all`?page=1&per_page=50"
Add-Sample GET "siswa/all`?page=1&per_page=3" (RawApi GET "siswa/all`?page=1&per_page=3")
# id siswa yang BENAR-BENAR ada, untuk contoh per-role di bawah. Sebelumnya
# id-nya di-hardcode (2) dan siswa itu keburu dihapus, sehingga sampel terpenting
# untuk aturan privasi malah merekam 404 "Data sudah dihapus."
$siswaIds = @((($siswaAllRaw.raw | ConvertFrom-Json).data.data) | ForEach-Object { $_.idSiswa })
Add-Sample GET "siswa`?idSiswa=1" (RawApi GET "siswa`?idSiswa=1") "Detail lengkap termasuk data orang tua/wali -- untuk PENGELOLA dan Guru. Role Siswa menerima versi publik, karyawan biasa mendapat 403 (lihat bagian Perilaku per Role)."

# ══════════════════ 7. AKADEMIK - SEMESTER & JAM ══════════════════
Add-Section "Akademik - Semester dan Jam Pelajaran"
$semRaw = RawApi GET "akademik/semester/aktif"
Add-Sample GET "akademik/semester/aktif" $semRaw "Ambil sekali saat app start; pakai sebagai default tahun_ajaran/semester."
$sem = ($semRaw.raw | ConvertFrom-Json).data
$tahun = $sem.tahunAjaran; $semester = $sem.semester
$tq = "tahun_ajaran=$(Enc $tahun)&semester=$semester"
Add-Sample GET "akademik/semester/riwayat" (RawApi GET "akademik/semester/riwayat")
$jamRaw = RawApi GET "akademik/jam"
Add-Sample GET "akademik/jam" $jamRaw
$jams = @(($jamRaw.raw | ConvertFrom-Json).data | Sort-Object ke)

# ══════════════════ 8. AKADEMIK - PEMBAGIAN KELAS ══════════════════
Add-Section "Akademik - Pembagian Kelas"
$btRaw = RawApi GET "akademik/siswa/belum-terdaftar`?$tq"
Add-Sample GET "akademik/siswa/belum-terdaftar`?$tq" $btRaw "PERHATIAN: response ini snake_case (pengecualian satu-satunya di modul akademik)."
$bt = ($btRaw.raw | ConvertFrom-Json).data
$enrollSiswaId = $null
if ($bt.total_belum -gt 0) { $enrollSiswaId = @($bt.siswa)[0].idSiswa }

Add-Sample GET "akademik/siswa/1/kelas`?$tq" (RawApi GET "akademik/siswa/1/kelas`?$tq") "Kelas aktif siswa id=1."
Add-Sample GET "akademik/kelas/1/siswa`?$tq" (RawApi GET "akademik/kelas/1/siswa`?$tq")
Add-Sample GET "akademik/siswa/1/kelas/riwayat" (RawApi GET "akademik/siswa/1/kelas/riwayat") "Riwayat lengkap: ``deletedAt`` terisi jika pernah dipindah/dibatalkan. TANPA ``page``/``per_page`` bentuknya array datar seperti ini (perilaku lama, tetap didukung)."
Add-Sample GET "akademik/siswa/1/kelas/riwayat`?per_page=2&page=1" (RawApi GET "akademik/siswa/1/kelas/riwayat`?per_page=2&page=1") "Endpoint riwayat yang SAMA dengan ``per_page``: ``data`` berubah menjadi envelope paginasi identik dengan ``GET /guru/all`` (``data``, ``current_page``, ``last_page``, ``per_page``, ``total``, ``from``, ``to``, ``links``). Bentuk tiap ITEM tidak berubah. Berlaku juga untuk ``kelas/{id}/siswa/riwayat``, ``guru/{id}/mapel/riwayat``, dan ``mapel/{id}/guru/riwayat``. Default ``per_page``=25, maksimum 100 (di luar itu 422). ``next_page_url`` sengaja tidak dikirim -- URL-nya menunjuk host service internal, bukan Gateway."

$tmpSiswaKelasId = $null
if ($tmpKelasId -and $enrollSiswaId) {
    $r = RawApi POST "akademik/kelas/assign" @{ siswa_id = $enrollSiswaId; kelas_id = $tmpKelasId; tahun_ajaran = $tahun; semester = [int]$semester }
    Add-Sample POST "akademik/kelas/assign" $r "Request akademik memakai snake_case; response camelCase."
    $tmpSiswaKelasId = ($r.raw | ConvertFrom-Json).data.idSiswaKelas
}

# ══════════════════ 9. AKADEMIK - PENGAMPU ══════════════════
Add-Section "Akademik - Pengampu Mapel"
$gmRaw = RawApi GET "akademik/guru/1/mapel`?$tq"
Add-Sample GET "akademik/guru/1/mapel`?$tq" $gmRaw "Mapel aktif yang diampu guru id=1. Hanya berisi ID relasi -- nama mapel/kelas di-resolve dari master data."
$gm = @(($gmRaw.raw | ConvertFrom-Json).data)
$guruKelasId = $null; $guruMapelId = $null; $guruPengampuId = $null
if ($gm.Count -gt 0) { $guruKelasId = $gm[0].kelasId; $guruMapelId = $gm[0].mapelId; $guruPengampuId = $gm[0].idPengampuMapel }
if ($guruKelasId) {
    Add-Sample GET "akademik/kelas/$guruKelasId/pengampu`?$tq" (RawApi GET "akademik/kelas/$guruKelasId/pengampu`?$tq")
}
if ($guruMapelId) {
    Add-Sample GET "akademik/mapel/$guruMapelId/guru`?$tq" (RawApi GET "akademik/mapel/$guruMapelId/guru`?$tq")
}

$tmpPengampuId = $null
if ($tmpKelasId) {
    $r = RawApi POST "akademik/pengampu" @{ guru_id = 1; mapel_id = 1; kelas_id = $tmpKelasId; tahun_ajaran = $tahun; semester = [int]$semester }
    Add-Sample POST "akademik/pengampu" $r
    $tmpPengampuId = ($r.raw | ConvertFrom-Json).data.idPengampuMapel
}

# ══════════════════ 10. AKADEMIK - JADWAL ══════════════════
Add-Section "Akademik - Jadwal Pelajaran"
$tmpJadwalId = $null
if ($tmpPengampuId -and $jams.Count -ge 2) {
    $created = $false
    foreach ($hari in @("Senin", "Selasa", "Rabu", "Kamis", "Jumat")) {
        $r = RawApi POST "akademik/jadwal" @{
            pengampu_mapel_id = $tmpPengampuId; hari = $hari
            jam_mulai_id = $jams[0].idJam; jam_selesai_id = $jams[1].idJam
            ruangan = "Lab Contoh"; catatan = "Dibuat oleh capture script"
        }
        if ($r.code -eq 201) {
            Add-Sample POST "akademik/jadwal" $r "``pukul`` adalah string siap tampil. ``keMulai``/``keSelesai`` = urutan jam."
            $tmpJadwalId = ($r.raw | ConvertFrom-Json).data.idJadwal
            $created = $true; break
        } elseif ($r.code -eq 409 -and -not $script:conflictCaptured) {
            Add-Sample POST "akademik/jadwal" $r "Contoh KONFLIK 409: guru/kelas bentrok di hari+jam yang sama."
            $script:conflictCaptured = $true
        }
    }
}
if ($guruKelasId) {
    Add-Sample GET "akademik/jadwal/kelas/$guruKelasId`?$tq" (RawApi GET "akademik/jadwal/kelas/$guruKelasId`?$tq")
}
Add-Sample GET "akademik/jadwal/guru/1`?$tq" (RawApi GET "akademik/jadwal/guru/1`?$tq")
Add-Sample GET "akademik/jadwal/siswa/1`?$tq" (RawApi GET "akademik/jadwal/siswa/1`?$tq") "Jadwal siswa berdasarkan kelas yang diikutinya."

# ══════════════════ 11. AKADEMIK - PENGATURAN & NILAI ══════════════════
Add-Section "Akademik - Pengaturan Nilai dan Nilai"
Add-Sample GET "akademik/pengaturan-nilai" (RawApi GET "akademik/pengaturan-nilai") "SuperAdmin/Admin saja (termasuk GET)."

$tmpNilaiId = $null
if ($tmpSiswaKelasId -and $tmpPengampuId) {
    $r = RawApi POST "akademik/nilai" @{ siswa_kelas_id = $tmpSiswaKelasId; pengampu_mapel_id = $tmpPengampuId; nilai_harian_1 = 90; nilai_harian_2 = 80; nilai_harian_3 = 70 }
    Add-Sample POST "akademik/nilai" $r "**Ulangan harian: maksimal 5 slot** (``nilai_harian_1``..``nilai_harian_5``). Slot kosong = null dan TIDAK ikut dihitung -- 3 terisi berarti dibagi 3, bukan 5. Perhatikan respons: ``nilaiHarian`` = rata-rata (read-only), ``ulanganHarian`` = array 5 slot, ``jumlahUlangan`` = yang terisi. ``nilaiAkhir`` null sampai rata-rata harian + UTS + UAS terisi."
    $tmpNilaiId = ($r.raw | ConvertFrom-Json).data.idNilai
    if ($tmpNilaiId) {
        $r = RawApi PATCH "akademik/nilai/$tmpNilaiId" @{ nilai_uts = 78; nilai_uas = 90 }
        Add-Sample PATCH "akademik/nilai/$tmpNilaiId" $r "Setelah semua komponen terisi, ``nilaiAkhir`` dihitung otomatis dari bobot semester."
        $r = RawApi PATCH "akademik/nilai/$tmpNilaiId" @{ nilai_harian_2 = $null }
        Add-Sample PATCH "akademik/nilai/$tmpNilaiId" $r "MENGOSONGKAN satu ulangan: kirim ``null`` secara EKSPLISIT (menghilangkan field = tidak berubah). Penyebut rata-rata otomatis menyesuaikan -- lihat ``jumlahUlangan`` turun."
    }
    Add-Sample GET "akademik/nilai/pengampu/$tmpPengampuId`?$tq" (RawApi GET "akademik/nilai/pengampu/$tmpPengampuId`?$tq")
    Add-Sample GET "akademik/nilai/siswa/$enrollSiswaId`?$tq" (RawApi GET "akademik/nilai/siswa/$enrollSiswaId`?$tq")
} else {
    Add-Note "> **Contoh POST/PATCH nilai tidak ikut terekam pada capture ini** karena data sementara (siswa di kelas + pengampu) gagal disiapkan -- biasanya semua siswa sudah terdaftar di kelas. Bentuk requestnya: ``nilai_harian_1``..``nilai_harian_5``, ``nilai_uts``, ``nilai_uas``. Responsnya memuat ``nilaiHarian`` (rata-rata slot terisi), ``ulanganHarian`` (array 5, null = belum ada), dan ``jumlahUlangan``. Jalankan ulang setelah ada siswa yang belum masuk kelas bila contoh nyatanya dibutuhkan."
}

# ══════════════════ 12. AKADEMIK - RAPORT & RANKING ══════════════════
Add-Section "Akademik - Raport dan Ranking"
if ($enrollSiswaId) {
    Add-Sample GET "akademik/raport/siswa/$enrollSiswaId`?$tq" (RawApi GET "akademik/raport/siswa/$enrollSiswaId`?$tq") "``tahun_ajaran`` dan ``semester`` WAJIB di semua endpoint raport/ranking (422 jika kosong)."
}
if ($tmpKelasId) {
    Add-Sample GET "akademik/raport/kelas/$tmpKelasId`?$tq" (RawApi GET "akademik/raport/kelas/$tmpKelasId`?$tq")
    Add-Sample GET "akademik/nilai/ranking/kelas/$tmpKelasId`?$tq" (RawApi GET "akademik/nilai/ranking/kelas/$tmpKelasId`?$tq")
}

# ══════════════════ 12.4 PERIODE KHUSUS & PENGATURAN ABSENSI ══════════════════
Add-Section "Periode Khusus dan Pengaturan Absensi"

# Periode khusus (rentang tanggal 2030 = aman, tidak mempengaruhi perilaku hari ini)
$perRaw = RawApi POST "akademik/periode" @{ nama = 'Ramadan (contoh)'; tahun_ajaran = $tahun; semester = [int]$semester; jenis = 'ramadan'; berlaku_dari = '2030-02-01'; berlaku_sampai = '2030-02-28' }
Add-Sample POST "akademik/periode" $perRaw "Periode khusus mengubah aturan sementara lalu otomatis kembali normal. jenis: ramadan|ujian|libur|khusus. kbm_normal default false untuk ujian & libur."
$perId = ($perRaw.raw | ConvertFrom-Json).data.idPeriode

$librRaw = RawApi POST "akademik/periode" @{ nama = 'Libur 1 hari (contoh)'; tahun_ajaran = $tahun; semester = [int]$semester; jenis = 'libur'; berlaku_dari = '2030-02-10'; berlaku_sampai = '2030-02-10' }
Add-Sample POST "akademik/periode" $librRaw "Libur 1 hari: berlaku_dari = berlaku_sampai. Perhatikan ``kbmNormal:false`` otomatis. Auto-alpa melewati tanggal ini."
$librId = ($librRaw.raw | ConvertFrom-Json).data.idPeriode

Add-Sample GET "akademik/periode/aktif`?tanggal=2030-02-10" (RawApi GET "akademik/periode/aktif`?tanggal=2030-02-10") "RESOLUSI: kalau beberapa periode bertumpuk, rentang TERPENDEK menang -> libur 1 hari mengalahkan Ramadan."
Add-Sample GET "akademik/periode/aktif`?tanggal=2030-02-05" (RawApi GET "akademik/periode/aktif`?tanggal=2030-02-05") "Tanggal Ramadan biasa."
Add-Sample GET "akademik/periode" (RawApi GET "akademik/periode`?tahun_ajaran=$(Enc $tahun)&semester=$semester") "Daftar periode."

# Jam per periode + jam efektif per tanggal
$jamPerRaw = RawApi POST "akademik/jam" @{ periode_id = $perId; ke = 1; jam_mulai = '07:30'; jam_selesai = '08:00' }
Add-Sample POST "akademik/jam" $jamPerRaw "Set jam milik periode (menggantikan set normal). ``hari`` opsional: terisi = khusus hari itu (mis. Jumat) dan menang atas baris semua-hari."
$jamPerId = ($jamPerRaw.raw | ConvertFrom-Json).data.idJam
Add-Sample GET "akademik/jam`?tanggal=2030-02-05&hari=Senin" (RawApi GET "akademik/jam`?tanggal=2030-02-05&hari=Senin") "JAM EFEKTIF pada tanggal: sudah ikut periode + hari. Jadwal TIDAK diduplikasi saat Ramadan -- jam dinding di-resolve per (periode, hari, slot ke)."
Add-Sample POST "akademik/jam" (RawApi POST "akademik/jam" @{ ke = 1; jam_mulai = '07:00'; jam_selesai = '07:45' }) "Duplikat slot (periode_id+hari+ke sama) -> 409 Conflict (BUKAN 422)."

# Jadwal mengikuti tanggal: `pukul` di-resolve untuk periode (ke-2/ke-3 dibuat agar jadwal ber-slot ganda ikut ter-resolve)
RawApi POST "akademik/jam" @{ periode_id = $perId; ke = 2; jam_mulai = '08:00'; jam_selesai = '08:30' } | Out-Null
RawApi POST "akademik/jam" @{ periode_id = $perId; ke = 3; jam_mulai = '08:30'; jam_selesai = '09:00' } | Out-Null
Add-Sample GET "akademik/jadwal/guru/1`?tanggal=2030-02-05" (RawApi GET "akademik/jadwal/guru/1`?tanggal=2030-02-05") "``pukul`` mengikuti tanggal: pada periode Ramadan menampilkan jam Ramadan. Bandingkan dgn GET jadwal/guru/1 tanpa tanggal (bagian Jadwal di atas) yang memakai jam normal. Slot yg tidak diset di periode -> ``pukul`` tidak muncul (jadwal itu ditiadakan pada tanggal tsb)."

# Pengaturan absensi: override periode + resolusi efektif
$pengRaw = RawApi POST "akademik/pengaturan-absensi" @{ tahun_ajaran = $tahun; semester = [int]$semester; periode_id = $perId; batas_terlambat_siswa = '08:00'; batas_terlambat_pegawai = '08:00' }
Add-Sample POST "akademik/pengaturan-absensi" $pengRaw "periode_id terisi = override selama periode itu; tanpa periode_id = default semester. Duplikat kombinasi -> 409."
$pengId = ($pengRaw.raw | ConvertFrom-Json).data.idPengaturanAbsensi
Add-Sample GET "akademik/pengaturan-absensi/efektif`?tanggal=2030-02-05" (RawApi GET "akademik/pengaturan-absensi/efektif`?tanggal=2030-02-05") "Aturan yang BENAR-BENAR berlaku pada tanggal. ``sumber``: periode | default_semester | default_sistem (fallback bawaan 07:20)."
Add-Sample GET "akademik/pengaturan-absensi/efektif" (RawApi GET "akademik/pengaturan-absensi/efektif") "Tanpa tanggal = hari ini. Belum ada baris apa pun -> sumber=default_sistem."

# Bersihkan contoh (capture DELETE sekalian)
if ($pengId)   { Add-Sample DELETE "akademik/pengaturan-absensi/$pengId" (RawApi DELETE "akademik/pengaturan-absensi/$pengId") }
if ($jamPerId) { RawApi DELETE "akademik/jam/$jamPerId" | Out-Null }
if ($librId)   { RawApi DELETE "akademik/periode/$librId" | Out-Null }
if ($perId)    { Add-Sample DELETE "akademik/periode/$perId" (RawApi DELETE "akademik/periode/$perId") "Soft delete." }

# ══════════════════ 12.5 ABSENSI ══════════════════
Add-Section "Absensi (Kartu, Keluar, Rekap, PIN, Terminal)"

# Kartu absensi (SuperAdmin/Admin) — UID opaque berprefix SIS-/GUR-/KAR-
Add-Sample POST "siswa/kartu/terbitkan" (RawApi POST "siswa/kartu/terbitkan" @{ idSiswa = 1 }) "Terbitkan/ganti kartu siswa. ``kartuUid`` opaque (bukan NISN). Terbit-ulang menimpa UID lama."
Add-Sample POST "siswa/kartu/blokir" (RawApi POST "siswa/kartu/blokir" @{ idSiswa = 1; status = "hilang" }) "Blokir kartu (``hilang``/``blokir``) -> scan ditolak."
RawApi POST "siswa/kartu/terbitkan" @{ idSiswa = 1 } | Out-Null  # pulihkan kartu siswa 1 ke aktif
Add-Sample POST "guru/kartu/terbitkan" (RawApi POST "guru/kartu/terbitkan" @{ idGuru = 1 }) "Kartu guru (prefix ``GUR-``); karyawan ``KAR-`` serupa via /karyawan/kartu/terbitkan."
[void]$MD.AppendLine('### `GET /kartu/qr?data=<uid>`')
[void]$MD.AppendLine("")
[void]$MD.AppendLine("Mengembalikan **image/svg+xml** (bukan JSON envelope) - QR siap cetak ke kartu fisik. Role SuperAdmin/Admin. Tidak di-capture di sini karena respons berupa SVG.")
[void]$MD.AppendLine("")

# Absensi keluar (pulang awal) — disetujui wali kelas/admin
Add-Sample POST "akademik/absensi/keluar" (RawApi POST "akademik/absensi/keluar" @{ siswa_id = 1; jenis = "pulang_awal"; keterangan = "dijemput orang tua" }) "``jenis``: pulang_awal/izin_kegiatan/lomba/pulang_sakit. ``disetujuiOleh`` = id user penyetuju (diinject Gateway)."
Add-Sample GET "akademik/absensi/keluar" (RawApi GET "akademik/absensi/keluar") "Daftar izin keluar (default hari ini WIB; filter ``tanggal``/``siswa_id``)."

# Rekap absensi — rentang default awal bulan s/d hari ini (WIB)
Add-Sample GET "akademik/absensi/rekap/harian/kelas/1" (RawApi GET "akademik/absensi/rekap/harian/kelas/1") "Per siswa: hadir/terlambat/izin/sakit/alpa + total. ``namaLengkap`` disertakan. Override rentang: ``tanggal_dari``/``tanggal_sampai``."
Add-Sample GET "akademik/absensi/rekap/harian/siswa/1" (RawApi GET "akademik/absensi/rekap/harian/siswa/1") "Ringkasan + detail per hari."
Add-Sample GET "akademik/absensi/rekap/pelajaran/siswa/1" (RawApi GET "akademik/absensi/rekap/pelajaran/siswa/1") "Ringkasan absensi per pelajaran 1 siswa."
Add-Sample GET "akademik/absensi/rekap/pegawai/guru/1" (RawApi GET "akademik/absensi/rekap/pegawai/guru/1") "Rekap pegawai; path ``/rekap/pegawai/{guru|karyawan}/{id}``."

# Jendela PIN (Admin buka) + autentikasi terminal
Add-Sample POST "absensi/pin/buka" (RawApi POST "absensi/pin/buka" @{ subjek_tipe = "guru"; subjek_id = 1; durasi_menit = 10 }) "Admin membuka jendela PIN untuk pegawai (time-boxed, sekali pakai)."

# Wali kelas (Admin) — dipakai enforcement persetujuan izin keluar
$waliRaw = RawApi POST "akademik/wali" @{ guru_id = 1; kelas_id = 1; tahun_ajaran = $tahun; semester = [int]$semester }
Add-Sample POST "akademik/wali" $waliRaw "Tetapkan wali kelas (satu wali per kelas/semester; 409 jika sudah ada)."
$waliId = ($waliRaw.raw | ConvertFrom-Json).data.idWaliKelas
Add-Sample GET "akademik/kelas/1/wali" (RawApi GET "akademik/kelas/1/wali") "Wali aktif satu kelas."
if ($waliId) { Add-Sample DELETE "akademik/wali/$waliId" (RawApi DELETE "akademik/wali/$waliId") "Batalkan penugasan wali (soft delete)." }
Add-Sample POST "absensi/scan" (RawApi POST "absensi/scan" @{ kartu_uid = "SIS-XXXX" }) "Scan & absen PIN butuh autentikasi **terminal** (header X-Terminal-Id/Token), bukan Bearer. Tanpa header terminal -> 401."

# ══════════════════ 13. CLEANUP DATA SEMENTARA (capture DELETE) ══════════════════
Add-Section "Endpoint /saya, Laporan Angkatan & Ulangan Harian"

Add-Sample GET "akademik/absensi/rekap/pegawai/saya" (RawApi GET "akademik/absensi/rekap/pegawai/saya") "Rekap absensi DIRI SENDIRI untuk pegawai (guru/karyawan). Subjek diresolve dari email token; bentuknya identik dengan ``rekap/pegawai/{tipe}/{id}`` sehingga DTO-nya sama. 404 bila akun bukan pegawai."
Add-Sample GET "akademik/nilai/ranking/angkatan`?tingkat=1&$tq" (RawApi GET "akademik/nilai/ranking/angkatan`?tingkat=1&$tq") "Peringkat SE-ANGKATAN. ``peringkat`` bisa kembar dan MELOMPAT (1,2,2,4,5) -- jangan pakai indeks baris. Seri diurutkan alfabet oleh server; jangan di-sort ulang. Mapel belum dinilai dihitung 0 (lihat ``belumDinilai``/``jumlahMapel``). Siswa non-aktif sudah dibuang server."
Add-Sample GET "akademik/nilai/ranking/angkatan`?tingkat=1&$tq&detail=1" (RawApi GET "akademik/nilai/ranking/angkatan`?tingkat=1&$tq&detail=1") "``detail=1`` menambah array ``nilai`` per siswa (per mapel + predikat). Field ``nilai`` TIDAK ada saat detail=0 -> jadikan opsional di DTO."
Add-Sample GET "class/all`?tingkat=1&jurusan=MIPA" (RawApi GET "class/all`?tingkat=1&jurusan=MIPA") "Filter kelas per angkatan untuk dropdown laporan (``tingkat`` 1/2/3, ``jurusan`` opsional)."

Add-Note "**Ekspor laporan angkatan** ``GET akademik/nilai/ranking/angkatan/export?...&format=csv|pdf`` mengembalikan BERKAS, bukan JSON -- karena itu tidak dicontohkan di sini. Respons: ``Content-Type: text/csv; charset=UTF-8`` atau ``application/pdf``, plus ``Content-Disposition: attachment; filename=\"peringkat-angkatan-XII-MIPA-2024-2025-sem2.pdf\"``. Format selain csv/pdf -> 422. Di Android: simpan lewat MediaStore/SAF, jangan di-parse sebagai JSON."

Add-Section "Contoh Response DELETE (dari cleanup data sementara)"
if ($tmpNilaiId)      { Add-Sample DELETE "akademik/nilai/$tmpNilaiId" (RawApi DELETE "akademik/nilai/$tmpNilaiId") }
if ($tmpJadwalId)     { Add-Sample DELETE "akademik/jadwal/$tmpJadwalId" (RawApi DELETE "akademik/jadwal/$tmpJadwalId") "Soft delete; jadwal masih terlihat di endpoint /riwayat." }
if ($tmpPengampuId)   { Add-Sample DELETE "akademik/pengampu/$tmpPengampuId" (RawApi DELETE "akademik/pengampu/$tmpPengampuId") }
if ($tmpSiswaKelasId) { Add-Sample DELETE "akademik/kelas/assign/$tmpSiswaKelasId" (RawApi DELETE "akademik/kelas/assign/$tmpSiswaKelasId") }
if ($tmpKelasId)      { Add-Sample DELETE "class/$tmpKelasId" (RawApi DELETE "class/$tmpKelasId") }

# ══════════════════ 14. SAMPLE PER ROLE ══════════════════
# Rate limit login 5/menit: pastikan jendela menit pertama sudah lewat
$elapsed = [int]$sw.Elapsed.TotalSeconds
if ($elapsed -lt 70) { Write-Host "`nTunggu $((70 - $elapsed))s (rate limit login)..."; Start-Sleep (70 - $elapsed) }

Add-Section "Perilaku per Role (Guru / Siswa / Karyawan)"

$lg = RawApi POST "login" @{ email = "andi.susanto2@sekolah.com"; password = "GuruTest123"; device_name = "android" }
$guruToken = ($lg.raw | ConvertFrom-Json).data.token
$ls = RawApi POST "login" @{ email = "andi.siswa1@sekolah.com"; password = "SiswaTest123"; device_name = "android" }
$siswaToken = ($ls.raw | ConvertFrom-Json).data.token
$lk = RawApi POST "login" @{ email = "akuntest.karyawan@example.com"; password = "KaryawanTest123"; device_name = "android" }
$kwToken = ($lk.raw | ConvertFrom-Json).data.token

if ($siswaToken) {
    Add-Sample GET "guru`?idGuru=1" (RawApi GET "guru`?idGuru=1" -Token $siswaToken) "Detail guru DILIHAT NON-PENGELOLA (Guru, Siswa, karyawan biasa): field pribadi (nik, alamat, telephone, tanggalLahir, dll.) DISARING oleh Gateway -- field-nya TIDAK DIKIRIM, bukan dikirim kosong. Bandingkan dengan versi lengkap di bagian Guru. Buat semua field DTO detail nullable, kalau tidak parser akan melempar MissingFieldException dan layar jadi blank."
    # Harus siswa LAIN: record diri sendiri sengaja TIDAK disaring, jadi memakai
    # id sendiri akan merekam bentuk yang penuh dan menyesatkan pembaca.
    $siswaSayaRaw = RawApi GET "siswa/saya" -Token $siswaToken
    $idSiswaSaya  = (($siswaSayaRaw.raw | ConvertFrom-Json).data.idSiswa)
    $idSiswaLain = @($siswaIds | Where-Object { $_ -ne $idSiswaSaya }) | Select-Object -First 1
    if ($idSiswaLain) {
        Add-Sample GET "siswa`?idSiswa=$idSiswaLain" (RawApi GET "siswa`?idSiswa=$idSiswaLain" -Token $siswaToken) "Detail siswa LAIN dilihat ROLE SISWA: 200 tapi hanya INFO PUBLIK (idSiswa, namaLengkap, jenisKelamin, status, foto). NISN, tempat/tanggal lahir, alamat, telepon, dan seluruh data orang tua TIDAK dikirim. Bandingkan dengan ``GET /siswa/saya`` di bawah yang tetap lengkap."
    } else {
        Add-Note "> **Contoh detail siswa LAIN untuk viewer Siswa tidak terekam** -- hanya ada satu siswa di database, jadi tidak ada pembanding. Bentuk yang diharapkan: ``idSiswa``, ``namaLengkap``, ``jenisKelamin``, ``status``, ``foto`` saja."
        Write-Host "  [!!] Tidak ada siswa pembanding - sampel privasi detail siswa dilewati" -ForegroundColor Yellow
    }
    Add-Sample GET "siswa/all`?page=1&per_page=3" (RawApi GET "siswa/all`?page=1&per_page=3" -Token $siswaToken) "Daftar siswa DILIHAT ROLE SISWA: tiap baris hanya idSiswa, namaLengkap, jenisKelamin, status. Meta paginasi TIDAK berubah, jadi parser daftar tetap satu untuk semua role."
    Add-Sample GET "siswa/saya" $siswaSayaRaw "Profil DIRI SENDIRI untuk role Siswa -- LENGKAP, termasuk ``foto`` (data-URI base64). Tidak ikut disaring karena ini datanya sendiri; ``GET /siswa?idSiswa=`` untuk siswa lain hanya versi publik. idSiswa diresolve dari email token."
    Add-Sample GET "akademik/nilai/saya`?$tq" (RawApi GET "akademik/nilai/saya`?$tq" -Token $siswaToken) "Khusus role Siswa; siswa_id di-resolve server dari email token."
    Add-Sample GET "akademik/raport/saya`?$tq" (RawApi GET "akademik/raport/saya`?$tq" -Token $siswaToken)
    Add-Sample GET "akademik/nilai/ranking/saya`?$tq" (RawApi GET "akademik/nilai/ranking/saya`?$tq" -Token $siswaToken) "Hanya posisi diri sendiri -- tanpa daftar siswa lain."
    Add-Sample POST "akademik/nilai" (RawApi POST "akademik/nilai" @{ siswa_kelas_id = 1; pengampu_mapel_id = 1; nilai_harian = 80 } -Token $siswaToken) "Role Siswa tidak boleh menulis nilai (403)."
}
# Pembanding karyawan biasa vs Administrator Sekolah dipakai di bagian TU di bawah.
# Direkam DI SINI, selagi tokennya masih hidup: blok berikutnya melakukan refresh
# (yang mencabut token lama) lalu logout. Versi sebelumnya menyimpan salinan token
# lalu memakainya setelah refresh -- hasilnya 401, bukan 403, sehingga "pembanding"
# yang didokumentasikan sebenarnya tidak pernah menguji apa pun.
$kwPengampuRaw = $null
$kwPrivasiRaw  = @{}
if ($kwToken) {
    $kwPengampuRaw = RawApi POST "akademik/pengampu" @{ guru_id = 1; mapel_id = 1; kelas_id = 1; tahun_ajaran = $tahun; semester = "$semester" } -Token $kwToken
    $kwPrivasiRaw['guru']    = RawApi GET "guru`?idGuru=1" -Token $kwToken
    $kwPrivasiRaw['nilai']   = RawApi GET "akademik/nilai/kelas/1`?$tq" -Token $kwToken
    $kwPrivasiRaw['siswa']   = RawApi GET "siswa/all`?page=1&per_page=3" -Token $kwToken
    $kwPrivasiRaw['sendiri'] = RawApi GET "akademik/absensi/rekap/pegawai/saya" -Token $kwToken
}
if ($kwToken) {
    Add-Sample POST "class" (RawApi POST "class" @{ noKelas = 1; tingkat = 1; jurusan = "MIPA"; limitSiswa = 30 } -Token $kwToken) "Karyawan read-only: POST ditolak 403."
    Add-Sample GET "guru`?idGuru=1" $kwPrivasiRaw['guru'] "Detail guru DILIHAT KARYAWAN BIASA: sama tersaringnya dengan viewer Siswa. Sebelumnya role Karyawan menerima PII penuh -- itu sudah ditutup."
    Add-Sample GET "akademik/nilai/kelas/1`?$tq" $kwPrivasiRaw['nilai'] "Karyawan biasa TIDAK boleh membaca data akademik siswa: 403. Berlaku juga untuk raport, ranking kelas, dan rekap absensi siswa. Administrator Sekolah tetap 200."
    Add-Sample GET "siswa/all`?page=1&per_page=3" $kwPrivasiRaw['siswa'] "Direktori siswa ditutup seluruhnya untuk karyawan biasa: 403, daftar maupun detail."
    Add-Sample GET "akademik/absensi/rekap/pegawai/saya" $kwPrivasiRaw['sendiri'] "Yang TETAP boleh karyawan biasa: rekap absensi DIRINYA SENDIRI. Subjek diresolve dari email token."
    # 404 di sini BUKAN soal izin -- gate role-nya lolos, yang gagal resolusi
    # subjeknya. Tanpa catatan ini pembaca mudah salah menyimpulkan bahwa
    # karyawan biasa memang tidak boleh membuka rekap dirinya sendiri.
    if ((($kwPrivasiRaw['sendiri'].raw | ConvertFrom-Json).resCode) -eq 404) {
        Add-Note "> **Catatan:** contoh di atas terekam **404**, bukan 200. Akun ``akuntest.karyawan`` punya user di Gateway tapi belum punya record di tabel ``karyawans``, sehingga subjeknya tak bisa diresolve. Gate role-nya sendiri LOLOS (bukan 403) -- karyawan biasa memang berhak atas endpoint ini. Jalankan ``seed-test-accounts.ps1`` agar contohnya terekam 200."
        Write-Host "  [!!] rekap/pegawai/saya karyawan = 404 (akun belum punya record karyawan) - catatan ditambahkan" -ForegroundColor Yellow
    }
    $rf = RawApi POST "refresh" -Token $kwToken
    Add-Sample POST "refresh" $rf "Tukar token valid dengan token baru 8 jam; token lama dicabut; device_name diwarisi."
    $kwToken = ($rf.raw | ConvertFrom-Json).data.token
    Add-Sample POST "password" (RawApi POST "password" @{ current_password = "PasswordSalah999"; new_password = "BaruBanget123"; confirm_password = "BaruBanget123" } -Token $kwToken) "Contoh 422: current_password salah."
    Add-Sample POST "logout" (RawApi POST "logout" -Token $kwToken)
}
# ── Administrator Sekolah (karyawan staf TU bertanda isAdminSekolah) ──
$lt = RawApi POST "login" @{ email = "akuntest.adminsekolah@example.com"; password = "AdminSekolahTest123"; device_name = "android" }
Add-Sample POST "login" $lt "Login **Administrator Sekolah**. Perhatikan ``role`` tetap ``Karyawan`` sementara ``isAdminSekolah`` bernilai true -- INILAH penanda yang dipakai app untuk membuka menu manajemen. Jangan gating dengan role."
$tuToken = ($lt.raw | ConvertFrom-Json).data.token
if ($tuToken) {
    Add-Sample GET "user" (RawApi GET "user" -Token $tuToken) "``isAdminSekolah`` juga tersedia di GET /user, jadi app tidak perlu menyimpan hasil login untuk menentukan menu."
    Add-Sample POST "akademik/pengampu" (RawApi POST "akademik/pengampu" @{ guru_id = 1; mapel_id = 99999; kelas_id = 1; tahun_ajaran = $tahun; semester = "$semester" } -Token $tuToken) "Administrator Sekolah BOLEH menulis data akademik: hasilnya 404 (mapel tidak ada), BUKAN 403 -- artinya pembatasan role sudah terlewat."
    Add-Sample POST "register" (RawApi POST "register" @{ name = "Coba Admin"; email = "coba.admin.tu@example.com"; password = "Rahasia123"; confirm_password = "Rahasia123"; role = "Admin" } -Token $tuToken) "Batas Administrator Sekolah: TIDAK boleh membuat akun Admin/SuperAdmin (422). Hanya Guru/Siswa/Karyawan."
    RawApi POST "logout" -Token $tuToken | Out-Null
}
if ($kwPengampuRaw) {
    Add-Sample POST "akademik/pengampu" $kwPengampuRaw "Karyawan BIASA (bukan Administrator Sekolah) tetap 403 pada endpoint yang sama -- pembanding langsung untuk contoh di atas."
    # Kalau yang terekam 401, tokennya sudah dicabut sebelum request dijalankan dan
    # pembandingnya tidak menguji apa pun. Jangan biarkan lolos diam-diam.
    if (($kwPengampuRaw.raw | ConvertFrom-Json).resCode -eq 401) {
        Add-Note "> **PERINGATAN capture:** contoh di atas terekam **401**, bukan 403 -- token karyawan biasa sudah dicabut sebelum request dijalankan. Pembanding Administrator Sekolah vs karyawan biasa TIDAK valid pada capture ini; perbaiki urutan di ``capture-api-samples.ps1`` lalu jalankan ulang."
        Write-Host "  [!!] Pembanding karyawan biasa terekam 401 (token sudah dicabut) - capture tidak valid" -ForegroundColor Red
    }
}

if ($guruToken) { RawApi POST "logout" -Token $guruToken | Out-Null }
if ($siswaToken) { RawApi POST "logout" -Token $siswaToken | Out-Null }

# ══════════════════ 15. CONTOH ERROR UMUM ══════════════════
Add-Section "Contoh Error Umum"
Add-Sample GET "user" (RawApi GET "user" -NoAuth) "401 tanpa token."
Add-Sample GET "user" (RawApi GET "user" -Token "token-tidak-valid") "401 token invalid/kedaluwarsa -> app harus hapus sesi lokal dan kembali ke Login."
Add-Sample POST "login" (RawApi POST "login" @{ email = "superadmin@example.com"; password = "salah-password-123" }) "Login gagal = HTTP 400 (bukan 401) -- jangan trigger auto-logout."
Add-Sample POST "akademik/kelas/assign" (RawApi POST "akademik/kelas/assign" @{ siswa_id = 99999; kelas_id = 1; tahun_ajaran = $tahun; semester = [int]$semester }) "404 validasi cross-service: siswa_id tidak ada di SiswaService."
Add-Sample POST "mapel" (RawApi POST "mapel" @{ keterangan = "tanpa field wajib" }) "422 validasi: resMsg berisi pesan pertama; bentuk data BERVARIASI antar modul -- jangan parsing data untuk 422."
Add-Sample POST "akademik/pengaturan-nilai" (RawApi POST "akademik/pengaturan-nilai" @{ tahun_ajaran = $tahun; semester = [int]$semester; bobot_harian = 40; bobot_uts = 30; bobot_uas = 30 }) "409 konflik bisnis: pengaturan semester ini sudah ada. Tampilkan resMsg apa adanya."
Add-Sample POST "karyawan" (RawApi POST "karyawan" @{ email = "akuntest.karyawan@example.com"; nip = "8000009"; namaLengkap = "Contoh Email Ganda"; jabatan = "Satpam" }) "422 email sudah dipakai akun lain. Gateway menolak SEBELUM menulis record domain -- kalau tidak, recordnya terlanjur tersimpan tanpa akun login dan pemanggil hanya menerima 500. ``users`` memakai soft delete sedangkan emailnya unik di DB, jadi akun yang sudah DIHAPUS pun masih memegang emailnya."
Add-Note @"
> **Respons 500 tidak memuat detail internal.** ``resMsg``-nya selalu berbentuk
> ``"Terjadi kesalahan di server. Sertakan kode A1B2C3D4 saat melaporkannya."`` --
> aman ditampilkan apa adanya ke pengguna. Detail aslinya (termasuk SQL) ada di log
> server dengan kode rujukan yang sama. Tidak dicontohkan di sini karena memicunya
> butuh membuat kondisi galat sungguhan.
>
> Service juga SELALU membalas envelope ``resCode``/``resMsg``, termasuk untuk error
> yang tidak tertangkap. Sebelumnya bentuknya bawaan Laravel (``message``/``exception``/
> ``trace``) yang tidak bisa di-parse DTO envelope -- satu DTO kini cukup untuk semua.
"@
Add-Note @"
> **Upload foto: dimensi 360x480 s/d 6000x6000 px**, di luar itu **422**
> ``The foto field has invalid image dimensions``. Tidak dicontohkan di sini karena
> butuh multipart. **Klien wajib memperkecil sebelum unggah** -- foto HP kelas atas
> melebihi 6000 px walau sudah di-crop 3:4 (iPhone 48 MP = 8064x6048 -> crop jadi
> +-4536x6048). Perkecil sisi terpanjang ke +-1600 px setelah crop.
>
> **``per_page`` maksimum 200** di semua endpoint berpaginasi; di luar 1-200 -> **422**.
"@
[void]$MD.AppendLine(@"
### ``POST /login`` -- 429 rate limit (contoh terdokumentasi, tidak di-capture ulang agar tidak mengunci akun)

Response (HTTP 429):
``````json
{"resCode":429,"resPhrase":"Too Many Requests","resStatus":"fail","resMsg":"Terlalu banyak percobaan login. Coba lagi dalam 1 menit.","data":[]}
``````
"@)

[void]$MD.AppendLine(@'

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
'@)

# ══════════════════ TULIS FILE ══════════════════
RawApi POST "logout" | Out-Null
[System.IO.File]::WriteAllText($OutFile, $MD.ToString(), (New-Object System.Text.UTF8Encoding $false))
Write-Host "`nSelesai. Output: $OutFile" -ForegroundColor Green
