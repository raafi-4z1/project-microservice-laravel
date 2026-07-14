<?php

use App\Http\Controllers\Master\GuruController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'force.pwd'])->prefix(config('gateway.guru_prefix'))->group(function(){
    Route::get('all', [GuruController::class, 'index']);
    Route::get('/', [GuruController::class, 'show']);
    Route::post('/', [GuruController::class, 'store'])->middleware('check.role:SuperAdmin,Admin');
    Route::post('update', [GuruController::class, 'update'])->middleware('check.role:SuperAdmin,Admin');
    // Kartu absensi — SuperAdmin/Admin
    Route::post('kartu/terbitkan', [GuruController::class, 'terbitkanKartu'])->middleware('check.role:SuperAdmin,Admin');
    Route::post('kartu/blokir', [GuruController::class, 'blokirKartu'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('/{id}', [GuruController::class, 'destroy'])->middleware('check.role:SuperAdmin,Admin');
});
