<?php

use App\Http\Controllers\AdminController;
use App\Services\BookingService;
use Illuminate\Support\Facades\Route;

// ------------------------------------------------------------------ Halaman pasien
Route::get('/', function (BookingService $booking) {
    return view('chat', [
        'hariIni' => $booking->hariIni(),
        'daftarDokter' => $booking->daftarDokter(),
        'geminiSiap' => (string) config('services.gemini.api_key') !== '',
    ]);
})->name('chat');

// ------------------------------------------------------------------ Back office
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/janji', [AdminController::class, 'janji'])->name('janji');
    Route::get('/janji/export', [AdminController::class, 'export'])->name('janji.export');
    Route::patch('/janji/{appointment}/status', [AdminController::class, 'ubahStatus'])->name('janji.status');
    Route::get('/dokter', [AdminController::class, 'dokter'])->name('dokter');
});
