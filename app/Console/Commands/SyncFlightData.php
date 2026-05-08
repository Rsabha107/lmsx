<?php

namespace App\Console\Commands;

use App\Services\FlightSyncService;
use Illuminate\Console\Command;

class SyncFlightData extends Command
{
    protected $signature = 'flights:sync';

    protected $description = 'Sync actual flight data from AviationStack for all active teams';

    public function handle(FlightSyncService $service): int
    {
        $this->info('Syncing flight data from AviationStack...');

        $result = $service->syncAll();

        $this->info("Synced:  {$result['synced']}");
        $this->info("Failed:  {$result['failed']}");
        $this->info("Skipped: {$result['skipped']}");

        return 0;
    }
}
