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
        Schema::create('jobs_operations', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique(); // e.g., 'JOB-2401'
            $table->foreignId('movement_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->constrained()->onDelete('restrict');
            
            // Assignment
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('driver_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('vehicle_id')->nullable()->constrained()->onDelete('set null');
            
            // Status tracking
            $table->enum('status', ['pending', 'dispatched', 'in-progress', 'completed', 'cancelled'])->default('pending');
            $table->integer('checkpoints_completed')->default(0);
            $table->integer('checkpoints_total')->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0);
            
            // Timing
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'dispatched_at']);
            $table->index('movement_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs_operations');
    }
};
