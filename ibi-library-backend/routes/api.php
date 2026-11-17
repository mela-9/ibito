<?php

use App\Http\Controllers\AntreanController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KoleksiBukuController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PeminjamanController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::get('/buku', [KoleksiBukuController::class, 'index']);
Route::get('/buku/{koleksiBuku}', [KoleksiBukuController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profil', [AuthController::class, 'updateProfile']);
    Route::post('/profil/minta-otp-sandi', [AuthController::class, 'requestPasswordOtp']);
    Route::post('/profil/ubah-sandi', [AuthController::class, 'changePasswordWithOtp']);

    Route::get('/peminjaman', [PeminjamanController::class, 'index']);
    Route::post('/peminjaman', [PeminjamanController::class, 'store']);
    Route::post('/peminjaman/{peminjaman}/kembali', [PeminjamanController::class, 'return']);
    Route::post('/aktivitas-baca', [PeminjamanController::class, 'logReading']);
    Route::get('/buku/{koleksiBuku}/antrean', [AntreanController::class, 'forBook']);
    Route::get('/antrean-saya', [AntreanController::class, 'mine']);
    Route::delete('/antrean/{antrean}', [AntreanController::class, 'destroy']);
    Route::get('/notifikasi', [NotifikasiController::class, 'index']);
    Route::patch('/notifikasi/{notifikasi}/baca', [NotifikasiController::class, 'read']);

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/buku', [KoleksiBukuController::class, 'store']);
        Route::put('/buku/{koleksiBuku}', [KoleksiBukuController::class, 'update']);
        Route::delete('/buku/{koleksiBuku}', [KoleksiBukuController::class, 'destroy']);
        Route::get('/pengguna/belum-verifikasi', [AuthController::class, 'pendingMembers']);
        Route::post('/pengguna/{pengguna}/verifikasi', [AuthController::class, 'verifyMember']);
        Route::get('/pustakawan/sirkulasi', [PeminjamanController::class, 'circulation']);
    });
});
