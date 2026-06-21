<?php

use App\Http\Controllers\Akademik\AkademikController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix(config('gateway.akademik_prefix'))->group(function () {

    // Pembagian Kelas — Write: SuperAdmin, Admin | Read: semua role
    Route::post('kelas/assign', [AkademikController::class, 'assignSiswa'])->middleware('check.role:SuperAdmin,Admin');
    Route::patch('kelas/assign/{id}', [AkademikController::class, 'pindahKelas'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('kelas/assign/{id}', [AkademikController::class, 'removeSiswa'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('kelas/{kelas_id}/siswa', [AkademikController::class, 'getSiswaByKelas']);
    Route::get('siswa/{siswa_id}/kelas', [AkademikController::class, 'getKelasBySiswa']);
    // Riwayat: SuperAdmin, Admin (data sensitif pencatatan sekolah)
    Route::get('kelas/{kelas_id}/siswa/riwayat', [AkademikController::class, 'getRiwayatKelas'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('siswa/{siswa_id}/kelas/riwayat', [AkademikController::class, 'getRiwayatSiswa'])->middleware('check.role:SuperAdmin,Admin');

    // Pengampu Mapel — Write: SuperAdmin, Admin | Read: semua role
    Route::post('pengampu', [AkademikController::class, 'assignGuru'])->middleware('check.role:SuperAdmin,Admin');
    Route::patch('pengampu/{id}', [AkademikController::class, 'gantiGuru'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('pengampu/{id}', [AkademikController::class, 'removeGuru'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('guru/{guru_id}/mapel', [AkademikController::class, 'getMapelByGuru']);
    Route::get('mapel/{mapel_id}/guru', [AkademikController::class, 'getGuruByMapel']);
    // Riwayat: SuperAdmin, Admin (data sensitif pencatatan sekolah)
    Route::get('guru/{guru_id}/mapel/riwayat', [AkademikController::class, 'getRiwayatGuru'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('mapel/{mapel_id}/guru/riwayat', [AkademikController::class, 'getRiwayatMapel'])->middleware('check.role:SuperAdmin,Admin');

    // Semester Aktif — acuan tahun ajaran untuk seluruh sistem
    Route::get('semester/aktif', [AkademikController::class, 'getSemesterAktif']);
    Route::post('semester/aktif', [AkademikController::class, 'setSemesterAktif'])->middleware('check.role:SuperAdmin,Admin');
    Route::get('semester/riwayat', [AkademikController::class, 'getRiwayatSemester']);
});
