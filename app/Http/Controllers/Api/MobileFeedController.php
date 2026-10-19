<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ScopesMobileAccess;
use App\Models\Contact;
use App\Models\Event;
use App\Models\FleetProvider;
use App\Models\JobCheckpoint;
use App\Models\JobIssue;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\User;
use App\Support\WallClock;
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
            $link = ['job_id' => $cp->job_id, 'code' => $cp->job?->team?->code];

            if ($cp->state === 'done' && $cp->is_on_time === false) {
                $alerts[] = [
                    'id' => 'late-'.$cp->id,
                    'tone' => ($cp->delay_minutes ?? 0) > 15 ? 'danger' : 'warn',
                    'title' => 'Late: '.$cp->name,
                    'body' => "$jobRef · $team · {$cp->delay_minutes} min late.",
                    'at' => WallClock::iso($cp->completed_at),
                    ...$link,
                ];
            }

            if ($cp->was_overridden) {
                $alerts[] = [
                    'id' => 'override-'.$cp->id,
                    'tone' => 'warn',
                    'title' => 'Overridden: '.$cp->name,
                    'body' => "$jobRef · ".($cp->override_reason ?: 'No reason recorded.'),
                    'at' => WallClock::iso($cp->overridden_at),
                    ...$link,
                ];
            }

            if ($cp->state === 'skipped') {
                $alerts[] = [
                    'id' => 'skip-'.$cp->id,
                    'tone' => 'neutral',
                    'title' => 'Skipped: '.$cp->name,
                    'body' => "$jobRef · ".($cp->skip_reason ?: 'No reason recorded.'),
                    'at' => WallClock::iso($cp->skipped_at),
                    ...$link,
                ];
            }
        }

        $issues = $this->scopeToVisibleAreasViaJob(
            JobIssue::with('job.team', 'reporter')->open()->where('event_id', $eventId),
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
                'at' => WallClock::iso($issue->created_at),
                'job_id' => $issue->job_id,
                'code' => $issue->job?->team?->code,
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
                'at' => WallClock::iso($cp->completed_at),
                'job_id' => $cp->job_id,
                'code' => $cp->job?->team?->code,
                'team' => $cp->job?->team?->team_name,
                'checkpoint' => $cp->name,
            ])
            ->values();

        return response()->json([
            'alerts' => array_slice($alerts, 0, 25),
            'activity' => $activity,
            'synced_at' => WallClock::iso(now()),
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
            'synced_at' => WallClock::iso(now()),
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
        $event = Event::find($eventId);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'read_only' => ! $user->can('jobs.work'),
            ],
            'event' => $event?->only(['id', 'name']),
            'stats' => [
                'jobs' => (clone $jobs)->count(),
                'checkpoints' => $doneCheckpoints.'/'.$totalCheckpoints,
                'on_time' => $doneCheckpoints > 0
                    ? round(($onTime / $doneCheckpoints) * 100).'%'
                    : '—',
            ],
            'contacts' => $this->contacts($user, $event),
            'synced_at' => WallClock::iso(now()),
        ]);
    }

    /**
     * The app groups contacts by org, in this order: the user's crew, the directory, field supervisors, providers.
     *
     * @return array<int, array<string, mixed>>
     */
    private function contacts(User $user, ?Event $event): array
    {
        $row = fn (string $id, ?string $name, ?string $role, ?string $org, ?string $phone, bool $online = false) => [
            'id' => $id,
            'name' => (string) $name,
            'role' => (string) $role,
            'org' => (string) $org,
            'phone' => (string) $phone,
            'online' => $online,
        ];

        $jobs = JobOperation::where('event_id', $event?->id)
            ->supervisedBy($user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with([
                'team:id,code', 'supervisor:id,name,phone',
                'movement.driver:id,name,phone,status', 'movement.units.driver:id,name,phone,status',
                'movement.fieldSupervisor:id,name,phone', 'movement.extraSupervisors:id,name,phone',
            ])
            ->get();

        $crew = [];
        $add = function (string $id, string $role, $person, ?string $team, bool $online = false) use (&$crew) {
            $crew[$id] ??= ['person' => $person, 'role' => $role, 'teams' => [], 'online' => $online];
            $crew[$id]['teams'][] = $team;
        };
        foreach ($jobs as $job) {
            $m = $job->movement;
            foreach ([$m?->driver, ...($m?->units->pluck('driver') ?? [])] as $driver) {
                if ($driver) {
                    $add("driver-{$driver->id}", 'Driver', $driver, $job->team?->code, $driver->status === 'on_shift');
                }
            }
            foreach ([$m?->fieldSupervisor ?? $job->supervisor, ...($m?->extraSupervisors ?? [])] as $supervisor) {
                if ($supervisor && $supervisor->id !== $user->id) {
                    $add("user-{$supervisor->id}", 'Supervisor', $supervisor, $job->team?->code);
                }
            }
        }

        $rows = [];
        $shownUsers = [$user->id];
        foreach ($crew as $id => $c) {
            $teams = implode(', ', array_unique(array_filter($c['teams'])));
            $rows[] = $row($id, $c['person']->name, $teams ? "{$c['role']} · {$teams}" : $c['role'], 'My crew', $c['person']->phone, $c['online']);
            if (str_starts_with($id, 'user-')) {
                $shownUsers[] = $c['person']->id;
            }
        }

        foreach (Contact::active()->orderBy('name')->get() as $contact) {
            if ($contact->user_id && in_array((int) $contact->user_id, $shownUsers, true)) {
                continue;
            }
            $rows[] = $row("contact-{$contact->id}", $contact->name, $contact->role, $contact->org, $contact->phone, (bool) $contact->on_shift);
            if ($contact->user_id) {
                $shownUsers[] = (int) $contact->user_id;
            }
        }

        $supervisors = User::fieldSupervisors($user)
            ->whereHas('events', fn ($q) => $q->where('events.id', $event?->id))
            ->whereNotIn('users.id', $shownUsers)
            ->with('fleetProvider:id,code')
            ->orderBy('name')
            ->get(['users.id', 'name', 'phone', 'job_title', 'fleet_provider_id']);
        foreach ($supervisors as $supervisor) {
            $role = implode(' · ', array_filter([$supervisor->job_title ?: 'Field Supervisor', $supervisor->fleetProvider?->code]));
            $rows[] = $row("user-{$supervisor->id}", $supervisor->name, $role, 'Field supervisors', $supervisor->phone);
        }

        // The event's providers: its default one and any running its movements.
        $providerIds = Movement::where('event_id', $event?->id)->whereNotNull('fleet_provider_id')->distinct()
            ->pluck('fleet_provider_id')->push($event?->fleet_provider_id)->filter()->unique();
        foreach (FleetProvider::whereIn('id', $providerIds)->orderBy('name')->get() as $provider) {
            $rows[] = $row("provider-{$provider->id}", $provider->contact_person ?: $provider->name, 'Fleet provider contact', $provider->name, $provider->phone);
        }

        return $rows;
    }

    /**
     * Events the supervisor can work in, newest first, so the app can offer a
     * picker after sign-in instead of guessing from the active flag.
     */
    public function events(Request $request): JsonResponse
    {
        $user = $request->user();

        $counts = JobOperation::query()
            ->when($this->onlyOwnJobs($request), fn ($q) => $q->supervisedBy($user->id))
            ->selectRaw('event_id, count(*) as total, count(distinct team_id) as teams')
            ->groupBy('event_id')
            ->get()
            ->keyBy('event_id');

        $punctuality = JobCheckpoint::query()
            ->where('state', 'done')
            ->selectRaw('event_id, count(*) as done, sum(case when is_on_time = 0 then 0 else 1 end) as on_time')
            ->groupBy('event_id')
            ->get()
            ->keyBy('event_id');

        $events = Event::query()
            ->active()
            ->with(['country', 'venues'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Event $event) => $user->canAccessEvent($event->id))
            ->map(function (Event $event) use ($counts, $punctuality) {
                $jobs = $counts[$event->id] ?? null;
                $done = $punctuality[$event->id] ?? null;

                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'short_name' => $event->short_name,
                    'status' => $event->status,
                    'starts_on' => $event->start_date?->toDateString(),
                    'ends_on' => $event->end_date?->toDateString(),
                    // Kept for app builds that predate the date-derived status.
                    'active' => $event->status === Event::STATUS_CURRENT,
                    'jobs' => (int) ($jobs->total ?? 0),
                    'teams' => (int) ($jobs->teams ?? 0),
                    'venue' => $event->venues->first()?->city ?: $event->country?->country_name,
                    'on_time_pct' => $done && $done->done > 0
                        ? (int) round($done->on_time / $done->done * 100)
                        : null,
                ];
            })
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
        return $request->integer('event_id') ?: Event::defaultId();
    }
}
