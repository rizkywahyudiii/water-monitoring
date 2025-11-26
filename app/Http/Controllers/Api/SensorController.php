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
     * Menyimpan data sensor dari ESP32 dan mengembalikan prediksi terakhir.
     */
    public function store(Request $request)
    {
        // 1. Validasi Input
        // Memastikan data yang dikirim ESP32 lengkap dan berupa angka
        $validated = $request->validate([
            'turbidity' => 'required|numeric',
            'distance'  => 'required|numeric',
            'water_level' => 'required|numeric', // ESP32 mengirim 0-100
        ]);

        // 2. Tentukan Status Kekeruhan
        // Logika sederhana berdasarkan tegangan sensor (sesuai kode .ino kamu)
        $voltage = $validated['turbidity'];
        $status = 'KERUH'; // Default

        if ($voltage > 1.58) {
            $status = 'JERNIH';
        } else if ($voltage > 1.5) {
            $status = 'AGAK KERUH';
        }

        // 3. Hitung Depletion Rate (Laju Pengurangan Air)
        // Ini PENTING: Python butuh data ini untuk training model LightGBM.
        // Rumus: (Level Lama - Level Baru) / Selisih Waktu (Jam)

        $latestData = SensorData::orderBy('id', 'desc')->first();
        $depletionRate = 0; // Default 0 jika data pertama atau air sedang diisi

        if ($latestData) {
            $levelDiff = $latestData->water_level - $validated['water_level'];

            // Hitung selisih waktu dalam jam menggunakan Carbon
            // Parse timestamp manual dari database jika perlu, atau gunakan created_at
            $lastTime = Carbon::parse($latestData->created_at);
            $now = Carbon::now();

            // diffInHours(..., true) mengembalikan float (misal 0.05 jam), bukan pembulatan
            $hoursDiff = $lastTime->diffInHours($now, true);

            // Kita hanya hitung rate jika:
            // a. Ada selisih waktu yang masuk akal (> 0.001 jam)
            // b. Level air BERKURANG (positif). Jika negatif artinya air sedang diisi.
            if ($hoursDiff > 0.001 && $levelDiff > 0) {
                // Rate = % per jam
                $depletionRate = $levelDiff / $hoursDiff;

                // Opsional: Batasi rate agar tidak gila (misal max 500%/jam) untuk membuang noise
                if ($depletionRate > 500) $depletionRate = 0;
            }
        }

        // 4. Simpan ke Database
        $sensor = SensorData::create([
            'turbidity'        => $validated['turbidity'],
            'distance'         => $validated['distance'],
            'water_level'      => $validated['water_level'],
            'turbidity_status' => $status,
            'depletion_rate'   => $depletionRate,
            // Timestamp otomatis diisi Laravel (created_at),
            // tapi kita juga isi kolom 'timestamp' manual agar kompatibel dgn script Python lama
            'timestamp'        => now(),
        ]);

        // 5. Ambil Prediksi TERAKHIR dari Worker Python
        // Kita tidak menunggu Python menghitung sekarang (async), tapi mengambil
        // hasil hitungan terakhir yang sudah tersimpan di tabel predictions.
        $lastPrediction = Prediction::latest()->first();

        // Siapkan nilai default jika belum ada prediksi sama sekali
        $predHours = 0;
        $predTimeMsg = "Menghitung...";

        if ($lastPrediction) {
            $predHours = $lastPrediction->predicted_hours;
            // Gunakan pesan waktu dari DB jika ada, atau buat sendiri
            $predTimeMsg = $lastPrediction->time_remaining ?? $this->formatHours($predHours);
        }

        // 6. Return JSON ke ESP32
        // Format ini harus sesuai dengan yang diharapkan oleh kode Arduino kamu
        return response()->json([
            'status'          => 'success',
            'message'         => 'Data recorded successfully',
            'data_id'         => $sensor->id,
            // Data penting untuk ditampilkan di OLED ESP32:
            'predicted_hours' => $predHours,
            'time'            => $predTimeMsg,
        ], 200);
    }

    /**
     * Helper kecil untuk memformat jam ke teks manusia (backup jika DB kosong)
     */
    private function formatHours($hours)
    {
        if ($hours < 1) {
            return round($hours * 60) . " menit";
        }
        $h = floor($hours);
        $m = round(($hours - $h) * 60);
        return "{$h} jam {$m} menit";
    }
}
