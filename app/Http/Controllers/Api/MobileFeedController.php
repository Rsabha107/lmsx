<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ScopesMobileAccess;
use App\Models\Contact;
use App\Models\Event;
use App\Models\JobCheckpoint;
use App\Models\JobIssue;
use App\Models\JobOperation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Supporting feeds for the mobile app: alerts, live map, contacts.
 *
 * There is no notifications table, so alerts are derived from real checkpoint
 * and job rows rather than stored separately.
 */
class MobileFeedController extends Controller
{
    use ScopesMobileAccess;

    /**
     * Alerts (derived) + recent activity (real completions).
     */
    public function alerts(Request $request): JsonResponse
    {
        $this->assertCanViewJobs($request);

        $eventId = $this->activeEventId($request);

        $checkpoints = $this->scopeToVisibleAreasViaJob(
            JobCheckpoint::with(['job.team', 'job.movement', 'completedBy'])
                ->where('event_id', $eventId),
            $request
        )
            ->orderByDesc('updated_at')
            ->limit(60)
            ->get();

        $alerts = [];

        foreach ($checkpoints as $cp) {
            $team = $cp->job?->team?->team_name ?? 'Unknown team';
            $jobRef = $cp->job?->job_id ?? 'JOB-'.$cp->job_id;

            if ($cp->state === 'done' && $cp->is_on_time === false) {
                $alerts[] = [
                    'id' => 'late-'.$cp->id,
                    'tone' => ($cp->delay_minutes ?? 0) > 15 ? 'danger' : 'warn',
                    'title' => 'Late: '.$cp->name,
                    'body' => "$jobRef · $team · {$cp->delay_minutes} min past the movement window.",
                    'at' => $cp->completed_at?->toIso8601String(),
                ];
            }

            if ($cp->was_overridden) {
                $alerts[] = [
                    'id' => 'override-'.$cp->id,
                    'tone' => 'warn',
                    'title' => 'Overridden: '.$cp->name,
                    'body' => "$jobRef · ".($cp->override_reason ?: 'No reason recorded.'),
                    'at' => $cp->overridden_at?->toIso8601String(),
                ];
            }

            if ($cp->state === 'skipped') {
                $alerts[] = [
                    'id' => 'skip-'.$cp->id,
                    'tone' => 'neutral',
                    'title' => 'Skipped: '.$cp->name,
                    'body' => "$jobRef · ".($cp->skip_reason ?: 'No reason recorded.'),
                    'at' => $cp->skipped_at?->toIso8601String(),
                ];
            }
        }

        $issues = $this->scopeToVisibleAreasViaJob(
            JobIssue::with('job', 'reporter')->open()->where('event_id', $eventId),
            $request
        )->latest()->limit(25)->get();

        foreach ($issues as $issue) {
            $alerts[] = [
                'id' => 'issue-'.$issue->id,
                'tone' => $issue->severity,
                'title' => 'Issue: '.$issue->label(),
                'body' => ($issue->job?->job_id ?? 'JOB').' · '
                    .($issue->notes ?: 'No detail given.')
                    .($issue->reporter ? ' — '.$issue->reporter->name : ''),
                'at' => $issue->created_at->toIso8601String(),
            ];
        }

        usort($alerts, fn ($a, $b) => ($b['at'] ?? '') <=> ($a['at'] ?? ''));

        $activity = $checkpoints
            ->where('state', 'done')
            ->sortByDesc('completed_at')
            ->take(20)
            ->map(fn ($cp) => [
                'id' => $cp->id,
                'time' => $cp->completed_at?->format('H:i') ?? '--:--',
                'who' => $cp->completedBy?->name ?? 'System',
                'method' => $cp->completion_method ?? 'web',
                'action' => 'Checkpoint ✓',
                'target' => ($cp->job?->job_id ?? 'JOB').' · '.$cp->name,
            ])
            ->values();

        return response()->json([
            'alerts' => array_slice($alerts, 0, 25),
            'activity' => $activity,
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Live map: real in-flight jobs keyed by their movement endpoints.
     *
     * Venues carry no coordinates, so the client lays nodes out from the
     * distinct location names returned here.
     */
    public function map(Request $request): JsonResponse
    {
        $this->assertCanViewJobs($request);

        $jobs = $this->scopeToVisibleAreas(
            JobOperation::with(['team', 'movement', 'vehicle'])
                ->where('event_id', $this->activeEventId($request))
                ->whereIn('status', ['dispatched', 'in-progress']),
            $request
        )->get();

        $locations = [];
        $live = $jobs->map(function ($job) use (&$locations) {
            $from = $job->movement?->from_location ?? '—';
            $to = $job->movement?->to_location ?? '—';
            $locations[$from] = true;
            $locations[$to] = true;

            return [
                'id' => $job->job_id ?? 'JOB-'.$job->id,
                'job_id' => $job->id,
                'code' => $job->team?->code ?? '—',
                'team' => $job->team?->team_name ?? 'Unknown team',
                'from' => $from,
                'to' => $to,
                'progress' => round(((float) $job->progress_percentage) / 100, 4),
                'status' => $job->status,
                'delay_minutes' => $job->movement?->delay_minutes,
                'vehicle' => $job->vehicle?->code,
            ];
        })->values();

        return response()->json([
            'nodes' => array_values(array_keys($locations)),
            'jobs' => $live,
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Profile, today's counts, and the ops contact list.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $eventId = $this->activeEventId($request);

        // Stats must count the same jobs the supervisor can actually open.
        $jobs = $this->scopeToVisibleAreas(
            JobOperation::where('event_id', $eventId),
            $request
        );

        $checkpoints = fn () => $this->scopeToVisibleAreasViaJob(
            JobCheckpoint::where('event_id', $eventId),
            $request
        );

        $totalCheckpoints = $checkpoints()->count();
        $doneCheckpoints = $checkpoints()->where('state', 'done')->count();
        $onTime = $checkpoints()->where('state', 'done')->where('is_on_time', true)->count();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ],
            'event' => Event::find($eventId)?->only(['id', 'name']),
            'stats' => [
                'jobs' => (clone $jobs)->count(),
                'checkpoints' => $doneCheckpoints.'/'.$totalCheckpoints,
                'on_time' => $doneCheckpoints > 0
                    ? round(($onTime / $doneCheckpoints) * 100).'%'
                    : '—',
            ],
            'contacts' => Contact::query()
                ->where('disabled', false)
                ->orderBy('name')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'role' => $c->role,
                    'org' => $c->org,
                    'phone' => $c->phone,
                    'online' => (bool) $c->on_shift,
                ]),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Events the supervisor can work in, newest first, so the app can offer a
     * picker after sign-in instead of guessing from the active flag.
     */
    public function events(Request $request): JsonResponse
    {
        $user = $request->user();

        $counts = JobOperation::query()
            ->selectRaw('event_id, count(*) as total')
            ->groupBy('event_id')
            ->pluck('total', 'event_id');

        $events = Event::query()
            ->orderByDesc('active_flag')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Event $event) => $user->canAccessEvent($event->id))
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'short_name' => $event->short_name,
                'status' => $event->status,
                'starts_on' => $event->start_date?->toDateString(),
                'ends_on' => $event->end_date?->toDateString(),
                'active' => (bool) $event->active_flag,
                'jobs' => (int) ($counts[$event->id] ?? 0),
            ])
            ->values();

        // Falls back to the first event the user may work, so the picker never
        // defaults to something they'd be denied.
        $default = $this->resolveEventId($request);
        if (! $user->canAccessEvent($default)) {
            $default = $events->first()['id'] ?? null;
        }

        return response()->json([
            'data' => $events,
            'default_id' => $default,
        ]);
    }

    private function resolveEventId(Request $request): ?int
    {
        return $request->integer('event_id')
            ?: Event::where('active_flag', true)->latest('id')->value('id')
            ?: Event::query()->latest('id')->value('id');
    }
}
