<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SensorData;
use App\Models\Prediction;
use App\Models\ModelEvaluation;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * 1. Menampilkan Halaman Dashboard Utama (View)
     */
    public function index()
    {
        $latest = SensorData::latest()->first();
        return view('dashboard', compact('latest'));
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
}
