<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SensorData;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistoryController extends Controller
{
    public function index()
    {
        // Ambil data sensor, urutkan dari yang terbaru
        // Eager load 'prediction' agar hemat query database
        // Paginate 10 data per halaman
        $data = SensorData::with('prediction')
                    ->latest() // alias dari orderBy('created_at', 'desc')
                    ->paginate(10);

        return view('history', compact('data'));
    }

    /**
     * Export seluruh riwayat data sensor ke CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        $fileName = 'riwayat_sensor_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');

            // Tulis header kolom
            fputcsv($handle, [
                'timestamp',
                'water_level_percent',
                'distance_cm',
                'turbidity_voltage',
                'turbidity_status',
                'depletion_rate_percent_per_hour',
                'prediction_method',
                'prediction_time_remaining_or_hours',
            ]);

            // Stream data dalam chunk agar hemat memori
            SensorData::with('prediction')
                ->orderBy('created_at', 'desc')
                ->chunk(500, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            optional($row->created_at)->toDateTimeString(),
                            $row->water_level,
                            $row->distance,
                            $row->turbidity,
                            $row->turbidity_status,
                            $row->depletion_rate,
                            optional($row->prediction)->predicted_method,
                            $row->prediction
                                ? ($row->prediction->time_remaining ?? $row->prediction->predicted_hours)
                                : null,
                        ]);
                    }
                });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
