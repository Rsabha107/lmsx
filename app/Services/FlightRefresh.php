<?php

namespace App\Services;

use App\Models\TeamFlight;

/**
 * One flight refresh: OAG first, then AviationStack (no AI web search). Records the attempt and its outcome
 * on the flight (`flight_sync_attempted_at`, `flight_sync_error`); `flight_synced_at` is set by the sources
 * only when they saved data. Used by `flights:sync`, the Sync button and bulk refresh on Event Teams.
 */
class FlightRefresh
{
    public function __construct(private OagScheduleService $oag, private FlightSyncService $aviationStack) {}

    /** @return array{ok: bool, source: ?string, error: ?string, change: ?array} */
    public function refresh(TeamFlight $flight): array
    {
        $source = null;
        $change = null;
        $error = null;

        if (! $flight->flight_number || $flight->flight_number === 'BUS') {
            return ['ok' => false, 'source' => null, 'error' => 'No flight number to look up.', 'change' => null];
        }

        try {
            $oag = $this->oag->sync($flight);
            if ($oag['saved'] ?? false) {
                $source = 'oag';
                $change = $oag['change'] ?? null;
            } else {
                $raw = $this->aviationStack->syncFlightRecord($flight);
                if ($raw && empty($raw['_date_mismatch'])) {
                    $source = 'aviationstack';
                } else {
                    $error = $raw && ! empty($raw['_date_mismatch'])
                        ? 'Found only on another date; check the flight number and date.'
                        : 'Not found by OAG or AviationStack; check the flight number and date.';
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $error = 'The flight source could not be reached.';
        }

        $flight->forceFill(['flight_sync_attempted_at' => now(), 'flight_sync_error' => $error])->saveQuietly();

        return ['ok' => $error === null, 'source' => $source, 'error' => $error, 'change' => $change];
    }
}
