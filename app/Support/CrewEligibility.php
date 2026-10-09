<?php

namespace App\Support;

use App\Models\Driver;
use App\Models\Movement;
use App\Models\User;
use App\Models\Vehicle;

/** Who may be put on a movement: its provider's people and fleet, and fleet put forward for its event. */
class CrewEligibility
{
    /**
     * Why this vehicle, driver or supervisor can't take the movement, or null.
     * Whoever is already on it stays allowed, so old data never blocks an edit.
     *
     * @param 'vehicle'|'driver'|'supervisor' $role
     */
    public static function violation(Movement $movement, string $role, int $id): ?string
    {
        $key = ['vehicle' => 'vehicle_id', 'driver' => 'driver_id', 'supervisor' => 'field_supervisor_id'][$role];
        if (in_array($id, $movement->resourceIds($key), true)) {
            return null;
        }

        $model = match ($role) {
            'vehicle' => Vehicle::find($id),
            'driver' => Driver::find($id),
            'supervisor' => User::find($id),
        };
        if (! $model) {
            return "That {$role} is not available.";
        }

        $owner = $role === 'supervisor' ? $model->fleet_provider_id : $model->provider_id;
        if ($movement->fleet_provider_id && (int) $owner !== (int) $movement->fleet_provider_id) {
            return "That {$role} belongs to a different provider than this movement.";
        }

        $inPool = match ($role) {
            'vehicle' => Vehicle::inEventPool($movement->event_id)->whereKey($id)->exists(),
            'driver' => Driver::inEventPool($movement->event_id)->whereKey($id)->exists(),
            'supervisor' => true,
        };

        return $inPool ? null : "That {$role} is not in this event's fleet.";
    }
}
