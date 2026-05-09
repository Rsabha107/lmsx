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
        Schema::table('job_checkpoints', function (Blueprint $table) {
            $table->unsignedInteger('bags_count')->nullable()->after('completed_at');
            $table->unsignedInteger('oversized_pieces_count')->nullable()->after('bags_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_checkpoints', function (Blueprint $table) {
            $table->dropColumn(['bags_count', 'oversized_pieces_count']);
        });
    }
};
