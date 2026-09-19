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
     * Mobile clients have no session, so resolve the flagged active event. The
     * client picks the event, so the choice is authorized against the user's
     * assignments rather than trusted outright.
     */
    protected function activeEventId(Request $request): ?int
    {
        $eventId = $request->integer('event_id')
            ?: Event::where('active_flag', true)->latest('id')->value('id')
            ?: Event::query()->latest('id')->value('id');

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
     * Constrain a query to the caller's areas. An empty area list matches
     * nothing, which is the intended deny-by-default.
     */
    protected function scopeToVisibleAreas(Builder $query, Request $request, string $column = 'functional_area'): Builder
    {
        $areas = $this->visibleFunctionalAreas($request);

        return $areas === null ? $query : $query->whereIn($column, $areas);
    }

    /**
     * Same restriction for rows that reach functional_area through their job.
     */
    protected function scopeToVisibleAreasViaJob(Builder $query, Request $request, string $relation = 'job'): Builder
    {
        $areas = $this->visibleFunctionalAreas($request);

        if ($areas === null) {
            return $query;
        }

        return $query->whereHas($relation, fn (Builder $job) => $job->whereIn('functional_area', $areas));
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
