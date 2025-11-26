<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SensorController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Endpoint untuk ESP32
// URL nanti: http://ip-kamu:8000/api/sensor
Route::post('/sensor', [SensorController::class, 'store']);

// Test endpoint (buka di browser http://localhost:8000/api/test buat cek)
Route::get('/test', function() {
    return response()->json(['status' => 'API Water Monitoring Ready!']);
});
