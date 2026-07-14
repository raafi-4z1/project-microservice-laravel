<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GuruController;

// Route::get('/', function () {
//     return app()->version();
// });

Route::prefix('guru')->group(function () {
    Route::get('all', [GuruController::class, 'index']);
    Route::get('lookup', [GuruController::class, 'lookupByEmail']);
    Route::get('lookup-kartu', [GuruController::class, 'lookupByKartu']);
    Route::get('/', [GuruController::class, 'show']);
    Route::post('/', [GuruController::class, 'store']);
    Route::post('update', [GuruController::class, 'update']);
    Route::post('kartu/terbitkan', [GuruController::class, 'terbitkanKartu']);
    Route::post('kartu/blokir', [GuruController::class, 'blokirKartu']);
    Route::post('pin/set', [GuruController::class, 'setPin']);
    Route::post('pin/verify', [GuruController::class, 'verifyPin']);
    Route::delete('/{id}', [GuruController::class, 'destroy']);
});
