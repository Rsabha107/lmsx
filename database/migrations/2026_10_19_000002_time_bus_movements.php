<?php

use App\Models\Movement;
use App\Services\JobGenerationService;
use Illuminate\Database\Migrations\Migration;

/**
 * Bus (road) arrivals and departures used to be left without a time window. They are now timed
 * from the bus's date and time like flights, so give the existing ones without a job their window.
 */
return new class extends Migration
{
    public function up(): void
    {
        $jobs = app(JobGenerationService::class);

        Movement::withoutGlobalScopes()
            ->whereIn('kind', ['arrival', 'departure'])
            ->whereNull('job_id')
            ->whereNull('window_start')
            ->whereHas('flight', fn ($q) => $q->where('flight_number', 'BUS'))
            ->with(['flight', 'checkpointTemplate.checkpoints', 'plan.movementTemplate.legs'])
            ->each(fn (Movement $movement) => $jobs->recomputeMovementWindow($movement));
    }

    public function down(): void
    {
        // The windows are correct either way; nothing to undo.
    }
};
