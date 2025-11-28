<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SensorData;
use App\Models\Prediction;
use Carbon\Carbon;

class SensorController extends Controller
{
    /**
     * Endpoint API untuk menerima data dari ESP32.
     * Method: POST
     */
    public function store(Request $request)
    {
        // 1. Validasi Input
        $validated = $request->validate([
            'turbidity' => 'required|numeric',
            'distance'  => 'required|numeric',
            'water_level' => 'required|numeric', // 0-100
        ]);

        // 2. Tentukan Status Kekeruhan
        // Logika update: >1.57 Jernih, >1.5 Agak Keruh, <1.5 Keruh
        $voltage = $validated['turbidity'];
        $status = 'KERUH'; // Default

        if ($voltage > 1.57) {
            $status = 'JERNIH';
        } else if ($voltage > 1.5) {
            $status = 'AGAK KERUH';
        }

        // 3. Hitung Rate (Laju Perubahan Air)
        // REVISI: Kita hapus pengecekan "levelDiff > 0".
        // Sekarang kita izinkan hasil negatif untuk mendeteksi pengisian air.

        $latestData = SensorData::latest()->first();
        $depletionRate = 0;

        if ($latestData) {
            // Rumus: Level Lama - Level Baru
            // Hasil Positif (+) = Air Berkurang (Draining) -> Python pakai ML
            // Hasil Negatif (-) = Air Bertambah (Refilling) -> Python pakai Math
            $levelDiff = $latestData->water_level - $validated['water_level'];

            $lastTime = Carbon::parse($latestData->created_at);
            $now = Carbon::now();

            // Hitung selisih waktu (float jam)
            $hoursDiff = $lastTime->diffInHours($now, true);

            // Hitung rate asalkan ada selisih waktu yang masuk akal
            if ($hoursDiff > 0.001) {
                $depletionRate = $levelDiff / $hoursDiff;
            }
        }

        // 4. Simpan ke Database
        $sensor = SensorData::create([
            'turbidity'        => $validated['turbidity'],
            'distance'         => $validated['distance'],
            'water_level'      => $validated['water_level'],
            'turbidity_status' => $status,
            'depletion_rate'   => $depletionRate, // Bisa (+) atau (-)
            'timestamp'        => now(),
        ]);

        // 5. Ambil Prediksi TERAKHIR dari Database
        // Ini hasil hitungan Python Worker (bisa "Habis dlm..." atau "Penuh dlm...")
        $lastPrediction = Prediction::latest()->first();

        $predHours = 0;
        $predTimeMsg = "Menghitung...";

        if ($lastPrediction) {
            $predHours = $lastPrediction->predicted_hours;
            // Kita prioritaskan pesan teks yang sudah diformat oleh Python
            $predTimeMsg = $lastPrediction->time_remaining ?? "Menghitung...";
        }

        // 6. Return JSON ke ESP32
        return response()->json([
            'status'          => 'success',
            'message'         => 'Data recorded',
            'data_id'         => $sensor->id,
            // Data ini yang akan diambil ESP32 untuk ditampilkan di OLED
            'predicted_hours' => $predHours,
            'time'            => $predTimeMsg,
        ], 200);
    }
}
