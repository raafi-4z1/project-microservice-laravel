<?php

use App\Http\Controllers\Absensi\AbsensiController;
use Illuminate\Support\Facades\Route;

// Absensi via SCAN / PIN dari terminal terdaftar (bukan user login).
// Autentikasi terminal via header X-Terminal-Id + X-Terminal-Token (auth.terminal).
Route::middleware(['auth.terminal'])->prefix('absensi')->group(function () {
    Route::post('scan', [AbsensiController::class, 'scan']);
    Route::post('pin/absen', [AbsensiController::class, 'absenPin']);
});

// Manajemen PIN oleh user login.
Route::middleware(['auth:api', 'force.pwd'])->prefix('absensi')->group(function () {
    // Admin membuka jendela PIN untuk pegawai yang lupa kartu
    Route::post('pin/buka', [AbsensiController::class, 'bukaPinWindow'])->middleware('check.role:SuperAdmin,Admin');
    // Pegawai mengatur PIN sendiri
    Route::post('pin/atur', [AbsensiController::class, 'aturPin'])->middleware('check.role:Guru,Karyawan');
});
