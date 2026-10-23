<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recycle bin: jobs, movement templates and events become soft-deletable like plans and
 * movements, and every delete is one `deletions` batch (who asked, who approved, what went),
 * so it can be approved, restored or purged as a unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deletions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            // jobs | plans | movements | movement_templates | event
            $table->string('kind', 30);
            // pending | rejected | withdrawn | expired | done | partially_restored | restored | purged
            $table->string('status', 20)->index();
            // Why it needed a second admin: threshold | in_use | event | brake (null: ran at once).
            $table->string('trigger', 20)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_name')->nullable();
            $table->text('reason')->nullable();
            $table->text('decision_note')->nullable();
            // What was asked for (ids of the kind), and what was actually deleted (ids per table).
            $table->json('request');
            $table->json('items')->nullable();
            $table->json('summary')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('purge_after')->nullable()->index();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('restored_at')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
            $table->index(['requested_by', 'created_at']);
        });

        foreach (['jobs_operations', 'movement_templates', 'events'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        foreach (['jobs_operations', 'movement_templates', 'events', 'plans', 'movements'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('deletion_id')->nullable()->constrained('deletions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['jobs_operations', 'movement_templates', 'events', 'plans', 'movements'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('deletion_id');
            });
        }

        foreach (['jobs_operations', 'movement_templates', 'events'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        Schema::dropIfExists('deletions');
    }
};
