<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    use HasFactory;

    protected $table = 'system_logs';

    protected $fillable = [
        'log_type',
        'message',
        'details',
    ];

    // Otomatis ubah kolom JSON 'details' menjadi Array PHP saat diambil
    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];
}
