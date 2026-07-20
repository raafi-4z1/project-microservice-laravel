<?php

use App\Http\Controllers\Akademik\AkademikController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'force.pwd'])->prefix(config('gateway.akademik_prefix'))->group(function () {

    // Pembagian Kelas — Write: SuperAdmin, Admin | Read: semua role
    Route::post('kelas/assign', [AkademikController::class, 'assignSiswa'])->middleware('check.role:SuperAdmin,Admin');
    Route::patch('kelas/assign/{id}', [AkademikController::class, 'pindahKelas'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('kelas/assign/{id}', [AkademikController::class, 'removeSiswa'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('kelas/{kelas_id}/siswa', [AkademikController::class, 'getSiswaByKelas']);
    Route::get('siswa/belum-terdaftar', [AkademikController::class, 'getSiswaBelumTerdaftar'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('siswa/{siswa_id}/kelas', [AkademikController::class, 'getKelasBySiswa']);
    // Riwayat: SuperAdmin, Admin (data sensitif pencatatan sekolah)
    Route::get('kelas/{kelas_id}/siswa/riwayat', [AkademikController::class, 'getRiwayatKelas'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('siswa/{siswa_id}/kelas/riwayat', [AkademikController::class, 'getRiwayatSiswa'])->middleware('check.role:SuperAdmin,Admin');

    // Pengaturan Absensi — Write: SuperAdmin, Admin | Read efektif: semua role
    Route::get('pengaturan-absensi/efektif', [AkademikController::class, 'getPengaturanAbsensiEfektif']);
    Route::get('pengaturan-absensi', [AkademikController::class, 'getPengaturanAbsensi'])->middleware('check.role:SuperAdmin,Admin');
    Route::post('pengaturan-absensi', [AkademikController::class, 'storePengaturanAbsensi'])->middleware('check.role:SuperAdmin,Admin');
    Route::patch('pengaturan-absensi/{id}', [AkademikController::class, 'updatePengaturanAbsensi'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('pengaturan-absensi/{id}', [AkademikController::class, 'destroyPengaturanAbsensi'])->middleware('check.role:SuperAdmin,Admin');

    // Periode Khusus (Ramadan/ujian/libur/kegiatan) — Write: SuperAdmin, Admin | Read: semua role
    Route::get('periode/aktif', [AkademikController::class, 'getPeriodeAktif']);
    Route::get('periode', [AkademikController::class, 'getPeriode']);
    Route::post('periode', [AkademikController::class, 'storePeriode'])->middleware('check.role:SuperAdmin,Admin');
    Route::patch('periode/{id}', [AkademikController::class, 'updatePeriode'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('periode/{id}', [AkademikController::class, 'destroyPeriode'])->middleware('check.role:SuperAdmin,Admin');

    // Wali Kelas — Write: SuperAdmin, Admin | Read: semua role
    Route::post('wali', [AkademikController::class, 'assignWali'])->middleware('check.role:SuperAdmin,Admin');
    Route::patch('wali/{id}', [AkademikController::class, 'gantiWali'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('wali/{id}', [AkademikController::class, 'removeWali'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('kelas/{kelas_id}/wali', [AkademikController::class, 'getWaliByKelas']);
    Route::get('guru/{guru_id}/wali', [AkademikController::class, 'getWaliByGuru']);

    // Pengampu Mapel — Write: SuperAdmin, Admin | Read: semua role
    Route::post('pengampu', [AkademikController::class, 'assignGuru'])->middleware('check.role:SuperAdmin,Admin');
    Route::patch('pengampu/{id}', [AkademikController::class, 'gantiGuru'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('pengampu/{id}', [AkademikController::class, 'removeGuru'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('kelas/{kelas_id}/pengampu', [AkademikController::class, 'getPengampuByKelas']);
    Route::get('guru/{guru_id}/mapel', [AkademikController::class, 'getMapelByGuru']);
    Route::get('mapel/{mapel_id}/guru', [AkademikController::class, 'getGuruByMapel']);
    // Riwayat: SuperAdmin, Admin (data sensitif pencatatan sekolah)
    Route::get('guru/{guru_id}/mapel/riwayat', [AkademikController::class, 'getRiwayatGuru'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('mapel/{mapel_id}/guru/riwayat', [AkademikController::class, 'getRiwayatMapel'])->middleware('check.role:SuperAdmin,Admin');

    // Semester Aktif — acuan tahun ajaran untuk seluruh sistem
    Route::get('semester/aktif', [AkademikController::class, 'getSemesterAktif']);
    Route::post('semester/aktif', [AkademikController::class, 'setSemesterAktif'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('semester/riwayat', [AkademikController::class, 'getRiwayatSemester']);

    // Jam Pelajaran (master slot waktu) — Write: SuperAdmin, Admin | Read: semua role
    Route::get('jam', [AkademikController::class, 'getJamPelajaran']);
    Route::post('jam', [AkademikController::class, 'storeJam'])->middleware('check.role:SuperAdmin,Admin');
    Route::patch('jam/{id}', [AkademikController::class, 'updateJam'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('jam/{id}', [AkademikController::class, 'destroyJam'])->middleware('check.role:SuperAdmin,Admin');

    // Jadwal Pelajaran — Write: SuperAdmin, Admin | Read: semua role
    Route::post('jadwal', [AkademikController::class, 'storeJadwal'])->middleware('check.role:SuperAdmin,Admin');
    Route::patch('jadwal/{id}', [AkademikController::class, 'updateJadwal'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('jadwal/{id}', [AkademikController::class, 'removeJadwal'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('jadwal/pengampu/{pengampu_id}', [AkademikController::class, 'getJadwalByPengampu']);
    Route::get('jadwal/kelas/{kelas_id}', [AkademikController::class, 'getJadwalByKelas']);
    Route::get('jadwal/guru/{guru_id}', [AkademikController::class, 'getJadwalByGuru']);
    Route::get('jadwal/siswa/{siswa_id}', [AkademikController::class, 'getJadwalBySiswa']);
    // Riwayat: SuperAdmin, Admin (data historis termasuk yang sudah dihapus)
    Route::get('jadwal/pengampu/{pengampu_id}/riwayat', [AkademikController::class, 'getRiwayatJadwalByPengampu'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('jadwal/kelas/{kelas_id}/riwayat', [AkademikController::class, 'getRiwayatJadwalByKelas'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('jadwal/guru/{guru_id}/riwayat', [AkademikController::class, 'getRiwayatJadwalByGuru'])->middleware('check.role:SuperAdmin,Admin');

    // Pengaturan Bobot Nilai — SuperAdmin, Admin only
    Route::get('pengaturan-nilai', [AkademikController::class, 'getPengaturanNilai'])->middleware('check.role:SuperAdmin,Admin');
    Route::post('pengaturan-nilai', [AkademikController::class, 'storePengaturanNilai'])->middleware('check.role:SuperAdmin,Admin');
    Route::patch('pengaturan-nilai/{id}', [AkademikController::class, 'updatePengaturanNilai'])->middleware('check.role:SuperAdmin,Admin');

    // Nilai & Raport — Write: Admin, SuperAdmin, Guru | Read: sesuai role
    Route::post('nilai', [AkademikController::class, 'storeNilai'])->middleware('check.role:SuperAdmin,Admin,Guru');
    Route::patch('nilai/{id}', [AkademikController::class, 'updateNilai'])->middleware('check.role:SuperAdmin,Admin,Guru');
    Route::delete('nilai/{id}', [AkademikController::class, 'destroyNilai'])->middleware('check.role:SuperAdmin,Admin,Guru');
    Route::get('nilai/pengampu/{pengampu_id}', [AkademikController::class, 'getNilaiByPengampu'])->middleware('check.role:SuperAdmin,Admin,Guru,Karyawan');
    Route::get('nilai/kelas/{kelas_id}', [AkademikController::class, 'getNilaiByKelas'])->middleware('check.role:SuperAdmin,Admin,Guru,Karyawan');
    Route::get('nilai/siswa/{siswa_id}', [AkademikController::class, 'getNilaiBySiswa'])->middleware('check.role:SuperAdmin,Admin,Guru,Karyawan');
    // Siswa: self-only via /nilai/saya
    Route::get('nilai/saya', [AkademikController::class, 'getNilaiSaya'])->middleware('check.role:Siswa');

    // Raport — Admin, SuperAdmin, Guru, Karyawan (full); Siswa (diri sendiri via /saya)
    Route::get('raport/saya', [AkademikController::class, 'getRaportSaya'])->middleware('check.role:Siswa');
    Route::get('raport/siswa/{siswa_id}', [AkademikController::class, 'getRaportSiswa'])->middleware('check.role:SuperAdmin,Admin,Guru,Karyawan');
    Route::get('raport/kelas/{kelas_id}', [AkademikController::class, 'getRaportKelas'])->middleware('check.role:SuperAdmin,Admin,Guru,Karyawan');

    // Ranking — Siswa (posisi sendiri via /saya); Admin/Guru/Karyawan (full)
    Route::get('nilai/ranking/saya', [AkademikController::class, 'getRankingSaya'])->middleware('check.role:Siswa');
    Route::get('nilai/ranking/kelas/{kelas_id}', [AkademikController::class, 'getRankingKelas'])->middleware('check.role:SuperAdmin,Admin,Guru,Karyawan');

    // Absensi per pelajaran — Guru menandai siswa saat jam pelajarannya
    Route::get('absensi/pelajaran/sekarang', [AkademikController::class, 'getPelajaranSekarang'])->middleware('check.role:Guru');
    Route::post('absensi/pelajaran/tandai', [AkademikController::class, 'tandaiPelajaran'])->middleware('check.role:Guru');
    Route::get('absensi/pelajaran/{jadwal_id}/siswa', [AkademikController::class, 'getDaftarSiswaJadwal'])->middleware('check.role:SuperAdmin,Admin,Guru');

    // Absensi keluar (pulang awal / izin keluar) — disetujui wali kelas / admin
    Route::post('absensi/keluar', [AkademikController::class, 'catatKeluar'])->middleware('check.role:SuperAdmin,Admin,Guru');
    Route::get('absensi/keluar', [AkademikController::class, 'daftarKeluar'])->middleware('check.role:SuperAdmin,Admin,Guru');

    // Rekap absensi — Siswa lihat miliknya via /saya; staf lihat penuh
    Route::get('absensi/rekap/harian/saya', [AkademikController::class, 'rekapHarianSaya'])->middleware('check.role:Siswa');
    Route::get('absensi/rekap/pelajaran/saya', [AkademikController::class, 'rekapPelajaranSaya'])->middleware('check.role:Siswa');
    Route::get('absensi/rekap/harian/kelas/{kelas_id}', [AkademikController::class, 'rekapHarianKelas'])->middleware('check.role:SuperAdmin,Admin,Guru,Karyawan');
    Route::get('absensi/rekap/harian/siswa/{siswa_id}', [AkademikController::class, 'rekapHarianSiswa'])->middleware('check.role:SuperAdmin,Admin,Guru,Karyawan');
    Route::get('absensi/rekap/pelajaran/siswa/{siswa_id}', [AkademikController::class, 'rekapPelajaranSiswa'])->middleware('check.role:SuperAdmin,Admin,Guru,Karyawan');
    Route::get('absensi/rekap/pegawai/{subjek_tipe}/{subjek_id}', [AkademikController::class, 'rekapPegawai'])->middleware('check.role:SuperAdmin,Admin');
});
