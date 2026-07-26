<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_stays', function (Blueprint $table) {
            $table->dateTime('training_start_at')->nullable()->after('training_ground');
        });
    }

    public function down(): void
    {
        Schema::table('team_stays', function (Blueprint $table) {
            $table->dropColumn('training_start_at');
        });
    }
};
