<?php

namespace App\Http\Controllers;

use App\Data\LmsData;
use App\Mail\DailySummaryMail;
use App\Models\AuditLog;
use App\Models\Vehicle;
use App\Models\FleetProvider;
use App\Models\Driver;
use App\Models\Contact;
use App\Models\Team;
use App\Models\TeamClassification;
use App\Models\Country;
use App\Models\Airport;
use App\Models\Checkpoint;
use App\Models\CheckpointTemplate;
use App\Models\MovementTemplate;
use App\Models\JobOperation;
use App\Models\JobCheckpoint;
use App\Models\JobIssue;
use App\Models\User;
use App\Models\Event;
use App\Models\Plan;
use App\Models\Movement;
use App\Services\JobLifecycleService;
use App\Services\DailySummaryService;
use App\Services\NotificationFeedService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LmsController extends Controller
{
    use AuthorizesRequests;

    public function dashboard(Request $request, NotificationFeedService $notifications): Response|RedirectResponse
    {
        if (! $request->user()->can('console.view')) {
            return redirect()->route('jobs.mobile');
        }

        return Inertia::render('Dashboard', [
            'kpis'          => LmsData::kpis(),
            'schedule'      => LmsData::schedule(),
            'notifications' => $notifications->recent($request->session()->get('active_event_id'), 6),
            'audit'         => LmsData::audit(),
        ]);
    }

    public function schedule(Request $request): Response
    {
        // Accept an optional ?date=YYYY-MM-DD so the page can browse other
        // days' movements; malformed input silently falls back to today
        // rather than erroring out.
        $date = $request->query('date');
        try {
            $date = $date ? \Carbon\Carbon::parse($date)->toDateString() : now()->toDateString();
        } catch (\Exception) {
            $date = now()->toDateString();
        }

        $movements = JobOperation::with(['movement.team', 'movement.flight', 'vehicle'])
            ->join('movements', 'jobs_operations.movement_id', '=', 'movements.id')
            ->whereDate('movements.window_start', $date)
            ->orderBy('movements.window_start', 'asc')
            ->select('jobs_operations.*')
            ->get()
            ->map(function ($job) {
                $movement = $job->movement;
                $team = $movement?->team;
                $delay = $movement?->delay_minutes;

                $status = 'scheduled';
                if ($delay > 0) {
                    $status = 'delayed';
                } elseif ($job->status === 'completed') {
                    $status = 'done';
                } elseif ($job->status === 'in-progress') {
                    $status = 'in-progress';
                }

                return [
                    'id' => $job->job_id ?? 'J-' . $job->id,
                    'code' => $team?->code ?? 'UNK',
                    'team' => $team?->team_name ?? 'Unknown Team',
                    'from' => $movement?->from_location ?? 'Unknown',
                    'to' => $movement?->to_location ?? 'Unknown',
                    'dep' => $movement?->window_start?->format('H:i') ?? '--:--',
                    'arr' => $movement?->window_end?->format('H:i') ?? '--:--',
                    'pax' => $movement?->passengers ?? $movement?->flight?->party_size_total ?? $team?->party_size_total ?? 0,
                    'vehicle' => $job->vehicle ? ($job->vehicle->code ?? $job->vehicle->plate_number ?? $job->vehicle->vehicle_type ?? 'Unassigned') : 'Unassigned',
                    'status' => $status,
                    'delay' => $delay > 0 ? $delay : null,
                ];
            });

        return Inertia::render('Schedule', [
            'schedule' => $movements,
            'scheduleDate' => $date,
        ]);
    }

    public function plans(Request $request): Response
    {
        $activeEventId = $request->session()->get('active_event_id');
        Log::info('Plans page accessed', ['active_event_id' => $activeEventId]);
        // Get active event
        $activeEvent = $activeEventId ? Event::with('country')->find($activeEventId) : null;

        // Get teams from active event
        $teams = [];
        if ($activeEventId) {
            $teams = Team::where('event_id', $activeEventId)
                ->with('country')
                ->get();
        }

        // Get plans for active event
        $plans = Plan::with(['movements.team'])
            ->when($activeEventId, function ($query) use ($activeEventId) {
                $query->where('event_id', $activeEventId);
            })
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'code' => $plan->code,
                    'date' => $plan->date,
                    'status' => $plan->status,
                    'movements_count' => $plan->movements->count(),
                    'teams_count' => $plan->movements->pluck('team_id')->unique()->count(),
                ];
            });

        return Inertia::render('Plans', [
            'schedule' => LmsData::schedule(),
            'activeEvent' => $activeEvent,
            'plans' => $plans,
            'movementTemplates' => MovementTemplate::with(['legs' => function ($query) {
                $query->orderBy('order');
            }])
                ->orderBy('code')
                ->get(),
            'teams' => $teams,
            'movementsByTeam' => [],
            'vehicles' => Vehicle::orderBy('name')->get(),
            'drivers' => Driver::orderBy('name')->get(),
            'supervisors' => User::whereHas('roles', function ($query) {
                $query->where('name', 'supervisor');
            })
                ->orWhereHas('roles', function ($query) {
                    $query->where('name', 'admin');
                })
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function library(Request $request): Response
    {
        $activeEventId = $request->session()->get('active_event_id');

        return Inertia::render('Library', [
            'checkpoints' => Checkpoint::withCount('checkpointTemplates as usage_count')
                ->orderBy('code')
                ->get(),
            'checkpointTemplates' => CheckpointTemplate::with(['checkpoints' => function ($query) {
                $query->orderBy('checkpoint_checkpoint_template.order');
            }])
                ->withCount('checkpoints as checkpoint_count')
                ->when($activeEventId, fn($q) => $q->where('event_id', $activeEventId))
                ->orderBy('code')
                ->get(),
            'movementTemplates' => MovementTemplate::with(['legs' => function ($query) {
                $query->orderBy('order');
            }])
                ->when($activeEventId, fn($q) => $q->where('event_id', $activeEventId))
                ->orderBy('code')
                ->get()
                ->map(function ($template) {
                    $template->setRelation('legs', $template->legs->map(function ($leg) {
                        return [
                            'id' => $leg->id,
                            'order' => $leg->order,
                            'from_location' => $leg->from_location,
                            'to_location' => $leg->to_location,
                            'leg_type' => $leg->leg_type,
                            'checkpoint_template_id' => $leg->checkpoint_template_id,
                            'transport_type' => $leg->vehicle_type,
                            'estimated_duration_minutes' => $leg->estimated_duration_minutes,
                        ];
                    }));
                    return $template;
                }),
        ]);
    }

    public function jobs(Request $request): Response
    {
        $activeEventId = $request->session()->get('active_event_id');

        // Build job query - scoped to the active event, if one is selected
        $jobsQuery = JobOperation::with([
            'event',
            'movement.team',
            'movement.flight.originAirport',
            'movement.flight.destinationAirport',
            'movement.accommodation',
            'movement.match.venue',
            'vehicle',
            'driver',
            'supervisor',
            'checkpoints.completedBy',
            'checkpoints.skippedBy',
            'checkpoints.checkpoint',
            'issues.reporter',
        ])
            ->when($activeEventId, fn ($q) => $q->where('event_id', $activeEventId));

        $jobs = $jobsQuery->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($job) {
                $movement = $job->movement;
                $team = $movement?->team;

                return [
                    'id' => $job->job_id ?? 'J-' . $job->id,
                    'team' => $team?->team_name ?? 'Unknown Team',
                    'code' => $team?->code ?? 'UNK',
                    'country_code' => $team?->country_id,
                    'flag' => $team?->flag,
                    'kind' => $movement?->kind ?? 'transfer',
                    'from' => $movement?->from_location ?? 'Unknown',
                    'to' => $movement?->to_location ?? 'Unknown',
                    'dep' => $movement?->window_start?->format('H:i') ?? '--:--',
                    'arr' => $movement?->flight?->scheduled_at?->format('H:i') ?? $movement?->window_start?->format('H:i') ?? '--:--',
                    'date' => $movement?->date?->format('Y-m-d') ?? $movement?->window_start?->format('Y-m-d') ?? now()->format('Y-m-d'),
                    'event_name' => $job->event?->name ?? null,
                    'event_code' => $job->event?->code ?? null,
                    'functional_area' => $job->functional_area ?? null,
                    'pax' => $movement?->passengers ?? $movement?->flight?->party_size_total ?? $team?->party_size_total ?? 0,
                    'vehicle' => $job->vehicle ? ($job->vehicle->code ?? $job->vehicle->plate_number ?? $job->vehicle->vehicle_type ?? 'Unassigned') : 'Unassigned',
                    'status' => $job->status,
                    'delay' => $movement?->delay_minutes,
                    'jobId' => $job->job_id,
                    'source' => $movement?->source ?? 'manual',
                    'checksComplete' => $job->checkpoints_completed ?? 0,
                    'checksTotal' => $job->checkpoints_total ?? 0,
                    'supervisor' => $job->supervisor?->name ?? 'Unassigned',
                    'supervisor_phone' => $job->supervisor?->phone ?? null,
                    'driver' => $job->driver?->name ?? 'Unassigned',
                    'driver_phone' => $job->driver?->phone ?? null,
                    'updated_at' => $job->updated_at?->format('Y-m-d H:i') ?? null,
                    'flight' => $movement?->flight ? [
                        'id' => $movement->flight->id,
                        'flight_number' => $movement->flight->flight_number,
                        'origin_airport' => $movement->flight->originAirport?->code ?? $movement->flight->origin_airport_id,
                        'destination_airport' => $movement->flight->destinationAirport?->code ?? $movement->flight->destination_airport_id,
                    ] : null,
                    'accommodation' => $movement?->accommodation ? [
                        'id' => $movement->accommodation->id,
                        'hotel_name' => $movement->accommodation->hotel_name,
                    ] : null,
                    'match' => $movement?->match ? [
                        'id' => $movement->match->id,
                        'match_number' => $movement->match->match_number,
                        'venue' => $movement->match->venue,
                    ] : null,
                    'team_data' => $team ? [
                        'hotel_name' => $team->hotel_name,
                        'origin_airport' => $team->originAirport?->code,
                        'destination_airport' => $team->destinationAirport?->code,
                        'training_ground' => $team->training_ground,
                    ] : null,
                    'team_data' => $team ? [
                        'hotel_name' => $team->hotel_name,
                        'origin_airport' => $team->originAirport?->code,
                        'destination_airport' => $team->destinationAirport?->code,
                        'training_ground' => $team->training_ground,
                    ] : null,
                    'issues' => $job->issues->map(fn ($issue) => [
                        'id' => $issue->id,
                        'label' => $issue->label(),
                        'severity' => $issue->severity,
                        'notes' => $issue->notes,
                        'reported_by' => $issue->reporter?->name ?? 'Unknown',
                        'reported_at' => $issue->created_at?->format('d M H:i'),
                        'resolved_at' => $issue->resolved_at?->format('d M H:i'),
                    ])->values(),
                    'checkpoints' => $job->checkpoints->map(function ($checkpoint) {
                        // Determine the time to display
                        $displayTime = null;

                        if ($checkpoint->state === 'done' && $checkpoint->completed_at) {
                            // Show actual completion time for done checkpoints
                            $displayTime = $checkpoint->completed_at->format('H:i');
                        } elseif ($checkpoint->scheduled_at) {
                            // Show scheduled time for pending/active checkpoints
                            $displayTime = $checkpoint->scheduled_at->format('H:i');
                        }

                        return [
                            'id' => $checkpoint->id, // Use actual database ID
                            'dbId' => $checkpoint->id, // Explicit database ID
                            'orderId' => 'CK' . $checkpoint->order, // Display ID
                            'name' => $checkpoint->name,
                            'state' => $checkpoint->state,
                            'status' => $checkpoint->state, // alias for compatibility
                            'at' => $displayTime,
                            'scheduled_at' => $checkpoint->scheduled_at?->format('H:i'),
                            'completed_at' => $checkpoint->completed_at?->format('H:i'),
                            'scheduled_ts' => $checkpoint->scheduled_at?->timestamp,
                            'completed_ts' => $checkpoint->completed_at?->timestamp,
                            'by' => $checkpoint->completedBy?->name ?? ($checkpoint->state === 'done' ? 'System' : null),
                            'skip_reason' => $checkpoint->skip_reason,
                            'skipped_at' => $checkpoint->skipped_at?->format('H:i'),
                            'skipped_by' => $checkpoint->skippedBy?->name,
                            'notes' => $checkpoint->notes,
                            'completion_method' => $checkpoint->completion_method,
                            'estimated_minutes' => $checkpoint->estimated_minutes,
                            'actual_duration_seconds' => $checkpoint->actual_duration_seconds,
                            'requires_photo' => $checkpoint->requires_photo,
                            'requires_signature' => $checkpoint->requires_signature,
                            'requires_baggage_count' => $checkpoint->checkpoint?->requires_baggage_count ?? false,
                            'planned_bags' => $checkpoint->planned_bags,
                            'bags_loaded' => $checkpoint->bags_loaded,
                            'food_bags' => $checkpoint->food_bags,
                            'oversized_pieces' => $checkpoint->oversized_pieces,
                            'has_photo' => $checkpoint->photo_path ? true : false,
                            'has_signature' => $checkpoint->signature_path ? true : false,
                            'photo_url' => $checkpoint->photo_path ? route('checkpoint.photo', $checkpoint->id) : null,
                            'signature_url' => $checkpoint->signature_path ? route('checkpoint.signature', $checkpoint->id) : null,
                        ];
                    })->toArray(),
                ];
            });

        return Inertia::render('Jobs', [
            'schedule' => $jobs,
        ]);
    }

    public function jobsMobile(Request $request): Response
    {
        $activeEventId = $request->session()->get('active_event_id');

        // "My Jobs" is a field supervisor's active worklist — only jobs
        // currently underway, real data only (no mock/demo rows).
        $dbJobs = JobOperation::with([
            'movement.team',
            'movement.flight.originAirport',
            'movement.flight.destinationAirport',
            'movement.accommodation',
            'movement.match.venue',
            'vehicle',
            'driver',
            'supervisor',
            'checkpoints.completedBy',
            'checkpoints.skippedBy',
            'checkpoints.checkpoint'
        ])
            ->join('movements', 'jobs_operations.movement_id', '=', 'movements.id')
            ->where('jobs_operations.status', 'in-progress')
            ->when($activeEventId, fn ($q) => $q->where('jobs_operations.event_id', $activeEventId))
            ->orderBy('movements.window_start', 'asc')
            ->select('jobs_operations.*')
            ->get()
            ->map(function ($job) {
                $movement = $job->movement;
                $team = $movement?->team;

                // Get the latest unactioned (pending) checkpoint
                $nextCheckpoint = $job->checkpoints()
                    ->where('state', 'pending')
                    ->orderBy('order', 'asc')
                    ->first();

                return [
                    'id' => $job->job_id ?? 'J-' . $job->id,
                    'jobId' => $job->job_id,
                    'team' => $team?->team_name ?? 'Unknown Team',
                    'code' => $team?->code ?? 'UNK',
                    'kind' => $movement?->kind ?? 'transfer',
                    'from' => $movement?->from_location ?? 'Unknown',
                    'to' => $movement?->to_location ?? 'Unknown',
                    'dep' => $movement?->window_start?->format('H:i') ?? '--:--',
                    'arr' => $movement?->flight?->scheduled_at?->format('H:i') ?? $movement?->window_start?->format('H:i') ?? '--:--',
                    'window_start' => $movement?->window_start?->format('Y-m-d H:i') ?? null,
                    'pax' => $movement?->passengers ?? $movement?->flight?->party_size_total ?? $team?->party_size_total ?? 0,
                    'vehicle' => $job->vehicle ? ($job->vehicle->code ?? $job->vehicle->plate_number ?? $job->vehicle->vehicle_type ?? 'Unassigned') : 'Unassigned',
                    'status' => $job->status,
                    'functional_area' => $job->functional_area ?? null,
                    'delay' => $movement?->delay_minutes,
                    'next_checkpoint' => $nextCheckpoint ? [
                        'id' => $nextCheckpoint->id,
                        'name' => $nextCheckpoint->name,
                        'scheduled_at' => $nextCheckpoint->scheduled_at?->format('H:i'),
                    ] : null,
                    'flight' => $movement?->flight ? [
                        'id' => $movement->flight->id,
                        'flight_number' => $movement->flight->flight_number,
                        'origin_airport' => $movement->flight->originAirport?->code ?? $movement->flight->origin_airport_id,
                        'destination_airport' => $movement->flight->destinationAirport?->code ?? $movement->flight->destination_airport_id,
                    ] : null,
                    'accommodation' => $movement?->accommodation ? [
                        'id' => $movement->accommodation->id,
                        'hotel_name' => $movement->accommodation->hotel_name,
                    ] : null,
                    'match' => $movement?->match ? [
                        'id' => $movement->match->id,
                        'match_number' => $movement->match->match_number,
                        'venue' => $movement->match->venue,
                    ] : null,
                    'team_data' => $team ? [
                        'hotel_name' => $team->hotel_name,
                        'origin_airport' => $team->originAirport?->code,
                        'destination_airport' => $team->destinationAirport?->code,
                        'training_ground' => $team->training_ground,
                    ] : null,
                ];
            });

        return Inertia::render('JobsMobile', [
            'schedule' => $dbJobs,
        ]);
    }

    public function jobMobileDetail(string $id): Response
    {
        // Try to find in database first
        $dbJob = null;
        $dbCheckpoints = [];

        $jobOperation = JobOperation::with([
            'event',
            'movement.team',
            'movement.flight.originAirport',
            'movement.flight.destinationAirport',
            'movement.accommodation',
            'movement.match.venue',
            'vehicle',
            'driver',
            'supervisor',
            'checkpoints.completedBy',
            'checkpoints.skippedBy',
            'checkpoints.checkpoint'
        ])->where('job_id', $id)
            ->orWhere('id', $id)
            ->first();

        if ($jobOperation) {
            $movement = $jobOperation->movement;
            $team = $movement?->team;

            $dbJob = [
                'id' => $jobOperation->job_id ?? 'J-' . $jobOperation->id,
                'team' => $team?->team_name ?? 'Unknown Team',
                'code' => $team?->code ?? 'UNK',
                'kind' => $movement?->kind ?? 'transfer',
                'from' => $movement?->from_location ?? 'Unknown',
                'to' => $movement?->to_location ?? 'Unknown',
                'dep' => $movement?->window_start?->format('H:i') ?? '--:--',
                'arr' => $movement?->flight?->scheduled_at?->format('H:i') ?? $movement?->window_start?->format('H:i') ?? '--:--',
                'pax' => $movement?->passengers ?? $movement?->flight?->party_size_total ?? $team?->party_size_total ?? 0,
                'vehicle' => $jobOperation->vehicle ? ($jobOperation->vehicle->code ?? $jobOperation->vehicle->plate_number ?? $jobOperation->vehicle->vehicle_type ?? 'Unassigned') : 'Unassigned',
                'status' => $jobOperation->status,
                'functional_area' => $jobOperation->functional_area ?? null,
                'flight' => $movement?->flight ? [
                    'id' => $movement->flight->id,
                    'flight_number' => $movement->flight->flight_number,
                    'origin_airport' => $movement->flight->originAirport?->code ?? $movement->flight->origin_airport_id,
                    'destination_airport' => $movement->flight->destinationAirport?->code ?? $movement->flight->destination_airport_id,
                ] : null,
                'accommodation' => $movement?->accommodation ? [
                    'id' => $movement->accommodation->id,
                    'hotel_name' => $movement->accommodation->hotel_name,
                ] : null,
                'match' => $movement?->match ? [
                    'id' => $movement->match->id,
                    'match_number' => $movement->match->match_number,
                    'venue' => $movement->match->venue,
                ] : null,
                'team_data' => $team ? [
                    'hotel_name' => $team->hotel_name,
                    'origin_airport' => $team->originAirport?->code,
                    'destination_airport' => $team->destinationAirport?->code,
                    'training_ground' => $team->training_ground,
                ] : null,
                'delay' => $movement?->delay_minutes,
                'source' => 'database',
            ];

            $dbCheckpoints = $jobOperation->checkpoints->map(function ($checkpoint, $index) use ($jobOperation) {
                // Determine status. A skipped checkpoint is settled, not outstanding,
                // so it counts towards the position of the next active one.
                $settledCount = $jobOperation->checkpoints->whereIn('state', ['done', 'skipped'])->count();
                $status = 'pending';
                if ($checkpoint->state === 'done') {
                    $status = 'done';
                } elseif ($checkpoint->state === 'skipped') {
                    $status = 'skipped';
                } elseif ($index === $settledCount) {
                    $status = 'active';
                }

                return [
                    'id' => $checkpoint->id,
                    'label' => $checkpoint->name,
                    'status' => $status,
                    'time' => $checkpoint->scheduled_at?->format('H:i') ?? '--:--',
                    'actual' => $checkpoint->completed_at?->format('H:i'),
                    'skip_reason' => $checkpoint->skip_reason,
                    'skipped_at' => $checkpoint->skipped_at?->format('H:i'),
                    'skipped_by' => $checkpoint->skippedBy?->name,
                    'requires_photo' => $checkpoint->requires_photo,
                    'requires_signature' => $checkpoint->requires_signature,
                    'requires_baggage_count' => $checkpoint->checkpoint?->requires_baggage_count ?? false,
                    'planned_bags' => $checkpoint->planned_bags,
                    'bags_loaded' => $checkpoint->bags_loaded,
                    'food_bags' => $checkpoint->food_bags,
                    'oversized_pieces' => $checkpoint->oversized_pieces,
                    'has_photo' => $checkpoint->photo_path ? true : false,
                    'has_signature' => $checkpoint->signature_path ? true : false,
                    'photo_url' => $checkpoint->photo_path ? route('checkpoint.photo', $checkpoint->id) : null,
                    'signature_url' => $checkpoint->signature_path ? route('checkpoint.signature', $checkpoint->id) : null,
                ];
            })->toArray();
        }

        // Get mock data as fallback
        $mockJob = collect(LmsData::schedule())->firstWhere('id', $id)
            ?? collect(LmsData::schedule())->first();

        if ($mockJob) {
            $mockJob['source'] = 'mock';
        }

        $mockCheckpoints = LmsData::checkpoints();

        return Inertia::render('JobMobileDetail', [
            'job' => $dbJob ?? $mockJob,
            'checkpoints' => !empty($dbCheckpoints) ? $dbCheckpoints : $mockCheckpoints,
            'mockJob' => $mockJob,
            'mockCheckpoints' => $mockCheckpoints,
        ]);
    }

    public function jobDetail(string $id): Response|RedirectResponse
    {
        $jobOperation = JobOperation::with([
            'event',
            'movement.team',
            'movement.flight',
            'movement',
            'vehicle',
            'driver',
            'supervisor',
            'checkpoints.completedBy',
            'checkpoints.skippedBy'
        ])->find($id);

        // If job not found, redirect to jobs page
        if (!$jobOperation) {
            return redirect()->route('jobs')->with('error', 'Job not found');
        }

        $movement = $jobOperation->movement;
        $team = $movement->team ?? null;

        // Transform job data for frontend
        $job = [
            'id' => $jobOperation->id,
            'code' => $team ? $team->code : 'N/A',
            'team' => $team ? $team->team_name : 'Unknown Team',
            'country_code' => $team?->country_id,
            'flag' => $team?->flag,
            'status' => $jobOperation->status,
            'delay' => null, // Calculate delay if needed
            'from' => $movement->origin ?? 'Unknown',
            'to' => $movement->destination ?? 'Unknown',
            'vehicle' => $jobOperation->vehicle ? $jobOperation->vehicle->code : 'N/A',
            'pax' => $movement->passenger_count ?? 0,
            'dep' => $movement->window_start ? \Carbon\Carbon::parse($movement->window_start)->format('H:i') : 'N/A',
            'arr' => $movement->flight?->scheduled_at?->format('H:i') ?? ($movement->window_start ? \Carbon\Carbon::parse($movement->window_start)->format('H:i') : 'N/A'),
        ];

        // Transform checkpoints for frontend
        $checkpoints = $jobOperation->checkpoints->map(function ($cp, $index) use ($jobOperation, $movement) {
            // Determine status. A skipped checkpoint is settled, not outstanding,
            // so it counts towards the position of the next active one.
            $settledCount = $jobOperation->checkpoints->whereIn('state', ['done', 'skipped'])->count();
            $status = 'pending';
            if ($cp->state === 'done') {
                $status = 'done';
            } elseif ($cp->state === 'skipped') {
                $status = 'skipped';
            } elseif ($index === $settledCount) {
                // Next unsettled checkpoint is active
                $status = 'active';
            }

            // Calculate estimated time based on checkpoint position
            $totalCheckpoints = $jobOperation->checkpoints->count();
            $scheduledTime = '--:--';

            if ($movement->window_start && $movement->window_end && $totalCheckpoints > 0) {
                $departure = \Carbon\Carbon::parse($movement->window_start);
                $arrival = \Carbon\Carbon::parse($movement->window_end);
                $totalMinutes = $departure->diffInMinutes($arrival);

                // Distribute checkpoints evenly across the journey
                $minutesPerCheckpoint = $totalMinutes / ($totalCheckpoints + 1);
                $estimatedTime = $departure->copy()->addMinutes($minutesPerCheckpoint * ($index + 1));
                $scheduledTime = $estimatedTime->format('H:i');
            }

            return [
                'id' => $cp->id,
                'label' => $cp->name,
                'status' => $status,
                'time' => $scheduledTime,
                'actual' => $cp->completed_at ? \Carbon\Carbon::parse($cp->completed_at)->format('H:i') : null,
                'skip_reason' => $cp->skip_reason,
                'skipped_at' => $cp->skipped_at?->format('H:i'),
                'skipped_by' => $cp->skippedBy?->name,
                'requires_photo' => $cp->requires_photo,
                'requires_signature' => $cp->requires_signature,
                'has_photo' => $cp->photo_path ? true : false,
                'has_signature' => $cp->signature_path ? true : false,
                'photo_url' => $cp->photo_path ? route('checkpoint.photo', $cp->id) : null,
                'signature_url' => $cp->signature_path ? route('checkpoint.signature', $cp->id) : null,
            ];
        });

        // Build crew members array
        $crewMembers = [];

        if ($jobOperation->supervisor) {
            $crewMembers[] = [
                'name' => $jobOperation->supervisor->name,
                'role' => 'Supervisor',
                'initials' => $this->getInitials($jobOperation->supervisor->name),
                'onShift' => true, // Could be determined from actual shift data
            ];
        }

        if ($jobOperation->driver) {
            $vehicleCode = $jobOperation->vehicle ? $jobOperation->vehicle->code : '';
            $crewMembers[] = [
                'name' => $jobOperation->driver->name,
                'role' => 'Driver' . ($vehicleCode ? " · {$vehicleCode}" : ''),
                'initials' => $this->getInitials($jobOperation->driver->name),
                'onShift' => true, // Could be determined from actual shift data
            ];
        }

        return Inertia::render('JobDetail', [
            'job' => $job,
            'checkpoints' => $checkpoints,
            'crewMembers' => $crewMembers,
        ]);
    }

    /**
     * Get initials from a name
     */
    private function getInitials(string $name): string
    {
        $parts = explode(' ', trim($name));
        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 2));
        }
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
    }

    public function tracker(): Response
    {
        return Inertia::render('Tracker', [
            'mapNodes' => LmsData::mapNodes(),
            'liveJobs' => LmsData::liveJobs(),
            'schedule' => LmsData::schedule(),
        ]);
    }

    public function fleet(): Response
    {
        return Inertia::render('Fleet', [
            'vehicles' => Vehicle::all(),
            'providers' => FleetProvider::withCount(['vehicles', 'drivers'])->get(),
            'drivers' => Driver::with('provider:id,name')->get(),
        ]);
    }

    public function contacts(): Response
    {
        return Inertia::render('Contacts', [
            'contacts' => Contact::active()->get(),
        ]);
    }

    public function storeContact(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'org' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'on_shift' => 'boolean',
        ]);

        Contact::create($validated);

        return redirect()->route('contacts');
    }

    public function updateContact(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'org' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'on_shift' => 'boolean',
        ]);

        $contact = Contact::findOrFail($id);
        $contact->update($validated);

        return redirect()->route('contacts');
    }

    public function destroyContact($id)
    {
        $contact = Contact::findOrFail($id);
        $contact->delete();

        return redirect()->route('contacts');
    }

    public function notifications(Request $request, NotificationFeedService $feed): Response
    {
        return Inertia::render('Notifications', [
            'notifications' => $feed->recent($request->session()->get('active_event_id')),
        ]);
    }

    public function email(Request $request, DailySummaryService $summary): Response
    {
        $date = $request->query('date');
        try {
            $date = $date ? \Carbon\Carbon::parse($date)->toDateString() : now()->toDateString();
        } catch (\Exception) {
            $date = now()->toDateString();
        }

        return Inertia::render('Email', $summary->build(
            $request->session()->get('active_event_id'),
            $date,
        ));
    }

    public function sendEmail(Request $request, DailySummaryService $summary): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $eventId = $request->session()->get('active_event_id');
        $date = isset($validated['date'])
            ? \Carbon\Carbon::parse($validated['date'])->toDateString()
            : now()->toDateString();

        $snapshot = $summary->build($eventId, $date);
        $recipients = collect($snapshot['recipients'])->pluck('email')->filter()->values();

        if ($recipients->isEmpty()) {
            return redirect()->back()->with('error', 'No recipients are assigned to this event.');
        }

        try {
            Mail::to($recipients->all())->send(new DailySummaryMail($snapshot));
        } catch (\Throwable $e) {
            Log::error('Daily summary email failed', ['date' => $date, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not send the summary: '.$e->getMessage());
        }

        AuditLog::record(
            'Daily summary sent',
            'Email · '.$date,
            $recipients->count().' recipient(s)',
            null,
            $eventId ? (int) $eventId : null,
        );

        return redirect()->back()->with('success', "Daily summary sent to {$recipients->count()} recipient(s).");
    }

    public function audit(): Response    {
        $entries = AuditLog::latest()->limit(500)->get()->map(fn (AuditLog $log) => [
            't' => $log->created_at->format('d M H:i'),
            'who' => $log->user_name,
            'role' => $log->user_role,
            'action' => $log->action,
            'target' => $log->target ?? '',
            'meta' => $log->meta ?? '',
        ])->all();

        return Inertia::render('Audit', [
            'audit' => $entries,
        ]);
    }

    /**
     * Override a checkpoint status (privileged action)
     */
    public function overrideCheckpoint(Request $request, string $checkpointId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'state' => 'required|in:done,skipped',
                'actual_time' => 'nullable|date_format:H:i',
                'exclude_date' => 'nullable|boolean',
                'reason' => 'required|string|max:255',
                'notes' => 'nullable|string',
                'planned_bags' => 'nullable|integer|min:0',
                'bags_loaded' => 'nullable|integer|min:0',
                'food_bags' => 'nullable|integer|min:0',
                'oversized_pieces' => 'nullable|integer|min:0',
                'photo' => 'nullable|image|max:10240', // Max 10MB
                'signature_data' => 'nullable|string', // Base64 encoded image
                'update_flight_actual' => 'nullable|string', // Flag to update team flight actual_at
            ]);

            $checkpoint = JobCheckpoint::with(['job.movement', 'checkpoint'])->findOrFail($checkpointId);

            $this->authorize('override', $checkpoint->job);

            $checkpoint = app(JobLifecycleService::class)->overrideCheckpoint(
                $checkpoint,
                $request->user(),
                [
                    'state' => $validated['state'],
                    'reason' => $validated['reason'],
                    'notes' => $validated['notes'] ?? null,
                    'actual_time' => $validated['actual_time'] ?? null,
                    'exclude_date' => $request->boolean('exclude_date'),
                    'photo' => $request->hasFile('photo') && $request->file('photo')->isValid()
                        ? $request->file('photo')
                        : null,
                    'signature' => $request->input('signature_data'),
                    'planned_bags' => $validated['planned_bags'] ?? null,
                    'bags_loaded' => $validated['bags_loaded'] ?? null,
                    'food_bags' => $validated['food_bags'] ?? null,
                    'oversized_pieces' => $validated['oversized_pieces'] ?? null,
                ],
            );

            if ($request->filled('update_flight_actual') && $checkpoint->completed_at) {
                $this->syncTeamFlightArrival($checkpoint);
            }

            return response()->json([
                'success' => true,
                'message' => 'Checkpoint updated successfully',
                'checkpoint' => $checkpoint,
            ]);
        } catch (\Illuminate\Validation\ValidationException
            | \Illuminate\Auth\Access\AuthorizationException
            | \Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // These carry their own HTTP status; the catch-all below would hide it.
            throw $e;
        } catch (\Exception $e) {
            Log::error('Override checkpoint failed', [
                'checkpoint_id' => $checkpointId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to override checkpoint: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mirror a confirmed PMA arrival checkpoint onto the team's arrival flight.
     */
    private function syncTeamFlightArrival(JobCheckpoint $checkpoint): void
    {
        $movement = $checkpoint->job?->movement;

        if (! $movement || ! $movement->team_id || ! $movement->event_id) {
            return;
        }

        $teamFlight = \App\Models\TeamFlight::where('event_id', $movement->event_id)
            ->where('team_id', $movement->team_id)
            ->where('direction', 'arrival')
            ->first();

        if (! $teamFlight) {
            return;
        }

        $teamFlight->update(['actual_at' => $checkpoint->completed_at]);

        Log::info('Updated team flight actual_at for PMA Arrival', [
            'team_flight_id' => $teamFlight->id,
            'actual_at' => $checkpoint->completed_at,
            'checkpoint_id' => $checkpoint->id,
        ]);
    }

    /**
     * Complete a checkpoint (from mobile app)
     */
    public function completeCheckpoint(Request $request, string $checkpointId): JsonResponse
    {
        $validated = $request->validate([
            'actual_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:500',
            'signature' => 'nullable|string',
            'photo' => 'nullable|string',
            'planned_bags' => 'nullable|integer|min:0',
            'bags_loaded' => 'nullable|integer|min:0',
            'food_bags' => 'nullable|integer|min:0',
            'oversized_pieces' => 'nullable|integer|min:0',
        ]);

        $checkpoint = JobCheckpoint::with(['job.movement', 'checkpoint'])->findOrFail($checkpointId);

        $this->authorize('update', $checkpoint->job);

        try {
            $checkpoint = app(JobLifecycleService::class)->completeCheckpoint(
                $checkpoint,
                $request->user(),
                [
                    'actual_time' => $validated['actual_time'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'photo' => $validated['photo'] ?? null,
                    'signature' => $validated['signature'] ?? null,
                    'planned_bags' => $validated['planned_bags'] ?? null,
                    'bags_loaded' => $validated['bags_loaded'] ?? null,
                    'food_bags' => $validated['food_bags'] ?? null,
                    'oversized_pieces' => $validated['oversized_pieces'] ?? null,
                ],
                'mobile',
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Checkpoint completed successfully',
            'checkpoint' => $checkpoint,
        ]);
    }

    /**
     * Retrieve checkpoint photo (with backward compatibility)
     */
    public function getCheckpointPhoto(string $checkpointId)
    {
        $checkpoint = JobCheckpoint::with('job')->findOrFail($checkpointId);

        $this->authorize('view', $checkpoint->job);

        if (!$checkpoint->photo_path) {
            abort(404, 'Photo not found');
        }

        $filePath = $checkpoint->photo_path;

        // Check new location first, then fall back to old location
        if (!Storage::disk('local')->exists($filePath)) {
            // Try old location for backward compatibility
            $oldPath = str_replace('jobs/' . $checkpoint->job_id . '/', '', $filePath);
            if (Storage::disk('local')->exists($oldPath)) {
                $filePath = $oldPath;
            } else {
                abort(404, 'Photo file not found');
            }
        }

        $file = Storage::disk('local')->get($filePath);
        $extension = pathinfo($checkpoint->photo_path, PATHINFO_EXTENSION);
        $mimeType = $this->getMimeType($extension);

        return response($file, 200)->header('Content-Type', $mimeType);
    }

    /**
     * Retrieve checkpoint signature (with backward compatibility)
     */
    public function getCheckpointSignature(string $checkpointId)
    {
        $checkpoint = JobCheckpoint::with('job')->findOrFail($checkpointId);

        $this->authorize('view', $checkpoint->job);

        if (!$checkpoint->signature_path) {
            abort(404, 'Signature not found');
        }

        $filePath = $checkpoint->signature_path;

        // Check new location first, then fall back to old location
        if (!Storage::disk('local')->exists($filePath)) {
            // Try old location for backward compatibility
            $oldPath = str_replace('jobs/' . $checkpoint->job_id . '/', '', $filePath);
            if (Storage::disk('local')->exists($oldPath)) {
                $filePath = $oldPath;
            } else {
                abort(404, 'Signature file not found');
            }
        }

        $file = Storage::disk('local')->get($filePath);
        $extension = pathinfo($checkpoint->signature_path, PATHINFO_EXTENSION);
        $mimeType = $this->getMimeType($extension);

        return response($file, 200)->header('Content-Type', $mimeType);
    }

    /**
     * Get MIME type from file extension
     */
    private function getMimeType(string $extension): string
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'image/png';
    }

    /**
     * Marks a field-reported issue as dealt with.
     */
    public function resolveJobIssue(JobIssue $issue): RedirectResponse
    {
        // An orphaned issue has no job to authorize against, so nobody resolves it.
        abort_unless($issue->job, 404);

        $this->authorize('update', $issue->job);

        if ($issue->resolved_at) {
            return back()->with('error', 'That issue is already resolved.');
        }

        $issue->update(['resolved_at' => now()]);

        AuditLog::record(
            action: 'Issue resolved',
            target: ($issue->job?->job_id ?? 'JOB').' · '.$issue->label(),
            meta: $issue->notes,
            subject: $issue->job,
            eventId: $issue->event_id,
        );

        return back()->with('success', 'Issue resolved');
    }

    public function updateJobStatus(Request $request, $jobId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(JobOperation::STATUSES)],
        ]);

        // The frontend passes the job's display id (the job_id string, e.g.
        // "JOB-20260726-0005"), not the numeric primary key — look up by
        // that first, falling back to the "J-{id}" placeholder format used
        // when a job has no job_id, and finally the raw numeric id itself.
        $job = JobOperation::where('job_id', $jobId)->first();
        if (!$job && str_starts_with($jobId, 'J-')) {
            $job = JobOperation::find(substr($jobId, 2));
        }
        if (!$job) {
            $job = JobOperation::find($jobId);
        }
        if (!$job) {
            abort(404, 'Job not found');
        }

        $this->authorize('update', $job);

        $from = $job->status;
        $to = $validated['status'];

        if ($from === $to) {
            return response()->json(['success' => true, 'message' => "Job is already {$to}"]);
        }

        try {
            app(JobLifecycleService::class)->transitionStatus($job, $to, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Job status updated to {$to}",
            'status' => $to,
        ]);
    }
}
