<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MapelController;

Route::get('/', function () {
    return app()->version();
});

Route::prefix('mapel')->group(function () {
    Route::get('all', [MapelController::class, 'index']);
    Route::get('/', [MapelController::class, 'show']);
    Route::post('/', [MapelController::class, 'store']);
    Route::patch('/', [MapelController::class, 'update']);
    Route::delete('/', [MapelController::class, 'destroy']);
});
