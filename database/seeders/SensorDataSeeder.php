<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SensorDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // === KONFIGURASI TANGKI MINIATUR ===
        $maxHeight = 14; // cm
        $minHeight = 1;  // cm
        $effectiveHeight = $maxHeight - $minHeight; // 13 cm

        $data = [];
        // Kita buat data mundur 7 hari ke belakang
        $currentDate = Carbon::now()->subDays(7);
        $now = Carbon::now();

        // Status Awal
        $currentLevel = 100; // Penuh
        $lastLevel = 100;

        // Variabel untuk "State Machine" (Simulasi Kebiasaan)
        // Mode: 'IDLE' (Diam), 'DRAINING' (Keran Buka), 'REFILLING' (Isi Ulang)
        $mode = 'IDLE';
        $stepsRemaining = 0; // Berapa lama mode ini bertahan
        $currentRate = 0;    // Kecepatan air berkurang (% per 10 menit)

        while ($currentDate <= $now) {

            // 1. LOGIKA "STATE MACHINE" (Simulasi Keran)
            // Jika durasi aktivitas habis, tentukan aktivitas baru
            if ($stepsRemaining <= 0) {
                if ($currentLevel <= 10) {
                    // Jika air habis/kritis -> Wajib ISI ULANG
                    $mode = 'REFILLING';
                    $stepsRemaining = rand(3, 6); // Isi ulang selama 30-60 menit
                    $currentRate = rand(15, 30);  // Cepat naik
                } else {
                    // Jika air masih ada, acak kejadian berikutnya
                    $dice = rand(1, 100);

                    if ($dice <= 40) {
                        // 40% Kemungkinan: IDLE (Keran Tutup)
                        $mode = 'IDLE';
                        $stepsRemaining = rand(6, 24); // Diam selama 1-4 jam
                        $currentRate = 0;
                    } elseif ($dice <= 80) {
                        // 40% Kemungkinan: KERAN DIBUKA (Normal Usage)
                        $mode = 'DRAINING';
                        $stepsRemaining = rand(6, 18); // Nyala 1-3 jam
                        // Rate: 1% sampai 5% per 10 menit (konsisten)
                        $currentRate = rand(10, 50) / 10;
                    } else {
                        // 20% Kemungkinan: BOCOR HALUS (Small Leak)
                        $mode = 'DRAINING';
                        $stepsRemaining = rand(12, 48); // Bocor lama (2-8 jam)
                        // Rate: 0.1% sampai 0.5% per 10 menit (sangat pelan)
                        $currentRate = rand(1, 5) / 10;
                    }
                }
            }

            // 2. EKSEKUSI PERUBAHAN LEVEL
            if ($mode == 'REFILLING') {
                $currentLevel += $currentRate;
                if ($currentLevel > 100) $currentLevel = 100;
            } elseif ($mode == 'DRAINING') {
                // Tambahkan sedikit noise (0.01 - 0.05%) biar gak terlalu kaku kayak robot
                $noise = rand(0, 5) / 100;
                $realDrop = $currentRate + $noise;

                $currentLevel -= $realDrop;
                if ($currentLevel < 0) $currentLevel = 0;
            } else {
                // IDLE: Mungkin ada penguapan dikit banget
                $currentLevel -= 0.01;
                if ($currentLevel < 0) $currentLevel = 0;
            }

            $stepsRemaining--; // Kurangi durasi aktivitas

            // 3. HITUNG JARAK (Distance)
            // Rumus: MaxHeight - (Persentase * TinggiEfektif)
            $waterHeightCm = ($currentLevel / 100) * $effectiveHeight;
            $distance = $maxHeight - $waterHeightCm;

            // 4. SIMULASI TURBIDITY (Sesuai Request)
            // Kita buat agak acak tapi realistis
            $turbidityVoltage = rand(100, 300) / 100; // 1.00V - 3.00V
            $turbidityStatus = 'KERUH';

            if ($turbidityVoltage > 1.57) {
                $turbidityStatus = 'JERNIH';
            } else if ($turbidityVoltage > 1.5) {
                $turbidityStatus = 'AGAK KERUH';
            } else {
                $turbidityStatus = 'KERUH';
            }

            // 5. HITUNG DEPLETION RATE (TARGET PREDIKSI ML)
            // Rate = (% Lama - % Baru) / Selisih Jam
            // Interval loop kita adalah 10 menit = 0.1666 jam
            $hoursDiff = 10 / 60;
            $depletionRate = 0;

            // Kita hanya hitung rate kalau air BERKURANG (Draining/Idle) dan TIDAK sedang isi ulang
            if ($mode != 'REFILLING' && $lastLevel > $currentLevel) {
                $diff = $lastLevel - $currentLevel;
                // Rate = Persen per Jam
                $depletionRate = $diff / $hoursDiff;
            }

            // 6. MASUKKAN KE ARRAY
            $data[] = [
                'turbidity' => $turbidityVoltage,
                'distance' => round($distance, 2),
                'water_level' => round($currentLevel, 2),
                'turbidity_status' => $turbidityStatus,
                'depletion_rate' => round($depletionRate, 2), // Ini yang akan dipelajari Python
                'timestamp' => $currentDate->format('Y-m-d H:i:s'),
                'created_at' => $currentDate->format('Y-m-d H:i:s'),
                'updated_at' => $currentDate->format('Y-m-d H:i:s'),
            ];

            // Update Loop
            $lastLevel = $currentLevel;
            $currentDate->addMinutes(10);

            // Batch Insert biar cepat (tiap 500 data)
            if (count($data) >= 500) {
                DB::table('sensor_data')->insert($data);
                $data = [];
            }
        }

        // Insert sisa data
        if (!empty($data)) {
            DB::table('sensor_data')->insert($data);
        }
    }
}
