<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Oauth\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserManagementController;

// Login publik (throttle 5x/menit)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Register & manajemen user — hanya SuperAdmin dan Admin
Route::middleware(['auth:api', 'force.pwd', 'check.role:SuperAdmin,Admin'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/users', [UserManagementController::class, 'index']);
    Route::get('/users/{id}', [UserManagementController::class, 'show']);
    Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);
});

// force.pwd membolehkan /user, /logout, /logout-all, /password meski flag aktif
Route::middleware(['auth:api', 'force.pwd'])->group(function () {
    Route::get('/user', [UserController::class, 'index']);
    Route::post('/logout', [AuthController::class, 'logout']);
    // Cabut semua sesi aktif di semua device sekaligus
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    // Tukar token yang masih valid dengan token baru (perpanjang sesi)
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:5,1');
    // Semua user yang sudah login bisa ganti password sendiri
    // Throttle: cegah brute-force current_password oleh pemegang token curian
    Route::post('/password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1');
});

// Reset password user lain — hanya SuperAdmin dan Admin
Route::middleware(['auth:api', 'force.pwd', 'check.role:SuperAdmin,Admin'])->group(function () {
    Route::post('/users/{id}/password', [UserManagementController::class, 'resetPassword']);
});
