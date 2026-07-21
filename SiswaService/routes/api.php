<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SiswaController;

// Route::get('/', function () {
//     return app()->version();
// });

Route::prefix('siswa')->group(function () {
    Route::get('all', [SiswaController::class, 'index']);
    Route::get('lookup', [SiswaController::class, 'lookupByEmail']);
    Route::get('lookup-kartu', [SiswaController::class, 'lookupByKartu']);
    Route::post('by-ids', [SiswaController::class, 'byIds']);
    Route::get('/', [SiswaController::class, 'show']);
    Route::post('/', [SiswaController::class, 'store']);
    Route::post('update', [SiswaController::class, 'update']);
    Route::post('kartu/terbitkan', [SiswaController::class, 'terbitkanKartu']);
    Route::post('kartu/blokir', [SiswaController::class, 'blokirKartu']);
    Route::delete('/{id}', [SiswaController::class, 'destroy']);
});
