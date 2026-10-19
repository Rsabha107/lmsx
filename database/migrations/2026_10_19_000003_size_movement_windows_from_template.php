<?php

use App\Models\MovementTemplate;
use App\Services\JobGenerationService;
use Illuminate\Database\Migrations\Migration;

/**
 * Movement windows now take their length from the movement template's estimated duration
 * (they fell back to 30 minutes when the leg had none). Re-end the windows of movements
 * without a job; start times stay as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        $jobs = app(JobGenerationService::class);

        MovementTemplate::withoutGlobalScopes()
            ->whereNotNull('estimated_duration_minutes')
            ->each(fn (MovementTemplate $template) => $jobs->applyTemplateDurations($template));
    }

    public function down(): void
    {
        // The old 30-minute fallback isn't worth restoring.
    }
};
