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
        Schema::table('matches', function (Blueprint $table) {
            // Drop old columns
            $table->dropColumn(['event', 'venue']);
            
            // Add new foreign key columns
            $table->unsignedBigInteger('event_id')->nullable()->after('match_number');
            $table->unsignedBigInteger('venue_id')->nullable()->after('event_id');
            
            // Add foreign key constraints
            $table->foreign('event_id')->references('id')->on('events')->onDelete('set null');
            $table->foreign('venue_id')->references('id')->on('venues')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Drop foreign keys
            $table->dropForeign(['event_id']);
            $table->dropForeign(['venue_id']);
            
            // Drop new columns
            $table->dropColumn(['event_id', 'venue_id']);
            
            // Restore old columns
            $table->string('event')->nullable();
            $table->string('venue')->nullable();
        });
    }
};
