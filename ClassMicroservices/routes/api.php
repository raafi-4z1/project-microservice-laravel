<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RuangKelasController;

Route::get('/', function () {
    return app()->version();
});

Route::prefix('class')->group(function () {
    Route::get('all', [RuangKelasController::class, 'index']);
    Route::get('/', [RuangKelasController::class, 'show']);
    Route::post('/', [RuangKelasController::class, 'store']);
    Route::patch('/', [RuangKelasController::class, 'update']);
    Route::delete('/', [RuangKelasController::class, 'destroy']);
});
