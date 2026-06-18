<?php

use App\Http\Controllers\Master\MapelController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix(config('gateway.mapel_prefix'))->group(function(){
    Route::get('all', [MapelController::class, 'index']);
    Route::get('/', [MapelController::class, 'show']);
    Route::post('/', [MapelController::class, 'store'])->middleware('check.role:SuperAdmin,Admin');
    Route::post('update', [MapelController::class, 'update'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('/{id}', [MapelController::class, 'destroy'])->middleware('check.role:SuperAdmin,Admin');
});
