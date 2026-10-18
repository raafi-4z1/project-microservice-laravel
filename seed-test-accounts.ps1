# =============================================================
#  SEED TEST ACCOUNTS — Microservice Laravel (untuk dev/testing app client)
#  Usage:
#    $env:TEST_ADMIN_PASSWORD = "PasswordSuperAdmin"
#    powershell -ExecutionPolicy Bypass -File seed-test-accounts.ps1
#
#  Membuat/mereset 6 akun test (idempotent, aman dijalankan berulang):
#    - Admin         : akuntest.admin@example.com         / AdminTest123
#    - Karyawan      : akuntest.karyawan@example.com      / KaryawanTest123
#    - Adm. Sekolah  : akuntest.adminsekolah@example.com  / AdminSekolahTest123
#    - Petugas Acara : akuntest.petugasacara@example.com  / PetugasAcaraTest123
#    - Guru  : <email guru asli pertama yang punya pengampu> / GuruTest123
#    - Siswa : <email siswa asli pertama yang terdaftar di kelas> / SiswaTest123
#  Akun Guru & Siswa memakai akun yang terhubung ke record asli di
#  GuruService/SiswaService agar endpoint /saya dan alur nilai bisa diuji.
#
#  Opsional: $env:SEED_NILAI = "1" mengisi nilai siswa test di semester aktif.
#
#  !! HANYA UNTUK SERVER PENGEMBANGAN/UJI. Skrip ini MERESET PASSWORD akun guru &
#  !! siswa ASLI pertama yang ditemukannya, dan dengan SEED_NILAI menulis nilai
#  !! karangan ke siswa asli itu (masuk raport & peringkat kelas). Jangan pernah
#  !! dijalankan di server sekolah yang sudah dipakai sungguhan.
#
#  Kode keluar: 0 bila semua langkah berhasil, 1 bila ada [GAGAL].
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
    # Mengembalikan 'ok', atau kalimat yang menyebut langkah mana yang gagal.
    $tmp = "$Password" + "Tmp1"
    $r = Api POST "users/$UserId/password" @{ new_password = $tmp; confirm_password = $tmp }
    if ($r.resCode -ne 200) { return "reset ke password sementara gagal ($($r.resCode) $($r.resMsg))" }
    $lg = Api POST "login" @{ email = $Email; password = $tmp; device_name = "seed-script" }
    if ($lg.resCode -ne 200) { return "login dengan password sementara gagal ($($lg.resCode) $($lg.resMsg))" }
    $ch = Api POST "password" @{ current_password = $tmp; new_password = $Password; confirm_password = $Password } -Token $lg.data.token
    if ($ch.resCode -ne 200) {
        # Akun kini memakai password sementara — sebutkan, supaya tidak terkunci diam-diam.
        return "ganti ke password final gagal ($($ch.resCode) $($ch.resMsg)); password akun SEKARANG '$tmp'"
    }
    return 'ok'
}

function Ensure-Account([string]$Name, [string]$Email, [string]$Role, [string]$Password) {
    $id = Find-UserId $Email
    if ($id) {
        # Reset oleh admin SELALU menyalakan wajib-ganti (password pilihan admin
        # wajib dirotasi pemiliknya). Jadi langsung lewat alur resmi: reset ke
        # password sementara -> login -> ganti ke password final; `POST /password`
        # yang sukses sekaligus mematikan penandanya.
        #
        # Versi sebelumnya me-reset, login untuk MENGECEK penanda, lalu baru
        # me-reset+login+ganti lagi. Karena penandanya selalu menyala, reset dan
        # login pertama selalu terbuang — tiap akun memakan 3 dari jatah login
        # 5/menit, dan seed tertahan beberapa kali 61 detik.
        $hasil = Clear-MustChangePassword $Email $id $Password
        if ($hasil -eq 'ok') {
            Write-Host "  [RESET   ] $Role : $Email (password final, tanpa wajib-ganti)" -ForegroundColor Green
            return $true
        }
        Write-Host "  [GAGAL   ] $Role : $Email -- $hasil" -ForegroundColor Red
        $script:GAGAL++
        return $false
    }
    $r = Api POST "register" @{ name = $Name; email = $Email; password = $Password; confirm_password = $Password; role = $Role }
    if ($r.resCode -eq 201) { Write-Host "  [REGISTER] $Role : $Email" -ForegroundColor Green; return $true }
    Write-Host "  [GAGAL   ] Register $Email -- $($r.resCode) $($r.resMsg)" -ForegroundColor Red
    $script:GAGAL++
    return $false
}

