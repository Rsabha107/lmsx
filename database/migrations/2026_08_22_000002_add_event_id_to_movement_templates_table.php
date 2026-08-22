<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('movement_templates', function (Blueprint $table) {
            $table->foreignId('event_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('events')
                  ->onDelete('cascade');

            $table->index('event_id');
        });

        // Backfill existing templates to the single existing event, if any.
        $defaultEventId = DB::table('events')->orderBy('id')->value('id');
        if ($defaultEventId) {
            DB::table('movement_templates')->whereNull('event_id')->update(['event_id' => $defaultEventId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movement_templates', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropColumn('event_id');
        });
    }
};
