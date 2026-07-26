<?php

namespace App\Http\Controllers;

use App\Data\LmsData;
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
use App\Models\User;
use App\Models\Event;
use App\Models\Plan;
use App\Models\Movement;
use App\Services\CheckpointUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LmsController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('Dashboard', [
            'kpis'          => LmsData::kpis(),
            'schedule'      => LmsData::schedule(),
            'notifications' => LmsData::notifications(),
            'audit'         => LmsData::audit(),
        ]);
    }

    public function schedule(): Response
    {
        return Inertia::render('Schedule', [
            'schedule' => LmsData::schedule(),
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

    public function library(): Response
    {
        return Inertia::render('Library', [
            'checkpoints' => Checkpoint::withCount('checkpointTemplates as usage_count')
                ->orderBy('code')
                ->get(),
            'checkpointTemplates' => CheckpointTemplate::with(['checkpoints' => function ($query) {
                $query->orderBy('checkpoint_checkpoint_template.order');
            }])
                ->withCount('checkpoints as checkpoint_count')
                ->orderBy('code')
                ->get(),
            'movementTemplates' => MovementTemplate::with(['legs' => function ($query) {
                $query->orderBy('order');
            }])
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
        // Build job query - show all jobs across all plans
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
            'checkpoints.checkpoint'
        ]);

        $jobs = $jobsQuery->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($job) {
                $movement = $job->movement;
                $team = $movement?->team;

                return [
                    'id' => $job->job_id ?? 'J-' . $job->id,
                    'team' => $team?->team_name ?? 'Unknown Team',
                    'code' => $team?->code ?? 'UNK',
                    'kind' => $movement?->kind ?? 'transfer',
                    'from' => $movement?->from_location ?? 'Unknown',
                    'to' => $movement?->to_location ?? 'Unknown',
                    'dep' => $movement?->window_start?->format('H:i') ?? '--:--',
                    'arr' => $movement?->flight?->scheduled_at?->format('H:i') ?? $movement?->window_end?->format('H:i') ?? '--:--',
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

    public function jobsMobile(): Response
    {
        // Get database jobs
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
            'checkpoints.checkpoint'
        ])
            ->join('movements', 'jobs_operations.movement_id', '=', 'movements.id')
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
                    'arr' => $movement?->flight?->scheduled_at?->format('H:i') ?? $movement?->window_end?->format('H:i') ?? '--:--',
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
                    'source' => 'database',
                ];
            });

        // Get mock data
        $mockJobs = collect(LmsData::schedule())->map(function ($job) {
            return array_merge($job, ['source' => 'mock']);
        });

        // Combine both sources
        $allJobs = $dbJobs->concat($mockJobs);

        return Inertia::render('JobsMobile', [
            'schedule' => $allJobs,
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
                'arr' => $movement?->flight?->scheduled_at?->format('H:i') ?? $movement?->window_end?->format('H:i') ?? '--:--',
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
                // Determine status
                $status = 'pending';
                if ($checkpoint->state === 'done') {
                    $status = 'done';
                } elseif ($index === $jobOperation->checkpoints->where('state', 'done')->count()) {
                    $status = 'active';
                }

                return [
                    'id' => $checkpoint->id,
                    'label' => $checkpoint->name,
                    'status' => $status,
                    'time' => $checkpoint->scheduled_at?->format('H:i') ?? '--:--',
                    'actual' => $checkpoint->completed_at?->format('H:i'),
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
            'checkpoints.completedBy'
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
            'status' => $jobOperation->status,
            'delay' => null, // Calculate delay if needed
            'from' => $movement->origin ?? 'Unknown',
            'to' => $movement->destination ?? 'Unknown',
            'vehicle' => $jobOperation->vehicle ? $jobOperation->vehicle->code : 'N/A',
            'pax' => $movement->passenger_count ?? 0,
            'dep' => $movement->window_start ? \Carbon\Carbon::parse($movement->window_start)->format('H:i') : 'N/A',
            'arr' => $movement->flight?->scheduled_at?->format('H:i') ?? ($movement->window_end ? \Carbon\Carbon::parse($movement->window_end)->format('H:i') : 'N/A'),
        ];

        // Transform checkpoints for frontend
        $checkpoints = $jobOperation->checkpoints->map(function ($cp, $index) use ($jobOperation, $movement) {
            // Determine status
            $status = 'pending';
            if ($cp->state === 'done') {
                $status = 'done';
            } elseif ($index === $jobOperation->checkpoints->where('state', 'done')->count()) {
                // Next uncompleted checkpoint is active
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
            'providers' => FleetProvider::all(),
            'drivers' => Driver::all(),
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

    public function notifications(): Response
    {
        return Inertia::render('Notifications', [
            'notifications' => LmsData::notifications(),
        ]);
    }

    public function email(): Response
    {
        return Inertia::render('Email', [
            'schedule' => LmsData::schedule(),
            'kpis'     => LmsData::kpis(),
        ]);
    }

    public function audit(): Response
    {
        return Inertia::render('Audit', [
            'audit' => LmsData::audit(),
        ]);
    }

    /**
     * Override a checkpoint status (privileged action)
     */
    public function overrideCheckpoint(Request $request, string $checkpointId): JsonResponse
    {
        $uploadService = app(CheckpointUploadService::class);
        try {
            Log::info('Override checkpoint called', [
                'checkpoint_id' => $checkpointId,
                'has_photo' => $request->hasFile('photo'),
                'request_all' => $request->all(), // Log all request data for debugging (including files info, but not file contents)
                // 'request_all' => $request->except(['photo', 'signature_data'])
            ]);

            $validated = $request->validate([
                'state' => 'required|in:done,skipped',
                'actual_time' => 'nullable|date_format:H:i',
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

            $checkpoint = JobCheckpoint::with(['job', 'checkpoint'])->findOrFail($checkpointId);

            // Get current user or use a default user for now (supervisor)
            // In production, you'd use auth()->user()
            $user = User::first(); // TODO: Replace with actual authenticated user

            $updateData = [
                'state' => $validated['state'],
                'was_overridden' => true,
                'override_reason' => $validated['reason'],
                'override_notes' => $validated['notes'] ?? null,
                'overridden_by' => $user->id,
                'overridden_at' => now(),
            ];

            // Handle based on state
            if (($validated['state'] === 'done' || $validated['state'] === 'success') && $validated['actual_time']) {
                $updateData['override_actual_time'] = $validated['actual_time'];
                
                // Use TODAY's date as the base, not the scheduled date
                list($hours, $minutes) = explode(':', $validated['actual_time']);
                $actualDateTime = \Carbon\Carbon::today()->setTime((int)$hours, (int)$minutes, 0);
                
                // Handle midnight crossover: if actual time is very early (e.g., 00:39) and scheduled time
                // was late (e.g., 14:19), assume the completion happened early next day
                if ($checkpoint->scheduled_at) {
                    $scheduledDateTime = \Carbon\Carbon::parse($checkpoint->scheduled_at);
                    $scheduledHour = $scheduledDateTime->hour;
                    $actualHour = (int)$hours;
                    
                    // If scheduled late (after 18:00) and actual is very early (before 06:00),
                    // it likely crossed midnight to tomorrow
                    if ($scheduledHour >= 18 && $actualHour < 6) {
                        $actualDateTime->addDay();
                    }
                }
                
                $updateData['completed_at'] = $actualDateTime;
                $updateData['completed_by'] = $user->id;
                $updateData['completion_method'] = 'web';
                $updateData['notes'] = $validated['notes'] ?? null;

                // Add baggage count if provided
                if (isset($validated['planned_bags'])) {
                    $updateData['planned_bags'] = $validated['planned_bags'];
                }
                if (isset($validated['bags_loaded'])) {
                    $updateData['bags_loaded'] = $validated['bags_loaded'];
                }
                if (isset($validated['food_bags'])) {
                    $updateData['food_bags'] = $validated['food_bags'];
                }
                if (isset($validated['oversized_pieces'])) {
                    $updateData['oversized_pieces'] = $validated['oversized_pieces'];
                }

                // Handle photo upload
                if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                    $updateData['photo_path'] = $uploadService->storePhoto(
                        $request->file('photo'),
                        $checkpointId,
                        $checkpoint->job_id
                    );
                }

                // Handle signature data (base64)
                if ($request->filled('signature_data')) {
                    $signaturePath = $uploadService->storeSignature(
                        $request->input('signature_data'),
                        $checkpointId,
                        $checkpoint->job_id
                    );

                    if ($signaturePath) {
                        $updateData['signature_path'] = $signaturePath;
                    }
                }

                // Calculate duration based on scheduled time
                if ($checkpoint->scheduled_at) {
                    $scheduledTime = \Carbon\Carbon::parse($checkpoint->scheduled_at);
                    $updateData['actual_duration_seconds'] = abs($actualDateTime->diffInSeconds($scheduledTime));
                }
                // Fallback: If checkpoint was started, use start time
                elseif ($checkpoint->started_at) {
                    $updateData['actual_duration_seconds'] = $actualDateTime->diffInSeconds($checkpoint->started_at);
                }
                // Last resort: use estimated duration
                elseif ($checkpoint->estimated_minutes) {
                    $updateData['actual_duration_seconds'] = $checkpoint->estimated_minutes * 60;
                }

                // Check if on time based on movement's window_end (not individual checkpoint scheduled_at)
                $job = $checkpoint->job;
                $movement = $job->movement;
                if ($movement && $movement->window_end) {
                    $windowEnd = \Carbon\Carbon::parse($movement->window_end);
                    $delayMinutes = $actualDateTime->diffInMinutes($windowEnd, false);
                    // On-time if completed before or at window_end
                    $updateData['is_on_time'] = $delayMinutes <= 0;
                    // Only store positive delays (after window_end)
                    $updateData['delay_minutes'] = max(0, -$delayMinutes);
                }
            } elseif ($validated['state'] === 'skipped') {
                $updateData['skip_reason'] = $validated['reason'];
                $updateData['skipped_by'] = $user->id;
                $updateData['skipped_at'] = now();
                $updateData['notes'] = $validated['notes'] ?? null;
            } elseif ($validated['state'] === 'missed') {
                $updateData['skip_reason'] = $validated['reason'];
                $updateData['skipped_by'] = $user->id;
                $updateData['skipped_at'] = now();
                $updateData['exception_type'] = 'missed';
                $updateData['notes'] = $validated['notes'] ?? null;
            }

            $checkpoint->update($updateData);

            // Update team flight actual_at if this is PMA Arrival checkpoint
            if ($request->filled('update_flight_actual') && 
                $validated['state'] === 'done' && 
                isset($updateData['completed_at'])) {
                
                $job = $checkpoint->job;
                $movement = $job->movement;
                
                if ($movement && $movement->team_id && $movement->event_id) {
                    // Find the arrival flight for this team
                    $teamFlight = \App\Models\TeamFlight::where('event_id', $movement->event_id)
                        ->where('team_id', $movement->team_id)
                        ->where('direction', 'arrival')
                        ->first();
                    
                    if ($teamFlight) {
                        $teamFlight->update([
                            'actual_at' => $updateData['completed_at'],
                        ]);
                        
                        Log::info('Updated team flight actual_at for PMA Arrival', [
                            'team_flight_id' => $teamFlight->id,
                            'actual_at' => $updateData['completed_at'],
                            'checkpoint_id' => $checkpointId,
                        ]);
                    }
                }
            }

            // Update job progress
            $job = $checkpoint->job;
            $job->updateProgress();

            // Auto-start job if it's still pending
            if ($job->fresh()->status === 'pending') {
                $job->update(['status' => 'in-progress']);
            }

            // Auto-complete job if all checkpoints are done
            if ($job->fresh()->checkpoints_completed === $job->checkpoints_total) {
                $job->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Checkpoint updated successfully',
                'checkpoint' => $checkpoint->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Override checkpoint failed', [
                'checkpoint_id' => $checkpointId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to override checkpoint: ' . $e->getMessage()
            ], 500);
        }
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

        $checkpoint = JobCheckpoint::with(['job', 'checkpoint'])->findOrFail($checkpointId);

        if ($checkpoint->state === 'done') {
            return response()->json([
                'success' => false,
                'message' => 'Checkpoint already completed'
            ], 400);
        }

        // Get current user or use a default user for now
        $user = User::first(); // TODO: Replace with actual authenticated user

        $updateData = [
            'state' => 'done',
            'completed_by' => $user->id,
            'completion_method' => 'mobile',
            'notes' => $validated['notes'] ?? null,
        ];

        if (isset($validated['planned_bags'])) {
            $updateData['planned_bags'] = $validated['planned_bags'];
        }
        if (isset($validated['bags_loaded'])) {
            $updateData['bags_loaded'] = $validated['bags_loaded'];
        }
        if (isset($validated['food_bags'])) {
            $updateData['food_bags'] = $validated['food_bags'];
        }
        if (isset($validated['oversized_pieces'])) {
            $updateData['oversized_pieces'] = $validated['oversized_pieces'];
        }

        // Save photo to private storage if provided
        if (!empty($validated['photo'])) {
            $photoPath = $this->saveBase64File(
                $validated['photo'],
                'photos',
                $checkpoint->job_id,
                'photo',
                $checkpoint->id
            );
            if ($photoPath) {
                $updateData['photo_path'] = $photoPath;
            }
        }

        // Save signature to private storage if provided
        if (!empty($validated['signature'])) {
            $signaturePath = $this->saveBase64File(
                $validated['signature'],
                'signatures',
                $checkpoint->job_id,
                'signature',
                $checkpoint->id
            );
            if ($signaturePath) {
                $updateData['signature_path'] = $signaturePath;
            }
        }

        // Set completion time
        if (!empty($validated['actual_time'])) {
            // Use TODAY's date as the base, not the scheduled date
            list($hours, $minutes) = explode(':', $validated['actual_time']);
            $actualDateTime = \Carbon\Carbon::today()->setTime((int)$hours, (int)$minutes, 0);
            
            // Handle midnight crossover: if actual time is very early (e.g., 00:39) and scheduled time
            // was late (e.g., 14:19), assume the completion happened early next day
            if ($checkpoint->scheduled_at) {
                $scheduledDateTime = \Carbon\Carbon::parse($checkpoint->scheduled_at);
                $scheduledHour = $scheduledDateTime->hour;
                $actualHour = (int)$hours;
                
                // If scheduled late (after 18:00) and actual is very early (before 06:00),
                // it likely crossed midnight to tomorrow
                if ($scheduledHour >= 18 && $actualHour < 6) {
                    $actualDateTime->addDay();
                }
            }

            $updateData['completed_at'] = $actualDateTime;

            // Calculate duration based on scheduled time
            if ($checkpoint->scheduled_at) {
                $scheduledTime = \Carbon\Carbon::parse($checkpoint->scheduled_at);
                $updateData['actual_duration_seconds'] = abs($actualDateTime->diffInSeconds($scheduledTime));
            }

            // Check if on time based on movement's window_end (not individual checkpoint scheduled_at)
            $job = $checkpoint->job;
            $movement = $job->movement;
            if ($movement && $movement->window_end) {
                $windowEnd = \Carbon\Carbon::parse($movement->window_end);
                $delayMinutes = $actualDateTime->diffInMinutes($windowEnd, false);
                // On-time if completed before or at window_end
                $updateData['is_on_time'] = $delayMinutes <= 0;
                // Only store positive delays (after window_end)
                $updateData['delay_minutes'] = max(0, -$delayMinutes);
            }
        } else {
            $updateData['completed_at'] = now();
        }

        $checkpoint->update($updateData);

        // Update job progress
        $job = $checkpoint->job;
        $job->updateProgress();

        // Auto-start job if it's still pending
        if ($job->fresh()->status === 'pending') {
            $job->update(['status' => 'in-progress']);
        }

        // Auto-complete job if all checkpoints are done
        if ($job->fresh()->checkpoints_completed === $job->checkpoints_total) {
            $job->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Checkpoint completed successfully',
            'checkpoint' => $checkpoint->fresh(),
        ]);
    }

    /**
     * Save base64 encoded file to private storage
     */
    private function saveBase64File(string $base64Data, string $directory, int $jobId, string $type, int $checkpointId): ?string
    {
        try {
            // Extract the base64 string (remove data:image/...;base64, prefix)
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
                $extension = $matches[1];
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            } else {
                $extension = 'png';
            }

            // Decode base64
            $fileData = base64_decode($base64Data);
            if ($fileData === false) {
                Log::error('Failed to decode base64 file data');
                return null;
            }

            // Generate unique filename with checkpoint ID
            $timestamp = now()->format('YmdHis');
            $filename = "checkpoint_{$checkpointId}_{$type}_{$timestamp}.{$extension}";
            
            // Store in job-specific directory
            $jobDirectory = "jobs/{$jobId}/{$directory}";
            $filePath = "{$jobDirectory}/{$filename}";

            // Save to private storage (storage/app/...)
            Storage::disk('local')->put($filePath, $fileData);

            return $filePath;
        } catch (\Exception $e) {
            Log::error('Failed to save file: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieve checkpoint photo (with backward compatibility)
     */
    public function getCheckpointPhoto(string $checkpointId)
    {
        $checkpoint = JobCheckpoint::findOrFail($checkpointId);

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
        $checkpoint = JobCheckpoint::findOrFail($checkpointId);

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
     * Update job status
     */
    public function updateJobStatus(Request $request, $jobId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,dispatched,in-progress,completed,cancelled',
        ]);

        $job = JobOperation::findOrFail($jobId);

        $job->update([
            'status' => $validated['status'],
        ]);

        return response()->json(['success' => true, 'message' => 'Job status updated successfully']);
    }
}
