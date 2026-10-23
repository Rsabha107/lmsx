<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hotel team schedule (Duty Roster → Hotel schedule): per team hotel and match day, the supervisor
 * and the kit runs to and from the venue; per team match, the numbered crew team; per day, the
 * crew teams at the Aspire VSA. Teams, hotels and times come from team stays and matches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_duty_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // Normalised hotel name (team_stays.hotel_name, trimmed and upper-cased).
            $table->string('hotel', 191);
            $table->date('date');
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('departure_crews')->nullable();
            $table->time('departure_time')->nullable();
            $table->json('arrival_crews')->nullable();
            $table->time('arrival_time')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'hotel', 'date']);
        });

        Schema::create('hotel_crew_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedSmallInteger('crew_team');
            $table->timestamps();
            $table->unique(['match_id', 'team_id']);
        });

        Schema::create('hotel_vsa_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->json('crews');
            $table->timestamps();
            $table->unique(['event_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_vsa_days');
        Schema::dropIfExists('hotel_crew_assignments');
        Schema::dropIfExists('hotel_duty_days');
    }
};
