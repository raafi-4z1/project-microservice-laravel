<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MapelController;

// Route::get('/', function () {
//     return app()->version();
// });

Route::prefix('mapel')->group(function () {
    Route::get('all', [MapelController::class, 'index']);
    // id + nama saja, TERMASUK yang sudah dihapus (resolusi nama historis
    // + cache sekolah besar). Bukan pengganti /all untuk dropdown.
    Route::get('nama', [MapelController::class, 'nama']);
    Route::get('/', [MapelController::class, 'show']);
    Route::post('/', [MapelController::class, 'store']);
    Route::post('update', [MapelController::class, 'update']);
    Route::delete('/{id}', [MapelController::class, 'destroy']);
});
