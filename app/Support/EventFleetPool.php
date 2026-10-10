<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Only vehicles and drivers in an event's fleet pool are offered for its movements, so fleet that
 * gets a provider (or an event that gets a default provider) after the fact is invisible until it is added.
 */
class EventFleetPool
{
    private const POOLS = [
        'vehicles' => ['event_vehicle', 'vehicle_id', 'vehicles'],
        'drivers' => ['event_driver', 'driver_id', 'drivers'],
    ];

    /**
     * Adds every vehicle and driver of an event's default provider that is not in its pool yet.
     *
     * @return array{vehicles: int, drivers: int} rows added (or that would be, when $apply is false)
     */
    public static function fill(?int $eventId = null, bool $apply = true): array
    {
        $counts = [];

        foreach (self::POOLS as $label => [$pivot, $column, $table]) {
            $missing = self::missing($pivot, $column, $table, $eventId);
            $counts[$label] = (clone $missing)->count();

            if ($apply && $counts[$label] > 0) {
                DB::table($pivot)->insertUsing(
                    ['event_id', $column, 'created_at', 'updated_at'],
                    $missing->select('e.id', 'r.id', DB::raw('NOW()'), DB::raw('NOW()')),
                );
            }
        }

        return $counts;
    }

    private static function missing(string $pivot, string $column, string $table, ?int $eventId): Builder
    {
        return DB::table('events as e')
            ->join("{$table} as r", 'r.provider_id', '=', 'e.fleet_provider_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from($pivot)
                ->whereColumn("{$pivot}.event_id", 'e.id')
                ->whereColumn("{$pivot}.{$column}", 'r.id'))
            ->when($eventId, fn ($q) => $q->where('e.id', $eventId));
    }
}
