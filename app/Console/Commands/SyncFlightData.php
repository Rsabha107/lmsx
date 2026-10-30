<?php

namespace App\Console\Commands;

use App\Models\TeamFlight;
use App\Services\FlightChangeDigest;
use App\Services\FlightRefresh;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncFlightData extends Command
{
    public const LAST_RUN_KEY = 'flights:last_run_at';

    protected $signature = 'flights:sync';

    protected $description = 'Refresh schedule and status of flights around now from OAG (AviationStack as fallback)';

    public function handle(FlightRefresh $refresh, FlightChangeDigest $digest): int
    {
        $this->info('Syncing flight data from OAG...');

        $flights = TeamFlight::with(['team:id,code', 'originAirport', 'destinationAirport'])
            ->whereHas('event')
            ->whereNotNull('flight_number')
            ->where('flight_number', '!=', 'BUS')
            ->whereBetween('scheduled_at', [now()->subHours(6), now()->addHours(12)])
            ->where(fn ($q) => $q->whereNull('flight_status')->orWhereNotIn('flight_status', ['landed', 'cancelled']))
            ->get();

        // Every run within 3 h of the flight, every 3 h further out, to spare the API quota.
        $due = $flights->filter(fn (TeamFlight $flight) => !$flight->flight_synced_at
            || $flight->flight_synced_at->lt(now()->subMinutes(abs(now()->diffInMinutes($flight->scheduled_at)) <= 180 ? 25 : 180)));

        $synced = 0;
        $failed = 0;
        foreach ($due as $flight) {
            // OAG, then AviationStack; the attempt and any failure are recorded on the flight.
            $refresh->refresh($flight)['ok'] ? $synced++ : $failed++;
        }

        // One push per supervisor and one email per planner for the whole run.
        $digest->flush();
        // The header's flight status chip shows how long ago this last ran.
        Cache::forever(self::LAST_RUN_KEY, now()->format('Y-m-d H:i:s'));

        $this->info("Synced:  {$synced}");
        $this->info("Failed:  {$failed}");
        $this->info('Skipped: '.($flights->count() - $due->count()));

        return self::SUCCESS;
    }
}
