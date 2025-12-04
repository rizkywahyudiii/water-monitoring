<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_evaluation', function (Blueprint $table) {
            $table->string('model_name', 50)->default('random_forest')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('model_evaluation', function (Blueprint $table) {
            $table->dropColumn('model_name');
        });
    }
};



