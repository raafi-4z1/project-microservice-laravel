<?php

use App\Http\Controllers\ClassController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix(config('gateway.class_prefix'))->group(function(){
    Route::get('all', [ClassController::class, 'index']);
    Route::get('/', [ClassController::class, 'show']);
    Route::post('/', [ClassController::class, 'store'])->middleware('check.role:SuperAdmin,Admin');
    Route::patch('/', [ClassController::class, 'update'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('/', [ClassController::class, 'destroy'])->middleware('check.role:SuperAdmin,Admin');
});
