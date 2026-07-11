<?php

use App\Http\Controllers\Master\ClassController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'force.pwd'])->prefix(config('gateway.class_prefix'))->group(function(){
    Route::get('all', [ClassController::class, 'index']);
    Route::get('/', [ClassController::class, 'show']);
    Route::post('/', [ClassController::class, 'store'])->middleware('check.role:SuperAdmin,Admin');
    Route::post('update', [ClassController::class, 'update'])->middleware('check.role:SuperAdmin,Admin');
    Route::delete('/{id}', [ClassController::class, 'destroy'])->middleware('check.role:SuperAdmin,Admin');
});
