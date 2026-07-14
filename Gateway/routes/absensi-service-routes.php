<?php

use App\Http\Controllers\Absensi\AbsensiController;
use Illuminate\Support\Facades\Route;

// Absensi via SCAN dari terminal terdaftar (bukan user login).
// Autentikasi terminal via header X-Terminal-Id + X-Terminal-Token (auth.terminal).
Route::middleware(['auth.terminal'])->prefix('absensi')->group(function () {
    Route::post('scan', [AbsensiController::class, 'scan']);
});
