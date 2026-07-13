<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KaryawanController;

Route::prefix('karyawan')->group(function () {
    Route::get('all', [KaryawanController::class, 'index']);
    Route::get('lookup', [KaryawanController::class, 'lookupByEmail']);
    Route::get('lookup-kartu', [KaryawanController::class, 'lookupByKartu']);
    Route::get('/', [KaryawanController::class, 'show']);
    Route::post('/', [KaryawanController::class, 'store']);
    Route::post('update', [KaryawanController::class, 'update']);
    Route::delete('/{id}', [KaryawanController::class, 'destroy']);
});
