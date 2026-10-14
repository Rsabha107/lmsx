<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\JobOperation;
use App\Services\JobLifecycleService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecomputeJobTimes extends Command
{
    protected $signature = 'jobs:recompute-times {--event= : Only this event id} {--apply : Write the changes (default is a dry run)}';

    protected $description = "Reset jobs' started/completed times (and movements' actual departure/arrival) from their first/last done checkpoint";

    public function handle(JobLifecycleService $lifecycle): int
    {
        $eventId = $this->option('event') ? (int) $this->option('event') : null;
        $apply = (bool) $this->option('apply');
        $fmt = fn ($v) => $v ? Carbon::parse($v)->format('Y-m-d H:i') : '—';

        $rows = [];
        $jobsChanged = 0;
        $movementsChanged = 0;

        JobOperation::with('movement')
            ->where(fn ($q) => $q->whereNotNull('started_at')->orWhereNotNull('completed_at'))
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->orderBy('id')
            ->chunkById(200, function ($jobs) use ($lifecycle, $apply, $fmt, &$rows, &$jobsChanged, &$movementsChanged) {
                foreach ($jobs as $job) {
                    $start = $lifecycle->checkpointTime($job, 'in-progress');
                    if (! $start) {
                        continue;
                    }
                    $end = $job->completed_at ? $lifecycle->checkpointTime($job, 'completed') : null;

                    $jobAttrs = array_filter([
                        'started_at' => $job->started_at && ! $job->started_at->equalTo($start) ? $start : null,
                        'completed_at' => $end && ! $job->completed_at->equalTo($end) ? $end : null,
                    ]);

                    $movement = $job->movement;
                    $movementAttrs = $movement ? array_filter([
                        'actual_departure' => $movement->actual_departure && ! $movement->actual_departure->equalTo($start) ? $start : null,
                        'actual_arrival' => $end && $movement->actual_arrival && ! $movement->actual_arrival->equalTo($end) ? $end : null,
                    ]) : [];

                    if (! $jobAttrs && ! $movementAttrs) {
                        continue;
                    }

                    $rows[] = [
                        $job->job_id,
                        $fmt($job->started_at) . ' → ' . $fmt($jobAttrs['started_at'] ?? $job->started_at),
                        $fmt($job->completed_at) . ' → ' . $fmt($jobAttrs['completed_at'] ?? $job->completed_at),
                        $movementAttrs ? 'yes' : '—',
                    ];

                    if ($apply) {
                        // Timestamps only: status stays as it is.
                        if ($jobAttrs) {
                            $job->update($jobAttrs);
                        }
                        if ($movementAttrs) {
                            $movement->update($movementAttrs);
                        }
                    }
                    $jobsChanged += $jobAttrs ? 1 : 0;
                    $movementsChanged += $movementAttrs ? 1 : 0;
                }
            });

        if ($rows) {
            $this->table(['Job', 'Started (before → after)', 'Completed (before → after)', 'Movement actuals'], $rows);
        }

        $this->line(sprintf('  %d job(s) and %d movement(s) %s', $jobsChanged, $movementsChanged, $apply ? 'updated' : 'would change'));

        if (! $apply) {
            $this->warn('Dry run. Re-run with --apply to write these changes.');

            return self::SUCCESS;
        }

        if ($jobsChanged || $movementsChanged) {
            AuditLog::change('Job times recomputed from checkpoints', $eventId ? "event {$eventId}" : 'all events', ['jobs' => $jobsChanged, 'movements' => $movementsChanged], eventId: $eventId);
        }

        return self::SUCCESS;
    }
}
