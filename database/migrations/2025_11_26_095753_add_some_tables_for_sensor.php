<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel System Logs (Opsional, tapi kita buatkan sesuai request)
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_type', 50);
            $table->text('message');
            $table->json('details')->nullable();
            // Kita pakai timestamp bawaan Laravel (created_at) sebagai ganti kolom timestamp manual
            $table->timestamp('created_at')->useCurrent();
        });

        // 2. Tabel Sensor Data (PENTING: Input ESP32)
        Schema::create('sensor_data', function (Blueprint $table) {
            $table->id();
            $table->float('turbidity');
            $table->float('distance');
            $table->float('water_level'); // float sesuai schema lama
            $table->float('depletion_rate')->nullable()->default(0);
            $table->string('turbidity_status', 20)->nullable();

            // Kolom timestamp manual untuk kompatibilitas script Python
            // Python query: SELECT ... timestamp FROM sensor_data
            $table->timestamp('timestamp')->useCurrent();

            $table->timestamps(); // create_at & updated_at bawaan Laravel
        });

        // 3. Tabel Predictions (PENTING: Output Python)
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            // Relasi ke sensor_data
            $table->unsignedBigInteger('sensor_data_id');
            $table->foreign('sensor_data_id')->references('id')->on('sensor_data')->onDelete('cascade');

            $table->float('predicted_hours');
            $table->string('predicted_method', 50);
            $table->float('current_level');
            $table->float('predicted_rate')->nullable();
            // Tambahan kolom helper untuk display UI (opsional tapi berguna)
            $table->string('time_remaining')->nullable();

            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();
        });

        // 4. Tabel Evaluasi Model (PENTING: Log Training ML)
        Schema::create('model_evaluation', function (Blueprint $table) {
            $table->id();
            $table->float('mae');
            $table->float('rmse');
            $table->float('r2_score');
            $table->integer('training_samples');
            $table->float('training_time');

            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();
        });

        // 5. Tabel Backtest (Legacy/Opsional)
        Schema::create('backtest_results', function (Blueprint $table) {
            $table->id();
            $table->integer('total_tests');
            $table->float('mean_error');
            $table->float('median_error');
            $table->float('min_error');
            $table->float('max_error');
            $table->float('overall_accuracy');

            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backtest_results');
        Schema::dropIfExists('model_evaluation');
        Schema::dropIfExists('predictions');
        Schema::dropIfExists('sensor_data');
        Schema::dropIfExists('system_logs');
    }
};