# Pastikan akun karyawan test punya RECORD karyawan, dengan penanda Adm. Sekolah
# sesuai $IsAdminSekolah. Satu fungsi untuk karyawan biasa DAN Adm. Sekolah:
# keduanya dulu ditulis sebagai dua blok terpisah, dan perbaikan "jangan bertindak
# bila pencarian gagal" hanya masuk ke salah satunya.
function Ensure-KaryawanRecord([string]$Email, [string]$Nama, [string]$Jabatan, [bool]$IsAdminSekolah, [string]$Label, [string]$AwalanNip) {
    $cari = Api GET "karyawan/all`?per_page=100&search=$([uri]::EscapeDataString($Email))"
    if ($cari.resCode -ne 200) {
        # Tanpa jawaban pasti JANGAN menyimpulkan record-nya tidak ada: cabang di
        # bawah menghapus akun yang masih aktif. Dulu satu GET yang kena 429/500
        # cukup untuk membuat skrip menghapus akun pembanding.
        Write-Host "  [GAGAL   ] Cek record $Label : $($cari.resCode) $($cari.resMsg) -- dilewati, akun TIDAK disentuh" -ForegroundColor Red
        $script:GAGAL++
        return
    }
    $ada = @($cari.data.data | Where-Object { $_.email -eq $Email })
    if ($ada.Count -gt 0) {
        # Pastikan penandanya sesuai peran akun ini (menyala untuk Adm. Sekolah,
        # mati untuk karyawan biasa yang gunanya sebagai pembanding).
        Api POST "karyawan/update" @{ idKaryawan = $ada[0].idKaryawan; isAdminSekolah = $IsAdminSekolah } | Out-Null
        Write-Host "  [ADA     ] $Label : $Email (karyawan id=$($ada[0].idKaryawan))"
        return
    }

    # Record belum ada. Kalau akun user-nya SUDAH ADA dan aktif, `POST /karyawan`
    # ditolak 422 oleh guard anti data-yatim ("email dipakai akun aktif") — dan itu
    # memang benar. Dulu ini jalan buntu: `users.email` unik di level DB padahal
    # modelnya soft delete, sehingga menghapus akun membuat emailnya terkunci
    # selamanya. Sejak email bisa dipakai ulang, `POST /karyawan` untuk email milik
    # akun TERHAPUS memulihkan baris akun yang SAMA (id tetap) sekaligus membuat
    # record-nya. Jadi: hapus akunnya dulu, baru POST /karyawan.
    $cariU = Api GET "users`?search=$([uri]::EscapeDataString($Email))&per_page=5"
    if ($cariU.resCode -ne 200) {
        Write-Host "  [GAGAL   ] Cek akun $Email : $($cariU.resCode) $($cariU.resMsg) -- dilewati" -ForegroundColor Red
        $script:GAGAL++
        return
    }
    $idLama = $null
    $uLama = @($cariU.data.data | Where-Object { $_.email -eq $Email })
    if ($uLama.Count -gt 0) {
        $idLama = $uLama[0].id
        $del = Api DELETE "users/$idLama"
        if ($del.resCode -ne 202) {
            # Berhenti di sini: dengan akun masih aktif, POST /karyawan pasti 422
            # dan "memulihkan" akun yang tidak terhapus hanya menambah galat.
            Write-Host "  [GAGAL   ] Lepas akun $Email untuk ditautkan: $($del.resCode) $($del.resMsg) -- dihentikan, akun tetap aktif" -ForegroundColor Red
            $script:GAGAL++
            return
        }
    }
    $stamp = Get-Date -Format "HHmmss"
    $mk = Api POST "karyawan" @{
        email = $Email; nip = "$AwalanNip$stamp"; namaLengkap = $Nama
        jabatan = $Jabatan; isAdminSekolah = $IsAdminSekolah
    }
    if ($mk.resCode -eq 201) {
        $idBaru = Find-UserId $Email
        $catatanId = if ($idLama -and "$idBaru" -eq "$idLama") { " -- akun id=$idLama dipulihkan, bukan dibuat baru" } else { "" }
        Write-Host "  [TAUTKAN ] $Label : $Email (karyawan id=$($mk.data.idKaryawan))$catatanId" -ForegroundColor Green
        return
    }
    Write-Host "  [GAGAL   ] $Label : $($mk.resCode) $($mk.resMsg)" -ForegroundColor Red
    $script:GAGAL++
    # Jangan tinggalkan akun dalam keadaan terhapus — dan PASTIKAN berhasil.
    if ($idLama) {
        $rb = Api POST "users/$idLama/restore"
        if ($rb.resCode -eq 200) {
            Write-Host "           (akun id=$idLama dipulihkan kembali apa adanya)" -ForegroundColor Yellow
        } else {
            Write-Host "  [GAGAL   ] Akun id=$idLama TERTINGGAL TERHAPUS ($($rb.resCode)). Pulihkan manual: POST /users/$idLama/restore" -ForegroundColor Red
        }
    }
}

