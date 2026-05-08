<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_checkpoints', function (Blueprint $table) {
            $table->integer('bags_loaded')->default(0)->after('notes');
            $table->integer('oversized_pieces')->default(0)->after('bags_loaded');
        });
    }

    public function down(): void
    {
        Schema::table('job_checkpoints', function (Blueprint $table) {
            $table->dropColumn(['bags_loaded', 'oversized_pieces']);
        });
    }
};
