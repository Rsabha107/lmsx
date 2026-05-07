<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds override tracking fields for privileged checkpoint state changes.
     */
    public function up(): void
    {
        Schema::table('job_checkpoints', function (Blueprint $table) {
            // Override tracking
            $table->boolean('was_overridden')->default(false)->after('state');
            $table->string('override_reason')->nullable()->after('was_overridden');
            $table->text('override_notes')->nullable()->after('override_reason');
            $table->foreignId('overridden_by')->nullable()->after('override_notes')
                ->constrained('users')->onDelete('set null');
            $table->timestamp('overridden_at')->nullable()->after('overridden_by');
            $table->time('override_actual_time')->nullable()->after('overridden_at');
            
            // Add index for analytics queries
            $table->index(['was_overridden', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_checkpoints', function (Blueprint $table) {
            // Drop index first
            $table->dropIndex(['was_overridden', 'completed_at']);
            
            // Drop foreign key
            $table->dropForeign(['overridden_by']);
            
            // Drop columns
            $table->dropColumn([
                'was_overridden',
                'override_reason',
                'override_notes',
                'overridden_by',
                'overridden_at',
                'override_actual_time',
            ]);
        });
    }
};
