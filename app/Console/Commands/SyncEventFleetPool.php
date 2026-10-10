<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\EventFleetPool;
use Illuminate\Console\Command;

class SyncEventFleetPool extends Command
{
    protected $signature = 'fleet:sync-pool {--event= : Only this event id} {--apply : Write the changes (default is a dry run)}';

    protected $description = 'Add every vehicle and driver of an event\'s default provider to that event\'s fleet pool';

    public function handle(): int
    {
        $eventId = $this->option('event') ? (int) $this->option('event') : null;
        $apply = (bool) $this->option('apply');

        $counts = EventFleetPool::fill($eventId, $apply);

        $this->line(sprintf('  vehicles %d, drivers %d %s', $counts['vehicles'], $counts['drivers'], $apply ? 'added' : 'would be added'));

        if (! $apply) {
            $this->warn('Dry run. Re-run with --apply to write these changes.');

            return self::SUCCESS;
        }

        AuditLog::change('Event fleet pool synced', $eventId ? "event {$eventId}" : 'all events', $counts);

        return self::SUCCESS;
    }
}
