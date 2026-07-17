<?php

use App\Http\Controllers\Master\KaryawanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'force.pwd'])->prefix(config('gateway.karyawan_prefix'))->group(function(){
    Route::get('all', [KaryawanController::class, 'index']);
    // Detail berisi data pribadi (alamat, no_telp) — Siswa diblokir; Guru menerima
    // versi tersaring (field publik saja, lihat KaryawanController::show)
    Route::get('/', [KaryawanController::class, 'show'])->middleware('check.role:SuperAdmin,Admin,Guru,Karyawan');
    Route::post('/', [KaryawanController::class, 'store'])->middleware('check.role:SuperAdmin,Admin');
    Route::post('update', [KaryawanController::class, 'update'])->middleware('check.role:SuperAdmin,Admin');
    // Kartu absensi — SuperAdmin/Admin
    Route::post('kartu/terbitkan', [KaryawanController::class, 'terbitkanKartu'])->middleware('check.role:SuperAdmin,Admin');
    Route::post('kartu/blokir', [KaryawanController::class, 'blokirKartu'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('/{id}', [KaryawanController::class, 'destroy'])->middleware('check.role:SuperAdmin,Admin');
});