# ── Login SuperAdmin ──
$login = Api POST "login" @{ email = "superadmin@example.com"; password = $env:TEST_ADMIN_PASSWORD; device_name = "seed-script" }
if ($login.resCode -ne 200) { Write-Host "ERROR: login SuperAdmin gagal -- $($login.resMsg)" -ForegroundColor Red; exit 1 }
$script:TOKEN = $login.data.token
Write-Host "Login SuperAdmin OK`n"

# ── Semester aktif (untuk cek keterhubungan guru/siswa) ──
$rSem = Api GET "akademik/semester/aktif"
if ($rSem.resCode -ne 200 -or -not $rSem.data.tahunAjaran) {
    # Tanpa semester aktif, pemilihan guru/siswa di bawah memakai tahun ajaran
    # kosong dan diam-diam jatuh ke cadangan "siswa pertama" yang belum tentu
    # terdaftar di kelas mana pun.
    Write-Host "  [GAGAL   ] Semester aktif: $($rSem.resCode) $($rSem.resMsg) -- pemilihan guru/siswa tidak dapat dipercaya" -ForegroundColor Red
    $script:GAGAL++
}
$sem = $rSem.data
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
Ensure-KaryawanRecord $kwBiasaEmail "Akun Test Karyawan Biasa" "Satpam" $false "Record karyawan biasa" "8"
# Membuat karyawan otomatis membuat/memulihkan akun user-nya dengan password
# turunan email + wajib-ganti, jadi passwordnya dipastikan ulang di sini.
Ensure-Account "Akun Test Karyawan" $kwBiasaEmail "Karyawan" "KaryawanTest123" | Out-Null

