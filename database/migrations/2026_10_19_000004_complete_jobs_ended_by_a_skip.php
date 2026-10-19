<?php

use App\Models\JobOperation;
use App\Services\JobLifecycleService;
use Illuminate\Database\Migrations\Migration;

/**
 * A job now completes once every checkpoint is done or skipped (at least one done); a skip
 * used to leave it in progress for good. Complete the in-progress jobs already in that state,
 * ending them at their last done checkpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        $lifecycle = app(JobLifecycleService::class);

        JobOperation::withoutGlobalScopes()
            ->where('status', 'in-progress')
            ->whereHas('checkpoints', fn ($q) => $q->where('state', 'done'))
            ->whereHas('checkpoints', fn ($q) => $q->where('state', 'skipped'))
            ->whereDoesntHave('checkpoints', fn ($q) => $q->whereNotIn('state', ['done', 'skipped']))
            ->each(fn (JobOperation $job) => $lifecycle->syncJobProgress($job));
    }

    public function down(): void
    {
        // Completed jobs aren't reopened.
    }
};
