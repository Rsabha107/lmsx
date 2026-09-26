<?php

namespace App\Support;

use App\Models\TeamFlight;

/**
 * The flight behind an arrival/departure job, shaped once for both the web
 * Jobs Queue and the mobile API. Times are formatted server-side so every
 * client shows the event's wall-clock time, whatever its own timezone.
 */
final class FlightSummary
{
    /**
     * @return array<string, mixed>|null
     */
    public static function from(?TeamFlight $flight): ?array
    {
        if (! $flight) {
            return null;
        }

        return [
            'id' => $flight->id,
            'direction' => $flight->direction,
            'flight_number' => $flight->flight_number,
            // A "BUS" leg is a road transfer, not a flight.
            'is_bus' => $flight->flight_number === 'BUS',
            'origin_airport' => $flight->originAirport?->code,
            'destination_airport' => $flight->destinationAirport?->code,
            'scheduled_at' => $flight->scheduled_at?->toIso8601String(),
            'scheduled_time' => $flight->scheduled_at?->format('H:i'),
            'scheduled_date' => $flight->scheduled_at?->format('D j M'),
            'estimated_time' => $flight->estimated_at?->format('H:i'),
            'actual_time' => $flight->actual_at?->format('H:i'),
            'delay_minutes' => $flight->delay_minutes > 0 ? $flight->delay_minutes : null,
            'flight_status' => $flight->flight_status,
            'terminal' => $flight->terminal,
            'gate' => $flight->gate,
        ];
    }
}
