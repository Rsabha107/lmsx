<?php

namespace App\Policies;

use App\Models\Movement;
use App\Models\User;

class MovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('movements.view');
    }

    public function view(User $user, Movement $movement): bool
    {
        if ((int) $movement->event_id !== (int) session('active_event_id')) {
            return false;
        }

        if (! $user->can('movements.view')) {
            return false;
        }

        return $user->can('movements.view-all-functional-areas')
            || $user->hasFunctionalArea($movement->functional_area);
    }

    /**
     * Crew Assignment can show several events at once, so its writes accept a movement of any
     * event the user may open, not only the active one.
     */
    public function assignCrew(User $user, Movement $movement): bool
    {
        $reachable = (int) $movement->event_id === (int) session('active_event_id')
            || $user->canAccessEvent((int) $movement->event_id);
        if (! $reachable || ! $user->can('movements.view')) {
            return false;
        }

        return $user->can('movements.view-all-functional-areas')
            || $user->hasFunctionalArea($movement->functional_area);
    }
}
