<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->double('r_squared')->nullable()->after('current_level');
        });
    }

    public function down()
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['r_squared']);
        });
    }
};
