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

    /** Same active event, jobs.view and functional area. */
    private function inScope(User $user, JobOperation $job): bool
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
     * Acting on a job (status changes, checkpoint completion) is scoped exactly like reading it:
     * same active event, same functional area, and a field supervisor's own jobs.
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
