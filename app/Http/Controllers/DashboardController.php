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
        // Kita kirim data awal biar pas loading gak kosong melompong
        $latest = SensorData::latest()->first();
        return view('dashboard', compact('latest'));
    }

    /**
     * 2. Pengganti 'dashboard_data.php'
     * API Internal untuk update angka-angka realtime via AJAX
     */
    public function getStats()
    {
        $latest = SensorData::latest()->first();
        $prediction = Prediction::latest()->first();

        // Hitung status sederhana untuk UI
        $statusAir = 'Aman';
        if ($latest && $latest->water_level < 20) $statusAir = 'KRITIS';
        elseif ($latest && $latest->water_level < 50) $statusAir = 'Waspada';

        return response()->json([
            'water_level' => $latest->water_level ?? 0,
            'distance'    => $latest->distance ?? 0,
            'turbidity'   => $latest->turbidity ?? 0,
            'status_air'  => $statusAir,
            'status_keruh'=> $latest->turbidity_status ?? 'Unknown',
            'prediksi_jam'=> $prediction->predicted_hours ?? 0,
            'waktu_habis' => $prediction->time_remaining ?? '-',
            'updated_at'  => $latest ? $latest->created_at->diffForHumans() : '-',
        ]);
    }

    /**
     * 3. Pengganti 'get_prediction.php'
     * API Internal untuk data Grafik Line Chart (Level Air vs Prediksi)
     */
    public function getChartData()
    {
        // Ambil 50 data terakhir untuk grafik
        $data = SensorData::with('prediction')
                ->latest()
                ->take(50)
                ->get()
                ->sortBy('id') // Urutkan biar grafik dari kiri ke kanan (lama ke baru)
                ->values(); // Reset array keys

        // Format data agar mudah dibaca Chart.js / ApexCharts
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
     * 4. Pengganti 'get_metric.php'
     * API Internal untuk menampilkan performa ML (RMSE, R2 Score)
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
