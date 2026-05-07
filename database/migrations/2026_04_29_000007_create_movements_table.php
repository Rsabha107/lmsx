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
        Schema::create('movements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., 'M-2026-001'
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('restrict');
            $table->foreignId('checkpoint_template_id')->nullable()->constrained()->onDelete('set null'); // Which checkpoint sequence to use
            
            $table->enum('kind', ['arrival', 'departure', 'transfer', 'training', 'match'])->default('transfer');
            $table->string('from_location');
            $table->string('to_location');
            
            // Timing
            $table->dateTime('scheduled_departure');
            $table->dateTime('scheduled_arrival');
            $table->dateTime('actual_departure')->nullable();
            $table->dateTime('actual_arrival')->nullable();
            
            // Assignment
            $table->foreignId('vehicle_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('driver_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('passengers')->default(0);
            
            // Status
            $table->enum('status', ['scheduled', 'in-progress', 'completed', 'delayed', 'cancelled'])->default('scheduled');
            $table->integer('delay_minutes')->nullable();
            $table->enum('source', ['manual', 'live-feed', 'template'])->default('manual');
            $table->string('flight_number')->nullable(); // If linked to flight
            
            // Job generation
            $table->string('job_id')->nullable()->unique(); // e.g., 'JOB-2401' - once generated
            $table->timestamp('job_generated_at')->nullable();
            
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['plan_id', 'team_id']);
            $table->index(['scheduled_departure', 'status']);
            $table->index('job_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movements');
    }
};
