<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GuruController;

// Route::get('/', function () {
//     return app()->version();
// });

Route::prefix('guru')->group(function () {
    Route::get('all', [GuruController::class, 'index']);
    // id + nama saja, TERMASUK yang sudah dihapus (resolusi nama historis
    // + cache sekolah besar). Bukan pengganti /all untuk dropdown.
    Route::get('nama', [GuruController::class, 'nama']);
    // Berkas foto (bukan Base64). Disk private -> harus lewat endpoint
    // ber-autentikasi, bukan URL publik.
    Route::get('foto/{id}', [GuruController::class, 'foto']);
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
