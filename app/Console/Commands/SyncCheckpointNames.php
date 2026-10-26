<?php

namespace App\Console\Commands;

use App\Services\CheckpointNameSync;
use Illuminate\Console\Command;

/** One-off catch-up: give every job step its library checkpoint's current name. Dry run by default. */
class SyncCheckpointNames extends Command
{
    protected $signature = 'checkpoints:sync-names {--apply : Rename the job steps (otherwise only list them)}';

    protected $description = 'Rename job steps to their library checkpoint\'s current name';

    public function handle(CheckpointNameSync $sync): int
    {
        $pending = $sync->pending();

        if ($pending->isEmpty()) {
            $this->info('Every job step already has its checkpoint\'s current name.');

            return self::SUCCESS;
        }

        $this->table(['Code', 'Library name', 'Names on jobs now', 'Steps', 'Jobs'],
            $pending->map(fn ($p) => [$p->code, $p->name, $p->old_names, $p->rows, $p->jobs])->all());

        if (! $this->option('apply')) {
            $this->warn('Dry run: nothing changed. Run with --apply to rename these job steps.');

            return self::SUCCESS;
        }

        $this->info($sync->apply().' job step(s) renamed.');

        return self::SUCCESS;
    }
}
