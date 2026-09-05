<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RuangKelasController;

// Route::get('/', function () {
//     return app()->version();
// });

Route::prefix('class')->group(function () {
    Route::get('all', [RuangKelasController::class, 'index']);
    // id + nama saja, TERMASUK yang sudah dihapus (resolusi nama historis
    // + cache sekolah besar). Bukan pengganti /all untuk dropdown.
    Route::get('nama', [RuangKelasController::class, 'nama']);
    Route::get('/', [RuangKelasController::class, 'show']);
    Route::post('/', [RuangKelasController::class, 'store']);
    Route::post('update', [RuangKelasController::class, 'update']);
    Route::delete('/{id}', [RuangKelasController::class, 'destroy']);
});
