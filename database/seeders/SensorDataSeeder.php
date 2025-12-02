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
        // Hapus data lama
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('predictions')->truncate();
        DB::table('sensor_data')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

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

        while ($currentDate <= $now) {
            // Pola penggunaan yang lebih "nyata" berbasis jam
            $hour = (int) $currentDate->format('H');

            // Default: sedikit penguapan
            $delta = -0.05; // -0.05% per 10 menit

            if ($hour >= 5 && $hour <= 8) {
                // Pagi hari: pemakaian sedang
                $delta = -rand(5, 15) / 10; // -0.5% s/d -1.5% per 10 menit
            } elseif ($hour >= 17 && $hour <= 21) {
                // Sore/malam: pemakaian tinggi (mandi, masak, dsb)
                $delta = -rand(15, 35) / 10; // -1.5% s/d -3.5% per 10 menit
            } elseif ($hour >= 0 && $hour <= 4) {
                // Tengah malam: hampir tidak dipakai
                $delta = -rand(0, 3) / 10; // 0 s/d -0.3% per 10 menit
            }

            // Isi ulang otomatis jika level terlalu rendah
            if ($currentLevel <= 10) {
                // Simulasi pompa isi ulang: naik cukup cepat
                $delta = rand(30, 60) / 10; // +3% s/d +6% per 10 menit
            }

            // Tambahkan noise kecil agar tidak terlalu kaku
            $noise = rand(-3, 3) / 100; // -0.03 s/d +0.03%
            $currentLevel += $delta + $noise;
            if ($currentLevel > 100) $currentLevel = 100;
            if ($currentLevel < 0) $currentLevel = 0;

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

            // 5. HITUNG DEPLETION RATE (TETAP DISIMPAN, TAPI SEKARANG TURUNANNYA JELAS)
            // Rate = (% Lama - % Baru) / Selisih Jam (10 menit = 0.1666 jam)
            $hoursDiff = 10 / 60;
            $depletionRate = 0;
            if ($lastLevel != $currentLevel) {
                $diff = $lastLevel - $currentLevel;
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
