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
        // Field supervisors work only their own jobs, as in the mobile API.
        return $this->inScope($user, $job)
            && ($user->can('jobs.view-unassigned') || $job->isSupervisedBy((int) $user->id));
    }

    /** Reading only: anyone who may view it, plus the agency for jobs of its events (ProviderScope limits the rest). */
    public function read(User $user, JobOperation $job): bool
    {
        return $this->view($user, $job)
            || ($user->hasRole('agency') && $user->canAccessEvent((int) $job->event_id));
    }

    /**
     * An event the user may open (the active one, or another shown with it on the Jobs Queue or
     * Day Board), jobs.view and functional area.
     */
    private function inScope(User $user, JobOperation $job): bool
    {
        if (! $user->canAccessEvent((int) $job->event_id)) {
            return false;
        }

        if (! $user->can('jobs.view')) {
            return false;
        }

        return $user->can('jobs.view-all-functional-areas')
            || $user->hasFunctionalArea($job->functional_area);
    }

    /**
     * Acting on a job (status changes, checkpoint completion, resolving issues) is scoped exactly like
     * reading it (same active event, same functional area, a field supervisor's own jobs) and needs jobs.work.
     */
    public function update(User $user, JobOperation $job): bool
    {
        return $this->view($user, $job) && $user->can('jobs.work');
    }

    /**
     * Overriding writes a completion record the supervisor did not witness, so
     * it needs an explicit grant on top of normal access to the job.
     */
    public function override(User $user, JobOperation $job): bool
    {
        return $job->status !== 'cancelled'
            && $this->inScope($user, $job)
            && $user->can('jobs.override');
    }

    /** Deleting discards the job's field record, so it needs planning rights too. */
    public function delete(User $user, JobOperation $job): bool
    {
        return $this->inScope($user, $job) && $user->can('plans.manage');
    }
}
