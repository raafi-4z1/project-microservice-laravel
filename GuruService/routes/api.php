<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GuruController;

// Route::get('/', function () {
//     return app()->version();
// });

Route::prefix('guru')->group(function () {
    Route::get('all', [GuruController::class, 'index']);
    Route::get('lookup', [GuruController::class, 'lookupByEmail']);
    Route::get('/', [GuruController::class, 'show']);
    Route::post('/', [GuruController::class, 'store']);
    Route::post('update', [GuruController::class, 'update']);
    Route::delete('/{id}', [GuruController::class, 'destroy']);
});
