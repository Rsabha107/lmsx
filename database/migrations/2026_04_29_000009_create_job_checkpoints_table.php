<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Job checkpoints are SNAPSHOTS from checkpoint templates at the time of job generation.
     * This ensures historical integrity - changing a template doesn't affect existing jobs.
     */
    public function up(): void
    {
        Schema::create('job_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs_operations', 'id')->onDelete('cascade');
            $table->foreignId('checkpoint_id')->nullable()->constrained()->onDelete('set null'); // Reference to library (optional)
            
            $table->integer('order')->default(0);
            $table->string('name'); // Snapshotted name
            $table->string('type')->default('manual'); // Snapshotted type
            $table->enum('state', ['pending', 'active', 'done', 'skipped', 'failed'])->default('pending');
            
            // Completion tracking
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('completion_method')->nullable(); // 'mobile', 'web', 'auto', 'gps'
            
            // Evidence
            $table->string('photo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->decimal('gps_latitude', 10, 8)->nullable(); // e.g., 48.85661400
            $table->decimal('gps_longitude', 11, 8)->nullable(); // e.g., 2.35222190
            $table->text('notes')->nullable();
            
            $table->timestamps();

            $table->index(['job_id', 'order']);
            $table->index(['job_id', 'state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_checkpoints');
    }
};
