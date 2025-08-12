<?php

use App\Http\Controllers\MapelController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix(config('gateway.mapel_prefix'))->group(function(){
    Route::get('all', [MapelController::class, 'index']);
    Route::get('/', [MapelController::class, 'show']);
    Route::post('/', [MapelController::class, 'store'])->middleware('check.role:SuperAdmin,Admin');
    Route::patch('/', [MapelController::class, 'update'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('/', [MapelController::class, 'destroy'])->middleware('check.role:SuperAdmin,Admin');
});
