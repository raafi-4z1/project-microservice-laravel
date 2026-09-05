<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SiswaController;

// Route::get('/', function () {
//     return app()->version();
// });

Route::prefix('siswa')->group(function () {
    Route::get('all', [SiswaController::class, 'index']);
    // id + nama saja, TERMASUK yang sudah dihapus (resolusi nama historis
    // + cache sekolah besar). Bukan pengganti /all untuk dropdown.
    Route::get('nama', [SiswaController::class, 'nama']);
    // Berkas foto (bukan Base64). Disk private -> harus lewat endpoint
    // ber-autentikasi, bukan URL publik.
    Route::get('foto/{id}', [SiswaController::class, 'foto']);
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
