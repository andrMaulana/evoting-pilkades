<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VotingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PemilihController;
use App\Http\Controllers\KandidatController;

// Halaman utama (Kamera Scanner)
Route::get('/', [VotingController::class, 'index']);

// Jembatan API ke Controller
// throttle: maksimal 60 permintaan/menit per IP (cukup untuk satu kiosk, menyulitkan tebak-tebakan kode)
Route::post('/api/cek-pemilih', [VotingController::class, 'cekPemilih'])->middleware('throttle:60,1');
Route::post('/api/simpan-suara', [VotingController::class, 'simpanSuara'])->middleware('throttle:60,1');

// Rute untuk Login & Logout
Route::get('/login', [AuthController::class, 'index'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rute yang dikunci (Hanya bisa diakses kalau udah login)
Route::middleware('auth')->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');

    // Rute Halaman DPT
    Route::get('/admin/pemilih', [PemilihController::class, 'index'])->name('admin.pemilih');
    // Rute untuk proses upload & import CSV
    Route::post('/admin/pemilih/import', [PemilihController::class, 'import'])->name('admin.pemilih.import');
    // Rute untuk simpan data tambah manual
    Route::post('/admin/pemilih/store', [PemilihController::class, 'store'])->name('admin.pemilih.store');
    // Rute untuk cetak undangan massal (filter TPS / rentang No. Urut)
    Route::get('/admin/pemilih/cetak-massal', [PemilihController::class, 'cetakMassal'])->name('admin.pemilih.cetak-massal');
    // Rute untuk menerbitkan QR baru (kartu hilang)
    Route::post('/admin/pemilih/qr-baru/{nik}', [PemilihController::class, 'qrBaru'])->name('admin.pemilih.qr-baru');
    // Rute untuk cetak undangan per warga
    Route::get('/admin/pemilih/cetak/{nik}', [PemilihController::class, 'cetak'])->name('admin.pemilih.cetak');
    // Rute untuk proses update data pemilih
    Route::put('/admin/pemilih/update/{nik}', [PemilihController::class, 'update'])->name('admin.pemilih.update');


    // Rute CRUD Kandidat
    Route::get('/admin/kandidat', [KandidatController::class, 'index'])->name('admin.kandidat');
    Route::post('/admin/kandidat/store', [KandidatController::class, 'store'])->name('admin.kandidat.store');
    Route::put('/admin/kandidat/update/{id}', [KandidatController::class, 'update'])->name('admin.kandidat.update');
    Route::delete('/admin/kandidat/delete/{id}', [KandidatController::class, 'destroy'])->name('admin.kandidat.destroy');
});