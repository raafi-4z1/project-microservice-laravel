<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SiswaKelasController;
use App\Http\Controllers\PengampuMapelController;
use App\Http\Controllers\SemesterAktifController;
use App\Http\Controllers\JamPelajaranController;
use App\Http\Controllers\JadwalPelajaranController;
use App\Http\Controllers\NilaiController;
use App\Http\Controllers\PengaturanNilaiController;
use App\Http\Controllers\AbsensiController;

Route::prefix('akademik')->group(function () {

    // Pembagian Kelas
    Route::post('kelas/assign', [SiswaKelasController::class, 'assign']);
    Route::patch('kelas/assign/{id}', [SiswaKelasController::class, 'pindahKelas']);
    Route::delete('kelas/assign/{id}', [SiswaKelasController::class, 'removeSiswa']);
    Route::get('kelas/{kelas_id}/siswa', [SiswaKelasController::class, 'getSiswaByKelas']);
    Route::get('siswa-kelas/terdaftar', [SiswaKelasController::class, 'getSiswaTerdaftar']);
    Route::get('siswa/{siswa_id}/kelas', [SiswaKelasController::class, 'getKelasBySiswa']);
    // Riwayat lengkap (termasuk data yang sudah diubah/dibatalkan)
    Route::get('kelas/{kelas_id}/siswa/riwayat', [SiswaKelasController::class, 'getRiwayatKelas']);
    Route::get('siswa/{siswa_id}/kelas/riwayat', [SiswaKelasController::class, 'getRiwayatSiswa']);

    // Pengampu Mapel
    Route::post('pengampu', [PengampuMapelController::class, 'assign']);
    Route::patch('pengampu/{id}', [PengampuMapelController::class, 'gantiGuru']);
    Route::delete('pengampu/{id}', [PengampuMapelController::class, 'removeGuru']);
    Route::get('kelas/{kelas_id}/pengampu', [PengampuMapelController::class, 'getByKelas']);
    Route::get('guru/{guru_id}/mapel', [PengampuMapelController::class, 'getMapelByGuru']);
    Route::get('mapel/{mapel_id}/guru', [PengampuMapelController::class, 'getGuruByMapel']);
    // Riwayat lengkap (termasuk data yang sudah diubah/dibatalkan)
    Route::get('guru/{guru_id}/mapel/riwayat', [PengampuMapelController::class, 'getRiwayatGuru']);
    Route::get('mapel/{mapel_id}/guru/riwayat', [PengampuMapelController::class, 'getRiwayatMapel']);

    // Semester Aktif — acuan tahun ajaran untuk seluruh sistem
    Route::get('semester/aktif', [SemesterAktifController::class, 'getAktif']);
    Route::post('semester/aktif', [SemesterAktifController::class, 'setAktif']);
    Route::get('semester/riwayat', [SemesterAktifController::class, 'getRiwayat']);

    // Jam Pelajaran (master slot waktu)
    Route::get('jam', [JamPelajaranController::class, 'index']);
    Route::post('jam', [JamPelajaranController::class, 'store']);
    Route::patch('jam/{id}', [JamPelajaranController::class, 'update']);
    Route::delete('jam/{id}', [JamPelajaranController::class, 'destroy']);

    // Jadwal Pelajaran
    Route::post('jadwal', [JadwalPelajaranController::class, 'store']);
    Route::patch('jadwal/{id}', [JadwalPelajaranController::class, 'update']);
    Route::delete('jadwal/{id}', [JadwalPelajaranController::class, 'destroy']);
    Route::get('jadwal/pengampu/{pengampu_id}', [JadwalPelajaranController::class, 'getByPengampu']);
    Route::get('jadwal/kelas/{kelas_id}', [JadwalPelajaranController::class, 'getByKelas']);
    Route::get('jadwal/guru/{guru_id}', [JadwalPelajaranController::class, 'getByGuru']);
    Route::get('jadwal/siswa/{siswa_id}', [JadwalPelajaranController::class, 'getBySiswa']);
    // Riwayat lengkap (termasuk jadwal yang sudah dihapus)
    Route::get('jadwal/pengampu/{pengampu_id}/riwayat', [JadwalPelajaranController::class, 'getRiwayatByPengampu']);
    Route::get('jadwal/kelas/{kelas_id}/riwayat', [JadwalPelajaranController::class, 'getRiwayatByKelas']);
    Route::get('jadwal/guru/{guru_id}/riwayat', [JadwalPelajaranController::class, 'getRiwayatByGuru']);

    // Pengaturan Bobot Nilai (dikonfigurasi per semester)
    Route::get('pengaturan-nilai', [PengaturanNilaiController::class, 'index']);
    Route::post('pengaturan-nilai', [PengaturanNilaiController::class, 'store']);
    Route::patch('pengaturan-nilai/{id}', [PengaturanNilaiController::class, 'update']);

    // Nilai & Raport
    Route::post('nilai', [NilaiController::class, 'store']);
    Route::patch('nilai/{id}', [NilaiController::class, 'update']);
    Route::delete('nilai/{id}', [NilaiController::class, 'destroy']);
    Route::get('nilai/pengampu/{pengampu_id}', [NilaiController::class, 'getByPengampu']);
    Route::get('nilai/kelas/{kelas_id}',       [NilaiController::class, 'getByKelas']);
    Route::get('nilai/siswa/{siswa_id}',        [NilaiController::class, 'getBySiswa']);
    Route::get('nilai/raport/siswa/{siswa_id}', [NilaiController::class, 'getRaportSiswa']);
    Route::get('nilai/raport/kelas/{kelas_id}', [NilaiController::class, 'getRaportKelas']);
    Route::get('nilai/ranking/kelas/{kelas_id}',[NilaiController::class, 'getRankingKelas']);

    // Absensi — pencatatan scan masuk (dipanggil Gateway setelah resolve kartu/terminal)
    Route::post('absensi/scan-siswa',   [AbsensiController::class, 'scanSiswa']);
    Route::post('absensi/scan-pegawai', [AbsensiController::class, 'scanPegawai']);
});
