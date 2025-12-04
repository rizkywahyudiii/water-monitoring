<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SensorData;
use App\Models\Prediction;
use App\Models\ModelEvaluation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * 1. Menampilkan Halaman Dashboard Utama (View)
     */
    public function index()
    {
        $latest = SensorData::latest()->first();
        $usageInsights = $this->buildUsageInsights();

        return view('dashboard', [
            'latest'        => $latest,
            'usageInsights' => $usageInsights,
        ]);
    }

    /**
     * Halaman Pola Penggunaan (berbasis Random Forest & Linear Regression).
     *
     * Konsep:
     * - Random Forest dan Linear Regression di Python mempelajari pola 'next_water_level' dari fitur:
     *   jam, menit, level air, jarak, dan kekeruhan.
     * - Di sini kita tampilkan ringkasan data historis + evaluasi KEDUA model untuk perbandingan.
     */
    public function usagePatterns()
    {
        // Ambil beberapa sampel pola penggunaan terbaru (depletion_rate tidak null)
        $samples = SensorData::select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('MINUTE(created_at) as minute'),
                'water_level',
                'distance',
                'turbidity',
                'depletion_rate',
                'created_at'
            )
            ->whereNotNull('depletion_rate')
            ->latest()
            ->take(100)
            ->get();

        // Ambil evaluasi KEDUA model secara terpisah (untuk perbandingan)
        $rfEvaluation = ModelEvaluation::where('model_name', 'random_forest_next_level')
            ->latest()
            ->first();

        $lrEvaluation = ModelEvaluation::where('model_name', 'linear_regression_next_level')
            ->latest()
            ->first();

        $usageInsights = $this->buildUsageInsights();

        return view('usage_patterns', [
            'samples'       => $samples,
            'rfEvaluation'   => $rfEvaluation,
            'lrEvaluation'   => $lrEvaluation,
            'usageInsights'  => $usageInsights,
        ]);
    }

    /**
     * 2. API Internal untuk update angka-angka realtime via AJAX
     */
    public function getStats()
    {
        $latest = SensorData::latest()->first();
        $prediction = Prediction::latest()->first();

        // --- LOGIKA STATUS TANGKI (KRITIS/AMAN) ---
        $statusAir = 'Aman';
        if ($latest && $latest->water_level < 20) $statusAir = 'KRITIS';
        elseif ($latest && $latest->water_level < 50) $statusAir = 'Waspada';

        // --- LOGIKA ALIRAN AIR (MENGISI/BERKURANG) ---
        // Kita ambil dari depletion_rate.
        // Rate Positif = Berkurang, Rate Negatif = Nambah.
        $rate = $latest->depletion_rate ?? 0;
        $flowStatus = 'STABIL';

        // Threshold 0.5% agar noise sensor tidak dianggap perubahan
        if ($rate < -0.5) {
            $flowStatus = 'MENGISI';
        } elseif ($rate > 0.5) {
            $flowStatus = 'BERKURANG';
        }

        // Hitung selisih waktu untuk indikator koneksi
        $lastSeenSeconds = $latest ? $latest->created_at->diffInSeconds(now()) : 999999;

        return response()->json([
            'water_level' => $latest->water_level ?? 0,
            'distance'    => $latest->distance ?? 0,
            'turbidity'   => $latest->turbidity ?? 0,
            'status_air'  => $statusAir,
            'status_keruh'=> $latest->turbidity_status ?? 'Unknown',
            'prediksi_jam'=> $prediction->predicted_hours ?? 0,
            'waktu_habis' => $prediction->time_remaining ?? '-',
            'updated_at'  => $latest ? $latest->created_at->diffForHumans() : '-',
            'last_seen_seconds' => $lastSeenSeconds,
            // Data Baru untuk UI Dinamis
            'flow_status' => $flowStatus,
            'flow_rate'   => abs($rate) // Kita kirim angka mutlak biar gak ada minus di UI
        ]);
    }

    /**
     * 3. API Internal untuk data Grafik Line Chart
     */
    public function getChartData()
    {
        $data = SensorData::with('prediction')
                ->latest()
                ->take(50)
                ->get()
                ->sortBy('id')
                ->values();

        $labels = $data->pluck('created_at')->map(fn($date) => $date->format('H:i'));
        $levels = $data->pluck('water_level');
        $predictions = $data->map(fn($item) => $item->prediction->predicted_rate ?? 0);

        return response()->json([
            'labels' => $labels,
            'levels' => $levels,
            'rates'  => $predictions
        ]);
    }

    /**
     * 4. API Internal untuk performa ML
     */
    public function getMetrics()
    {
        $metric = ModelEvaluation::latest()->first();

        return response()->json([
            'rmse' => $metric->rmse ?? 0,
            'r2_score' => $metric->r2_score ?? 0,
            'mae' => $metric->mae ?? 0,
            'training_time' => $metric->training_time ?? 0,
            'last_trained' => $metric ? $metric->created_at->format('d M Y H:i') : '-',
        ]);
    }

    /**
     * 5. API Internal untuk grafik prediksi waktu habis (Linear Regression Trend)
     * Format: Sumbu X = waktu (history + prediksi), Sumbu Y = level air (%)
     */
    public function getTimePredictionChart()
    {
        // 1. Ambil Data Histori (6 jam terakhir, diambil per jam untuk tidak terlalu padat)
        $sixHoursAgo = now()->subHours(6);
        
        $historyData = SensorData::selectRaw('
                DATE_FORMAT(created_at, "%d/%m %H:00") as time_label,
                HOUR(created_at) as hour,
                DATE(created_at) as date,
                AVG(water_level) as level
            ')
            ->where('created_at', '>=', $sixHoursAgo)
            ->groupBy('date', 'hour')
            ->orderBy('created_at')
            ->get();

        // 2. Siapkan Data Prediksi
        $latest = SensorData::latest()->first();
        $latestPrediction = Prediction::where('predicted_hours', '>', 0)->latest()->first();

        // Default jika data kosong
        if (!$latest) {
            return response()->json([
                'labels' => [],
                'history' => [],
                'prediction' => [],
                'empty_time_formatted' => 'Data tidak cukup'
            ]);
        }

        $currentLevel = $latest->water_level;
        // Gunakan rate prediksi, atau fallback ke rate sensor terakhir jika valid
        $depletionRate = abs($latestPrediction->predicted_rate ?? ($latest->depletion_rate ?? 0));

        // Siapkan Array untuk Chart.js
        $labels = [];
        $historyPoints = [];
        $predictionPoints = [];

        // A. MASUKKAN DATA HISTORI KE ARRAY (Garis Biru Solid)
        foreach ($historyData as $data) {
            $labels[] = $data->time_label;
            $historyPoints[] = round($data->level, 1);
            $predictionPoints[] = null; // Bagian histori, prediksi null
        }

        // B. TITIK SAMBUNG (PENTING: Agar garis nyambung, titik "Sekarang" ada di kedua dataset)
        $nowLabel = now()->format('d/m H:i');
        
        // Tambahkan titik "Sekarang" sebagai titik akhir history dan awal prediksi
        $labels[] = "Sekarang";
        $historyPoints[] = $currentLevel; // Titik akhir history (biru solid)
        $predictionPoints[] = $currentLevel; // Titik awal prediksi (merah putus-putus)

        // C. GENERATE PREDIKSI MASA DEPAN (Garis Merah Putus-putus)
        $emptyTimeFormatted = 'Stabil';

        if ($depletionRate > 0.1) {
            $hoursUntilEmpty = $currentLevel / $depletionRate;
            $emptyTime = now()->addHours($hoursUntilEmpty);
            $emptyTimeFormatted = $emptyTime->format('d M, H:i');

            $maxHours = min(24, ceil($hoursUntilEmpty) + 2); // Batasi max 24 jam ke depan visualnya

            for ($h = 1; $h <= $maxHours; $h++) {
                $futureTime = now()->addHours($h);
                $timeLabel = $futureTime->format('d/m H:00');
                $predictedLevel = max(0, $currentLevel - ($depletionRate * $h));

                $labels[] = $timeLabel;
                $historyPoints[] = null; // Bagian masa depan, histori null
                $predictionPoints[] = round($predictedLevel, 1);

                if ($predictedLevel <= 0) break;
            }
        }

        return response()->json([
            'labels' => $labels,
            'history' => $historyPoints,         // Dataset 0: History (Biru Solid)
            'prediction' => $predictionPoints,   // Dataset 1: Prediction (Merah Putus-putus)
            'empty_time_formatted' => $emptyTimeFormatted
        ]);
    }

    /**
     * 6. API Internal untuk grafik pola penggunaan harian/jam (Random Forest Pattern)
     */
    public function getUsagePatternChart()
    {
        // Ambil data 7 hari terakhir, grup per jam
        $sevenDaysAgo = Carbon::now()->subDays(7);

        $hourlyData = SensorData::select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('AVG(GREATEST(depletion_rate, 0)) as avg_depletion'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', $sevenDaysAgo)
            ->whereNotNull('depletion_rate')
            ->where('depletion_rate', '>', 0) // Hanya yang benar-benar berkurang
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderBy('hour')
            ->get();

        $hours = [];
        $avgUsage = [];

        // Generate semua jam 0-23, isi dengan 0 jika tidak ada data
        for ($h = 0; $h < 24; $h++) {
            $hours[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $data = $hourlyData->firstWhere('hour', $h);
            $avgUsage[] = $data ? round($data->avg_depletion, 2) : 0;
        }

        return response()->json([
            'labels' => $hours,
            'avg_usage' => $avgUsage,
        ]);
    }

    /**
     * Utilitas: Bangun ringkasan pola waktu penggunaan air berdasarkan
     * rata-rata depletion_rate positif per jam dalam 7 hari terakhir.
     */
    protected function buildUsageInsights(): array
    {
        $days = 7;

        $rows = SensorData::selectRaw('HOUR(created_at) as hour, AVG(GREATEST(depletion_rate, 0)) as avg_usage')
            ->whereNotNull('depletion_rate')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderByDesc('avg_usage')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'summary' => 'Belum ada cukup data untuk menganalisis pola waktu penggunaan air.',
                'top_hours' => [],
                'days' => $days,
            ];
        }

        $top = $rows->take(3);

        $formatHour = function ($h) {
            $start = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $end = str_pad(($h + 1) % 24, 2, '0', STR_PAD_LEFT) . ':00';
            return "{$start}-{$end}";
        };

        $phrases = $top->map(function ($row) use ($formatHour) {
            return $formatHour((int) $row->hour);
        })->all();

        $summary = 'Belum ada cukup data.';
        if (count($phrases) === 1) {
            $summary = "Berdasarkan {$days} hari terakhir, air paling banyak digunakan pada rentang waktu {$phrases[0]}.";
        } elseif (count($phrases) === 2) {
            $summary = "Berdasarkan {$days} hari terakhir, air paling banyak digunakan pada rentang waktu {$phrases[0]} dan {$phrases[1]}.";
        } else {
            $summary = "Berdasarkan {$days} hari terakhir, air paling banyak digunakan pada rentang waktu {$phrases[0]}, {$phrases[1]}, dan {$phrases[2]}.";
        }

        return [
            'summary' => $summary,
            'top_hours' => $top,
            'days' => $days,
        ];
    }
}