# -- 2b. Administrator Sekolah: karyawan NYATA bertanda isAdminSekolah --
# Bedanya dengan akun karyawan biasa di atas hanya pada penanda isAdminSekolah.
# Penanda itu yang membuka menu manajemen di app, jadi sesi Android butuh
# keduanya untuk membuktikan gating membaca penanda, bukan role.
$adminSekEmail = "akuntest.adminsekolah@example.com"
Ensure-KaryawanRecord $adminSekEmail "Akun Test Administrator Sekolah" "Tata Usaha" $true "Administrator Sekolah" "9"
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
$cariPA = Api GET "users`?search=$([uri]::EscapeDataString($petugasEmail))&per_page=5"
$adaPA = @($cariPA.data.data | Where-Object { $_.email -eq $petugasEmail })
if ($cariPA.resCode -ne 200) {
    # Pencarian gagal BUKAN berarti akunnya tidak ada — mendaftarkannya hanya
    # akan berujung 422 "email dipakai" dengan pesan yang menyesatkan.
    Write-Host "  [GAGAL   ] Cek Petugas Acara : $($cariPA.resCode) $($cariPA.resMsg) -- dilewati" -ForegroundColor Red
    $script:GAGAL++
} elseif ($adaPA.Count -eq 0) {
    $mkPA = Api POST "register" @{
        name = "Akun Test Petugas Acara"; email = $petugasEmail
        password = $petugasPw; confirm_password = $petugasPw
        role = "Karyawan"; isPetugasAcara = $true
    }
    if ($mkPA.resCode -eq 201) { Write-Host "  [BUAT    ] Petugas Acara : $petugasEmail" }
    else { Write-Host "  [GAGAL   ] Petugas Acara : $($mkPA.resCode) $($mkPA.resMsg)" -ForegroundColor Red; $script:GAGAL++ }
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


# ── 5. Nilai siswa test di semester AKTIF (opt-in: $env:SEED_NILAI = "1") ──
# Tanpa ini layar "Nilai Saya" / raport di app hanya pernah diuji dalam keadaan
# KOSONG. Diisi lewat API, bukan DB, supaya rata-rata harian dan nilai akhir
# dihitung oleh logika yang sama dengan produksi.
#
# OPT-IN dengan sengaja. Siswa test di atas adalah siswa ASLI pertama yang
# terdaftar di kelas. Di server pengembangan itu memang siswa uji, tapi di server
# sekolah sungguhan menulis nilai karangan untuknya akan masuk ke raportnya DAN
# menggeser peringkat seluruh teman sekelasnya — tanpa ada yang menghapusnya.
#
# HANYA mengisi pasangan (siswa_kelas, pengampu) yang BELUM punya nilai:
# `POST /akademik/nilai` untuk slot yang sudah ada melakukan upsert, jadi kalau
# dikirim ulang tiap run, nilai yang sengaja diedit penguji tertimpa diam-diam.
# Karena itu setiap GET di bawah WAJIB berhasil sebelum ada yang ditulis — GET
# yang gagal terbaca "belum ada nilai" dan berujung menimpa semuanya.
if ($siswaPick -and $env:SEED_NILAI -ne '1') {
    Write-Host "  [LEWATI  ] Nilai siswa test -- set `$env:SEED_NILAI = '1' untuk mengisinya (JANGAN di server sekolah sungguhan)" -ForegroundColor DarkGray
}
if ($siswaPick -and $env:SEED_NILAI -eq '1') {
    $qSem = "tahun_ajaran=$([uri]::EscapeDataString($tahun))&semester=$semester"
    $rKelas = Api GET "akademik/siswa/$($siswaPick.idSiswa)/kelas`?$qSem"
    $kSaya = $null
    if ($rKelas.resCode -eq 200) {
        $kSaya = @($rKelas.data | Where-Object { $_.tahunAjaran -eq $tahun -and "$($_.semester)" -eq "$semester" })[0]
    }
    if ($rKelas.resCode -ne 200) {
        Write-Host "  [GAGAL   ] Nilai siswa: cek kelas $($rKelas.resCode) $($rKelas.resMsg) -- tidak ada yang ditulis" -ForegroundColor Red
        $script:GAGAL++
    } elseif (-not $kSaya) {
        Write-Host "  [SKIP    ] Nilai siswa: $siswaEmail tidak terdaftar di kelas mana pun pada $tahun sem $semester" -ForegroundColor Yellow
    } else {
        $rPm   = Api GET "akademik/kelas/$($kSaya.kelasId)/pengampu`?$qSem"
        $rAda  = Api GET "akademik/nilai/siswa/$($siswaPick.idSiswa)`?$qSem"
        if ($rPm.resCode -ne 200 -or $rAda.resCode -ne 200) {
            Write-Host "  [GAGAL   ] Nilai siswa: cek pengampu=$($rPm.resCode) / nilai=$($rAda.resCode) -- tidak ada yang ditulis" -ForegroundColor Red
            $script:GAGAL++
        } else {
            $pengampuKelas = @($rPm.data | Where-Object { $_.tahunAjaran -eq $tahun -and "$($_.semester)" -eq "$semester" })
            $pengampuTerisi = @($rAda.data | ForEach-Object {
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
                        $script:GAGAL++
                    }
                }
                $i++
            }
        }
    }
}

# ── Ringkasan ──
# Dulu selalu mencetak "SIAP DIPAKAI" dan keluar dengan kode 0 apa pun yang
# terjadi, sehingga pemanggil (dan manusia yang hanya membaca baris terakhir)
# tidak bisa membedakan seed yang berhasil dari yang separuh gagal.
Write-Host ""
Write-Host "=============================================================="
if ($script:GAGAL -gt 0) {
    Write-Host "  BELUM SIAP: $($script:GAGAL) langkah GAGAL - lihat baris [GAGAL] di atas" -ForegroundColor Red
} else {
    Write-Host "  AKUN TEST SIAP DIPAKAI" -ForegroundColor Green
}
Write-Host "  Admin    : akuntest.admin@example.com    / AdminTest123"
Write-Host "  Karyawan : akuntest.karyawan@example.com / KaryawanTest123"
Write-Host "  Petugas Acara : akuntest.petugasacara@example.com / PetugasAcaraTest123 (HANYA boleh kelola acara)"
Write-Host "  Adm.Sek  : akuntest.adminsekolah@example.com / AdminSekolahTest123"
Write-Host "             (karyawan bertanda isAdminSekolah - boleh manajemen akademik)"
if ($guruEmail)  { Write-Host "  Guru     : $guruEmail / GuruTest123" }
if ($siswaEmail) { Write-Host "  Siswa    : $siswaEmail / SiswaTest123" }
Write-Host "=============================================================="
if ($script:GAGAL -gt 0) { exit 1 }
exit 0