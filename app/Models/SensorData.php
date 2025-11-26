<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SensorData extends Model
{
    use HasFactory;

    /**
     * Nama tabel di database.
     * Kita definisikan manual karena nama tabelnya 'sensor_data' (singular),
     * sedangkan default Laravel biasanya mencari yang plural ('sensor_datas').
     */
    protected $table = 'sensor_data';

    /**
     * Kolom yang boleh diisi secara massal (Mass Assignment).
     * Ini harus sesuai dengan kolom yang ada di database.
     */
    protected $fillable = [
        'turbidity',
        'distance',
        'water_level',
        'turbidity_status',
        'depletion_rate',
        'timestamp'
    ];

    /**
     * Casting tipe data.
     * Mengubah format data dari database menjadi tipe data PHP yang sesuai.
     * Contoh: 'turbidity' otomatis jadi float, 'timestamp' jadi object tanggal.
     */
    protected $casts = [
        'turbidity' => 'float',
        'distance' => 'float',
        'water_level' => 'float',
        'depletion_rate' => 'float',
        'timestamp' => 'datetime',
    ];

    /**
     * Relasi ke tabel predictions.
     * Satu baris data sensor memiliki satu hasil prediksi (One to One).
     */
    public function prediction()
    {
        return $this->hasOne(Prediction::class, 'sensor_data_id');
    }
}
