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
}
