<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Event;
use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Event and functional-area scoping for the token-authenticated mobile API.
 *
 * JobOperationPolicy can't be used here because it reads the web session's
 * active event, and token requests have no session.
 */
trait ScopesMobileAccess
{
    /**
     * Mobile clients have no session, so they name the event; without one, the
     * event running today. The choice is authorized against the user's
     * assignments rather than trusted outright.
     */
    protected function activeEventId(Request $request): ?int
    {
        $eventId = $request->integer('event_id') ?: Event::defaultId();

        abort_unless(
            $request->user()->canAccessEvent($eventId),
            403,
            'You are not assigned to this event.'
        );

        return $eventId;
    }

    /**
     * Functional areas the caller may see, or null when unrestricted.
     *
     * @return array<int, string>|null
     */
    protected function visibleFunctionalAreas(Request $request): ?array
    {
        $user = $request->user();

        if ($user->can('jobs.view-all-functional-areas')) {
            return null;
        }

        return $user->functionalAreaCodes();
    }

    /**
     * Field supervisors see only the jobs they supervise; desk roles holding
     * jobs.view-unassigned see every job their functional areas allow.
     */
    protected function onlyOwnJobs(Request $request): bool
    {
        return ! $request->user()->can('jobs.view-unassigned');
    }

    /**
     * Constrain a jobs query to the caller's areas (and, for field supervisors,
     * their own jobs). An empty area list matches nothing, which is the
     * intended deny-by-default.
     */
    protected function scopeToVisibleAreas(Builder $query, Request $request, string $column = 'functional_area'): Builder
    {
        if ($this->onlyOwnJobs($request)) {
            // Keep the table prefix the caller used, so joined queries stay unambiguous.
            $prefix = str_contains($column, '.') ? strstr($column, '.', true).'.' : '';
            $query->where($prefix.'supervisor_id', $request->user()->id);
        }

        $areas = $this->visibleFunctionalAreas($request);

        return $areas === null ? $query : $query->whereIn($column, $areas);
    }

    /**
     * Same restriction for rows that reach their job through a relation.
     */
    protected function scopeToVisibleAreasViaJob(Builder $query, Request $request, string $relation = 'job'): Builder
    {
        $areas = $this->visibleFunctionalAreas($request);
        $ownOnly = $this->onlyOwnJobs($request);

        if ($areas === null && ! $ownOnly) {
            return $query;
        }

        return $query->whereHas($relation, function (Builder $job) use ($areas, $ownOnly, $request) {
            if ($areas !== null) {
                $job->whereIn('functional_area', $areas);
            }
            if ($ownOnly) {
                $job->where('supervisor_id', $request->user()->id);
            }
        });
    }

    protected function authorizeJobAccess(Request $request, JobOperation $job): void
    {
        // 404 rather than 403 so job ids from other events aren't enumerable.
        abort_unless((int) $job->event_id === (int) $this->activeEventId($request), 404);

        $user = $request->user();

        abort_unless($user->can('jobs.view'), 403, 'You do not have access to jobs.');

        abort_unless(
            $user->can('jobs.view-all-functional-areas')
                || $user->hasFunctionalArea($job->functional_area),
            403,
            'This job is outside your functional area.'
        );

        abort_if(
            $this->onlyOwnJobs($request) && (int) $job->supervisor_id !== (int) $user->id,
            403,
            'This job is assigned to another supervisor.'
        );
    }

    protected function authorizeCheckpointAccess(Request $request, JobCheckpoint $checkpoint): void
    {
        $job = $checkpoint->job;

        abort_unless($job, 404);

        $this->authorizeJobAccess($request, $job);
    }

    protected function assertCanViewJobs(Request $request): void
    {
        abort_unless($request->user()->can('jobs.view'), 403, 'You do not have access to jobs.');
    }
}
