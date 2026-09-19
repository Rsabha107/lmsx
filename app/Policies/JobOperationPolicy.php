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

    /**
     * Acting on a job (status changes, checkpoint completion, overrides) is
     * scoped exactly like reading it: same active event, same functional area.
     */
    public function update(User $user, JobOperation $job): bool
    {
        return $this->view($user, $job);
    }

    /**
     * Overriding writes a completion record the supervisor did not witness, so
     * it needs an explicit grant on top of normal access to the job.
     */
    public function override(User $user, JobOperation $job): bool
    {
        return $this->update($user, $job) && $user->can('jobs.override');
    }
}
