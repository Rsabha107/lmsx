<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\JobCheckpoint;
use App\Services\JobLifecycleService;
use Illuminate\Console\Command;

class RecomputeCheckpointPunctuality extends Command
{
    protected $signature = 'checkpoints:recompute-punctuality {--event= : Only this event id} {--apply : Write the changes (default is a dry run)}';

    protected $description = 'Re-judge completed checkpoints against their own scheduled time (no scheduled time = neither on time nor late)';

    public function handle(JobLifecycleService $lifecycle): int
    {
        $eventId = $this->option('event') ? (int) $this->option('event') : null;
        $apply = (bool) $this->option('apply');
        $flag = fn (?bool $v) => $v === null ? '—' : ($v ? 'on time' : 'late');

        $rows = [];
        $changed = 0;
        $counts = ['on_time' => 0, 'late' => 0, 'no_flag' => 0];

        JobCheckpoint::with('job:id,job_id')
            ->where('state', 'done')
            ->whereNotNull('completed_at')
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->orderBy('id')
            ->chunkById(500, function ($checkpoints) use ($lifecycle, $apply, $flag, &$rows, &$changed, &$counts) {
                foreach ($checkpoints as $cp) {
                    $new = $lifecycle->punctualityOf($cp);
                    $oldDelay = $cp->getRawOriginal('delay_minutes');
                    $oldDelay = $oldDelay === null ? null : (int) $oldDelay;

                    if ($cp->is_on_time === $new['is_on_time'] && $oldDelay === $new['delay_minutes']) {
                        continue;
                    }

                    $changed++;
                    $counts[$new['is_on_time'] === null ? 'no_flag' : ($new['is_on_time'] ? 'on_time' : 'late')]++;
                    $rows[] = [
                        $cp->job?->job_id ?? "job {$cp->job_id}",
                        $cp->name,
                        $cp->scheduled_at?->format('Y-m-d H:i') ?? '—',
                        $cp->completed_at->format('Y-m-d H:i'),
                        $flag($cp->is_on_time) . ($oldDelay ? " ({$oldDelay}m)" : ''),
                        $flag($new['is_on_time']) . ($new['delay_minutes'] ? " ({$new['delay_minutes']}m)" : ''),
                    ];

                    if ($apply) {
                        $cp->update($new);
                    }
                }
            });

        if ($rows) {
            $this->table(['Job', 'Checkpoint', 'Scheduled', 'Completed', 'Before', 'After'], $rows);
        }

        $this->line(sprintf(
            '  %d checkpoint(s) %s: %d on time, %d late, %d no flag',
            $changed, $apply ? 'updated' : 'would change', $counts['on_time'], $counts['late'], $counts['no_flag'],
        ));

        if (! $apply) {
            $this->warn('Dry run. Re-run with --apply to write these changes.');

            return self::SUCCESS;
        }

        if ($changed) {
            AuditLog::change('Checkpoint punctuality recomputed', $eventId ? "event {$eventId}" : 'all events', ['changed' => $changed] + $counts, eventId: $eventId);
        }

        return self::SUCCESS;
    }
}
