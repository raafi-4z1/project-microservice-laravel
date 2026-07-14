<?php

use App\Http\Controllers\KartuController;
use Illuminate\Support\Facades\Route;

// Utilitas kartu absensi — SuperAdmin/Admin (untuk cetak QR kartu)
Route::middleware(['auth:api', 'force.pwd', 'check.role:SuperAdmin,Admin'])->prefix('kartu')->group(function () {
    Route::get('qr', [KartuController::class, 'qr']);
});
