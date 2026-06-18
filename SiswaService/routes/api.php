<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SiswaController;

// Route::get('/', function () {
//     return app()->version();
// });

Route::prefix('siswa')->group(function () {
    Route::get('all', [SiswaController::class, 'index']);
    Route::get('/', [SiswaController::class, 'show']);
    Route::post('/', [SiswaController::class, 'store']);
    Route::post('update', [SiswaController::class, 'update']);
    Route::delete('/{id}', [SiswaController::class, 'destroy']);
});
