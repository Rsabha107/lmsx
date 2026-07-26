<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_stays', function (Blueprint $table) {
            $table->dropColumn(['training_ground', 'training_start_at']);
        });
    }

    public function down(): void
    {
        Schema::table('team_stays', function (Blueprint $table) {
            $table->string('training_ground')->nullable()->after('address');
            $table->dateTime('training_start_at')->nullable()->after('training_ground');
        });
    }
};
