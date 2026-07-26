<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('training_ground')->nullable();
            $table->dateTime('training_start_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Carry over existing training data from team_stays before it's
        // dropped from that table.
        $rows = DB::table('team_stays')
            ->whereNotNull('training_ground')
            ->orWhereNotNull('training_start_at')
            ->get(['event_id', 'team_id', 'training_ground', 'training_start_at']);

        foreach ($rows as $row) {
            DB::table('team_trainings')->insert([
                'event_id' => $row->event_id,
                'team_id' => $row->team_id,
                'training_ground' => $row->training_ground,
                'training_start_at' => $row->training_start_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_trainings');
    }
};
