<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds enhanced tracking, timing, and validation fields to job_checkpoints table.
     */
    public function up(): void
    {
        Schema::table('job_checkpoints', function (Blueprint $table) {
            // Snapshot fields from template (for historical integrity)
            $table->text('description')->nullable()->after('type');
            $table->boolean('requires_photo')->default(false)->after('description');
            $table->boolean('requires_signature')->default(false)->after('requires_photo');
            $table->boolean('is_required')->default(true)->after('requires_signature');
            $table->integer('estimated_minutes')->nullable()->after('is_required');
            
            // Timing & duration tracking
            $table->timestamp('scheduled_at')->nullable()->after('estimated_minutes');
            $table->timestamp('started_at')->nullable()->after('scheduled_at');
            $table->integer('actual_duration_seconds')->nullable()->after('completed_at');
            $table->boolean('is_on_time')->nullable()->after('actual_duration_seconds');
            $table->integer('delay_minutes')->nullable()->after('is_on_time');
            
            // Skip/exception handling
            $table->string('skip_reason')->nullable()->after('state');
            $table->foreignId('skipped_by')->nullable()->after('skip_reason')
                ->constrained('users')->onDelete('set null');
            $table->timestamp('skipped_at')->nullable()->after('skipped_by');
            $table->string('exception_type')->nullable()->after('skipped_at');
            
            // Enhanced location & evidence
            $table->string('location_name')->nullable()->after('gps_longitude');
            $table->integer('gps_accuracy_meters')->nullable()->after('location_name');
            $table->string('verification_code')->nullable()->after('gps_accuracy_meters');
            $table->foreignId('verified_by')->nullable()->after('verification_code')
                ->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            
            // Add indexes for common queries
            $table->index(['state', 'scheduled_at']);
            $table->index(['is_on_time']);
            $table->index(['skipped_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_checkpoints', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['state', 'scheduled_at']);
            $table->dropIndex(['is_on_time']);
            $table->dropIndex(['skipped_by']);
            
            // Drop foreign keys
            $table->dropForeign(['skipped_by']);
            $table->dropForeign(['verified_by']);
            
            // Drop columns
            $table->dropColumn([
                'description',
                'requires_photo',
                'requires_signature',
                'is_required',
                'estimated_minutes',
                'scheduled_at',
                'started_at',
                'actual_duration_seconds',
                'is_on_time',
                'delay_minutes',
                'skip_reason',
                'skipped_by',
                'skipped_at',
                'exception_type',
                'location_name',
                'gps_accuracy_meters',
                'verification_code',
                'verified_by',
                'verified_at',
            ]);
        });
    }
};
