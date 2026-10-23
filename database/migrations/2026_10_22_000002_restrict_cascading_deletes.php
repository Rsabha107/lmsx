<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deletes go through the recycle bin now, so the database must never wipe plans, movements,
 * jobs or their links behind the app's back. These foreign keys used to cascade (or null the
 * template link); they now refuse, and the purge removes children explicitly, child first.
 */
return new class extends Migration
{
    /** table => [column => [referenced table, old rule]] */
    private const KEYS = [
        'movements' => ['plan_id' => ['plans', 'cascade'], 'event_id' => ['events', 'cascade']],
        'jobs_operations' => ['movement_id' => ['movements', 'cascade'], 'plan_id' => ['plans', 'cascade'], 'event_id' => ['events', 'cascade']],
        'job_checkpoints' => ['event_id' => ['events', 'cascade']],
        'plans' => ['movement_template_id' => ['movement_templates', 'set null'], 'event_id' => ['events', 'cascade']],
        'movement_templates' => ['event_id' => ['events', 'cascade']],
    ];

    public function up(): void
    {
        $this->apply(fn () => 'restrict');
    }

    public function down(): void
    {
        $this->apply(fn (string $old) => $old);
    }

    private function apply(callable $rule): void
    {
        foreach (self::KEYS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $rule) {
                foreach ($columns as $column => [$references, $old]) {
                    $table->dropForeign([$column]);
                    $table->foreign($column)->references('id')->on($references)->onDelete($rule($old));
                }
            });
        }
    }
};
