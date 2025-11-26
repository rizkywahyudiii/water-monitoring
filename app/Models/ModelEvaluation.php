<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelEvaluation extends Model
{
    use HasFactory;

    /**
     * Nama tabel di database.
     */
    protected $table = 'model_evaluation';

    /**
     * Izinkan semua kolom diisi (Mass Assignment).
     */
    protected $guarded = [];

    /**
     * Casting tipe data.
     * Pastikan angka desimal presisi tetap terbaca sebagai float.
     */
    protected $casts = [
        'mae'             => 'float',
        'rmse'            => 'float',
        'r2_score'        => 'float',
        'training_time'   => 'float',
        'timestamp'       => 'datetime',
    ];
}
