<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SiswaKelasController;
use App\Http\Controllers\PengampuMapelController;
use App\Http\Controllers\SemesterAktifController;

Route::prefix('akademik')->group(function () {

    // Pembagian Kelas
    Route::post('kelas/assign', [SiswaKelasController::class, 'assign']);
    Route::patch('kelas/assign/{id}', [SiswaKelasController::class, 'pindahKelas']);
    Route::delete('kelas/assign/{id}', [SiswaKelasController::class, 'removeSiswa']);
    Route::get('kelas/{kelas_id}/siswa', [SiswaKelasController::class, 'getSiswaByKelas']);
    Route::get('siswa/{siswa_id}/kelas', [SiswaKelasController::class, 'getKelasBySiswa']);
    // Riwayat lengkap (termasuk data yang sudah diubah/dibatalkan)
    Route::get('kelas/{kelas_id}/siswa/riwayat', [SiswaKelasController::class, 'getRiwayatKelas']);
    Route::get('siswa/{siswa_id}/kelas/riwayat', [SiswaKelasController::class, 'getRiwayatSiswa']);

    // Pengampu Mapel
    Route::post('pengampu', [PengampuMapelController::class, 'assign']);
    Route::patch('pengampu/{id}', [PengampuMapelController::class, 'gantiGuru']);
    Route::delete('pengampu/{id}', [PengampuMapelController::class, 'removeGuru']);
    Route::get('guru/{guru_id}/mapel', [PengampuMapelController::class, 'getMapelByGuru']);
    Route::get('mapel/{mapel_id}/guru', [PengampuMapelController::class, 'getGuruByMapel']);
    // Riwayat lengkap (termasuk data yang sudah diubah/dibatalkan)
    Route::get('guru/{guru_id}/mapel/riwayat', [PengampuMapelController::class, 'getRiwayatGuru']);
    Route::get('mapel/{mapel_id}/guru/riwayat', [PengampuMapelController::class, 'getRiwayatMapel']);

    // Semester Aktif — acuan tahun ajaran untuk seluruh sistem
    Route::get('semester/aktif', [SemesterAktifController::class, 'getAktif']);
    Route::post('semester/aktif', [SemesterAktifController::class, 'setAktif']);
    Route::get('semester/riwayat', [SemesterAktifController::class, 'getRiwayat']);
});
