<?php

use App\Http\Controllers\Master\SiswaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'force.pwd'])->prefix(config('gateway.siswa_prefix'))->group(function(){
    // Direktori siswa — dibuka untuk Siswa (versi publik), ditutup untuk
    // karyawan biasa: satpam/kebersihan tidak berkepentingan atas data siswa,
    // dan modulnya pun tidak ditampilkan di app. Administrator Sekolah tetap
    // masuk lewat pseudo-role AdminSekolah.
    Route::get('all', [SiswaController::class, 'index'])->middleware('check.role:SuperAdmin,Admin,AdminSekolah,Guru,Siswa');
    // Profil diri sendiri (termasuk foto) — satu-satunya jalur Siswa ke datanya
    // sendiri; idSiswa diresolve dari email token, bukan dari input klien.
    Route::get('saya', [SiswaController::class, 'saya'])->middleware('check.role:Siswa');
    // Detail berisi data pribadi (alamat, kontak orang tua) — Siswa boleh
    // membukanya tapi menerima proyeksi publik saja (lihat SiswaController::show).
    Route::get('/', [SiswaController::class, 'show'])->middleware('check.role:SuperAdmin,Admin,AdminSekolah,Guru,Siswa');
    Route::post('/', [SiswaController::class, 'store'])->middleware('check.role:SuperAdmin,Admin,AdminSekolah');
    Route::post('update', [SiswaController::class, 'update'])->middleware('check.role:SuperAdmin,Admin,AdminSekolah');
    // Kartu absensi — SuperAdmin/Admin/Administrator Sekolah
    Route::post('kartu/terbitkan', [SiswaController::class, 'terbitkanKartu'])->middleware('check.role:SuperAdmin,Admin,AdminSekolah');
    Route::post('kartu/blokir', [SiswaController::class, 'blokirKartu'])->middleware('check.role:SuperAdmin,Admin,AdminSekolah');
    Route::delete('/{id}', [SiswaController::class, 'destroy'])->middleware('check.role:SuperAdmin,Admin,AdminSekolah');
});
