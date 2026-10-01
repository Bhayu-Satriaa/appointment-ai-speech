<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\LiveController;
use Illuminate\Support\Facades\Route;

// Mode teks
Route::get('/dokter', [ChatController::class, 'dokter']);
Route::get('/jadwal', [ChatController::class, 'jadwal']);
Route::post('/chat', [ChatController::class, 'kirim']);

// Mode suara (Gemini Live API)
Route::get('/live/config', [LiveController::class, 'konfigurasi']);
Route::post('/live/token', [LiveController::class, 'token']);
Route::post('/tool', [LiveController::class, 'jalankanTool']);
