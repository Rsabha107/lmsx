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
        Schema::table('teams', function (Blueprint $table) {
            // Drop the old string columns
            $table->dropColumn(['origin_airport', 'destination_airport']);
        });

        Schema::table('teams', function (Blueprint $table) {
            // Add new foreign key columns
            $table->foreignId('origin_airport_id')->nullable()->after('training_ground')->constrained('airports')->onDelete('set null');
            $table->foreignId('destination_airport_id')->nullable()->after('origin_airport_id')->constrained('airports')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Drop foreign key columns
            $table->dropForeign(['origin_airport_id']);
            $table->dropForeign(['destination_airport_id']);
            $table->dropColumn(['origin_airport_id', 'destination_airport_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            // Restore string columns
            $table->string('origin_airport')->nullable()->after('training_ground');
            $table->string('destination_airport')->nullable()->after('origin_airport');
        });
    }
};
