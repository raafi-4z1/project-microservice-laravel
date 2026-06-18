<?php

use App\Http\Controllers\Master\SiswaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix(config('gateway.siswa_prefix'))->group(function(){
    Route::get('all', [SiswaController::class, 'index']);
    Route::get('/', [SiswaController::class, 'show']);
    Route::post('/', [SiswaController::class, 'store'])->middleware('check.role:SuperAdmin,Admin');
    Route::post('update', [SiswaController::class, 'update'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('/{id}', [SiswaController::class, 'destroy'])->middleware('check.role:SuperAdmin,Admin');
});
