<?php

use App\Http\Controllers\Master\SiswaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'force.pwd'])->prefix(config('gateway.siswa_prefix'))->group(function(){
    Route::get('all', [SiswaController::class, 'index']);
    // Detail berisi data pribadi (alamat, kontak orang tua, foto) —
    // sesama Siswa tidak boleh mengakses profil lengkap siswa lain
    Route::get('/', [SiswaController::class, 'show'])->middleware('check.role:SuperAdmin,Admin,Guru,Karyawan');
    Route::post('/', [SiswaController::class, 'store'])->middleware('check.role:SuperAdmin,Admin');
    Route::post('update', [SiswaController::class, 'update'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('/{id}', [SiswaController::class, 'destroy'])->middleware('check.role:SuperAdmin,Admin');
});
