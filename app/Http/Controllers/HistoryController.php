<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SensorData;
use Illuminate\Http\Request;

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
}
