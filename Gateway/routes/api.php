<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Oauth\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserManagementController;

// Login publik (throttle 5x/menit)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Register & manajemen user — hanya SuperAdmin dan Admin
Route::middleware(['auth:api', 'check.role:SuperAdmin,Admin'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/users', [UserManagementController::class, 'index']);
    Route::get('/users/{id}', [UserManagementController::class, 'show']);
    Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);
});

Route::middleware('auth:api')->group(function () {
    Route::get('/user', [UserController::class, 'index']);
    Route::post('/logout', [AuthController::class, 'logout']);
    // Semua user yang sudah login bisa ganti password sendiri
    Route::post('/password', [AuthController::class, 'changePassword']);
});

// Reset password user lain — hanya SuperAdmin dan Admin
Route::middleware(['auth:api', 'check.role:SuperAdmin,Admin'])->group(function () {
    Route::post('/users/{id}/password', [UserManagementController::class, 'resetPassword']);
});
