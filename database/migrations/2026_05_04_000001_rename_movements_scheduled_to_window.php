<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->renameColumn('scheduled_departure', 'window_start');
            $table->renameColumn('scheduled_arrival', 'window_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->renameColumn('window_start', 'scheduled_departure');
            $table->renameColumn('window_end', 'scheduled_arrival');
        });
    }
};
