<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    use HasFactory;

    /**
     * Nama tabel di database.
     * Sesuai dengan migrasi yang kita buat sebelumnya.
     */
    protected $table = 'predictions';

    /**
     * Guarded kosong artinya semua kolom BOLEH diisi (Mass Assignment).
     * Kita pakai ini biar praktis karena data diisi oleh mesin (Python),
     * jadi kita percaya saja semua inputnya valid.
     */
    protected $guarded = [];

    /**
     * Casting tipe data.
     * Mengubah format data dari database menjadi tipe data PHP yang sesuai.
     * 'predicted_hours' dan 'predicted_rate' penting jadi float untuk perhitungan.
     */
    protected $casts = [
        'predicted_hours' => 'float',
        'current_level'   => 'float',
        'predicted_rate'  => 'float',
        'timestamp'       => 'datetime',
    ];

    /**
     * Relasi ke tabel sensor_data.
     * Setiap prediksi pasti milik satu data sensor tertentu (Belongs To).
     */
    public function sensorData()
    {
        return $this->belongsTo(SensorData::class, 'sensor_data_id');
    }
}
