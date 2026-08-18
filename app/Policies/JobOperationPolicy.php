<?php

namespace App\Policies;

use App\Models\JobOperation;
use App\Models\User;

class JobOperationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('jobs.view');
    }

    public function view(User $user, JobOperation $job): bool
    {
        if ((int) $job->event_id !== (int) session('active_event_id')) {
            return false;
        }

        if (! $user->can('jobs.view')) {
            return false;
        }

        return $user->can('jobs.view-all-functional-areas')
            || $user->hasFunctionalArea($job->functional_area);
    }
}
