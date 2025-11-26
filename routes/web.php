<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Grouping semua route yang butuh Login (Auth) & Verifikasi Email
Route::middleware(['auth', 'verified'])->group(function () {

    // 1. Halaman Dashboard Utama (Menggunakan Controller, bukan view static lagi)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 2. API Internal untuk AJAX (Data Realtime, Grafik, Metrik)
    // Ini nanti dipanggil oleh JavaScript di dashboard.blade.php
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats'])->name('dashboard.stats');
    Route::get('/dashboard/chart', [DashboardController::class, 'getChartData'])->name('dashboard.chart');
    Route::get('/dashboard/metrics', [DashboardController::class, 'getMetrics'])->name('dashboard.metrics');

    // 3. Profile Routes (Bawaan Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
