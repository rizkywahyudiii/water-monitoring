<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BacktestResult extends Model
{
    use HasFactory;

    protected $table = 'backtest_results';

    protected $guarded = [];

    protected $casts = [
        'mean_error' => 'float',
        'overall_accuracy' => 'float',
        'timestamp' => 'datetime',
    ];
}
