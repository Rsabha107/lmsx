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

    public function plans(): Response
    {
        return Inertia::render('Plans', [
            'schedule' => LmsData::schedule(),
        ]);
    }

    public function library(): Response
    {
        return Inertia::render('Library', [
            'checkpoints' => Checkpoint::withCount('checkpointTemplates as usage_count')
                ->orderBy('code')
                ->get(),
            'checkpointTemplates' => CheckpointTemplate::with(['checkpoints' => function($query) {
                    $query->orderBy('checkpoint_checkpoint_template.order');
                }])
                ->withCount('checkpoints as checkpoint_count')
                ->orderBy('code')
                ->get(),
            'movementTemplates' => MovementTemplate::with(['legs' => function($query) {
                    $query->orderBy('order');
                }])
                ->orderBy('code')
                ->get()
                ->map(function($template) {
                    $template->setRelation('legs', $template->legs->map(function($leg) {
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
        // Get all plans
        $plans = \App\Models\Plan::orderBy('date', 'desc')
            ->orderBy('name')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'code' => $plan->code,
                    'date' => $plan->date,
                    'status' => $plan->status,
                    'movements_count' => $plan->movements()->count(),
                    'teams_count' => $plan->movements()->distinct('team_id')->count('team_id'),
                ];
            });

        // Get selected plan (from query param or default to first plan)
        $selectedPlanId = $request->query('plan');
        if ($selectedPlanId) {
            $selectedPlanId = (int) $selectedPlanId; // Cast to integer
        } elseif ($plans->isNotEmpty()) {
            $selectedPlanId = $plans->first()['id'];
        }

        // Build job query with plan filter
        $jobsQuery = JobOperation::with([
            'movement.team',
            'vehicle',
            'driver',
            'supervisor',
            'checkpoints.completedBy'
        ]);

        // Filter by plan if selected
        if ($selectedPlanId) {
            $jobsQuery->where('plan_id', $selectedPlanId);
        }

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
                    'arr' => $movement?->window_end?->format('H:i') ?? '--:--',
                    'pax' => $movement?->passengers ?? 0,
                    'vehicle' => $job->vehicle ? ($job->vehicle->code ?? $job->vehicle->plate_number ?? $job->vehicle->vehicle_type ?? 'Unassigned') : 'Unassigned',
                    'status' => $job->status,
                    'delay' => $movement?->delay_minutes,
                    'jobId' => $job->job_id,
                    'source' => $movement?->source ?? 'manual',
                    'checksComplete' => $job->checkpoints_completed ?? 0,
                    'checksTotal' => $job->checkpoints_total ?? 0,
                    'supervisor' => $job->supervisor?->name ?? 'Unassigned',
                    'driver' => $job->driver?->name ?? 'Unassigned',
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
                            'by' => $checkpoint->completedBy?->name ?? ($checkpoint->state === 'done' ? 'System' : null),
                            'completion_method' => $checkpoint->completion_method,
                            'estimated_minutes' => $checkpoint->estimated_minutes,
                            'actual_duration_seconds' => $checkpoint->actual_duration_seconds,
                            'requires_photo' => $checkpoint->requires_photo,
                            'requires_signature' => $checkpoint->requires_signature,
                            'has_photo' => $checkpoint->photo_path ? true : false,
                            'has_signature' => $checkpoint->signature_path ? true : false,
                        ];
                    })->toArray(),
                ];
            });

        return Inertia::render('Jobs', [
            'schedule' => $jobs,
            'plans' => $plans,
            'activePlan' => $selectedPlanId,
        ]);
    }

    public function jobsMobile(): Response
    {
        // Get database jobs
        $dbJobs = JobOperation::with([
            'movement.team',
            'vehicle',
            'driver',
            'supervisor',
            'checkpoints.completedBy'
        ])
            ->orderBy('created_at', 'desc')
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
                    'arr' => $movement?->window_end?->format('H:i') ?? '--:--',
                    'pax' => $movement?->passengers ?? 0,
                    'vehicle' => $job->vehicle ? ($job->vehicle->code ?? $job->vehicle->plate_number ?? $job->vehicle->vehicle_type ?? 'Unassigned') : 'Unassigned',
                    'status' => $job->status,
                    'delay' => $movement?->delay_minutes,
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
            'movement.team',
            'vehicle',
            'driver',
            'supervisor',
            'checkpoints.completedBy'
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
                'arr' => $movement?->window_end?->format('H:i') ?? '--:--',
                'pax' => $movement?->passengers ?? 0,
                'vehicle' => $jobOperation->vehicle ? ($jobOperation->vehicle->code ?? $jobOperation->vehicle->plate_number ?? $jobOperation->vehicle->vehicle_type ?? 'Unassigned') : 'Unassigned',
                'status' => $jobOperation->status,
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
                    'has_photo' => $checkpoint->photo_path ? true : false,
                    'has_signature' => $checkpoint->signature_path ? true : false,
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
            'movement.team',
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
            'arr' => $movement->window_end ? \Carbon\Carbon::parse($movement->window_end)->format('H:i') : 'N/A',
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

    public function teams(): Response
    {
        return Inertia::render('Teams', [
            'teams' => Team::with(['classification', 'country', 'originAirport', 'destinationAirport'])->active()->orderBy('code')->get(),
            'classifications' => TeamClassification::active()->orderBy('name')->get(),
            'countries' => Country::active()->orderBy('country_name')->get(),
            'airports' => Airport::orderBy('name')->get(),
        ]);
    }

    public function storeTeam(Request $request): RedirectResponse
    {
        Log::info('Storing new team', ['request' => $request->all()]);
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:teams,code',
            'team_name' => 'required|string|max:255',
            'country_id' => 'required|string|max:10|exists:countries,country_code',
            'flag' => 'nullable|string|max:10',
            'group_pool' => 'nullable|string|max:50',
            'classification_type_id' => 'nullable|integer|exists:team_classifications,id',
            'party_size_total' => 'nullable|integer|min:0',
            'party_size_players' => 'nullable|integer|min:0',
            'party_size_staff' => 'nullable|integer|min:0',
            'hotel_name' => 'nullable|string|max:255',
            'training_ground' => 'nullable|string|max:255',
            'origin_airport_id' => 'nullable|integer|exists:airports,id',
            'destination_airport_id' => 'nullable|integer|exists:airports,id',
            'gate' => 'nullable|string|max:50',
            'flight_number' => 'nullable|string|max:20',
            'arrival_date_time' => 'nullable|date_format:Y-m-d H:i',
            'departure_date_time' => 'nullable|date_format:Y-m-d H:i',
            'arrival_manifest' => 'nullable|array',
            'head_of_delegation' => 'nullable|string|max:255',
            'sc_liaison_name' => 'nullable|string|max:255',
            'sc_liaison_phone' => 'nullable|string|max:50',
            'bib_accent_color' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        Log::info('Validated team data', ['validated' => $validated]);
        Team::create($validated);

        Log::info('Team created successfully', ['team_code' => $validated['code']]);
        return redirect()->route('teams')->with('success', 'Team added successfully.');
    }

    public function updateTeam(Request $request, string $code): RedirectResponse
    {
        Log::info('Updating team', ['code' => $code, 'request' => $request->all()]);
        
        $team = Team::where('code', $code)->firstOrFail();
        
        $validated = $request->validate([
            'code' => 'required|string|max:10',
            'team_name' => 'required|string|max:255',
            'country_id' => 'required|string|max:10|exists:countries,country_code',
            'flag' => 'nullable|string|max:10',
            'group_pool' => 'nullable|string|max:50',
            'classification_type_id' => 'nullable|integer|exists:team_classifications,id',
            'party_size_total' => 'nullable|integer|min:0',
            'party_size_players' => 'nullable|integer|min:0',
            'party_size_staff' => 'nullable|integer|min:0',
            'hotel_name' => 'nullable|string|max:255',
            'training_ground' => 'nullable|string|max:255',
            'origin_airport_id' => 'nullable|integer|exists:airports,id',
            'destination_airport_id' => 'nullable|integer|exists:airports,id',
            'gate' => 'nullable|string|max:50',
            'flight_number' => 'nullable|string|max:20',
            'arrival_date_time' => 'nullable|date_format:Y-m-d H:i',
            'departure_date_time' => 'nullable|date_format:Y-m-d H:i',
            'arrival_manifest' => 'nullable|array',
            'head_of_delegation' => 'nullable|string|max:255',
            'sc_liaison_name' => 'nullable|string|max:255',
            'sc_liaison_phone' => 'nullable|string|max:50',
            'bib_accent_color' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        Log::info('Validated team update data', ['validated' => $validated]);
        $team->update($validated);

        Log::info('Team updated successfully', ['team_code' => $code]);
        return redirect()->route('teams')->with('success', 'Team updated successfully.');
    }

    public function destroyTeam(string $code): RedirectResponse
    {
        Log::info('Deleting team', ['code' => $code]);
        
        $team = Team::where('code', $code)->firstOrFail();
        $team->delete();

        Log::info('Team deleted successfully', ['team_code' => $code]);
        return redirect()->route('teams')->with('success', 'Team deleted successfully.');
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
        $validated = $request->validate([
            'state' => 'required|in:done,missed,skipped',
            'actual_time' => 'nullable|date_format:H:i',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $checkpoint = JobCheckpoint::with('job')->findOrFail($checkpointId);
        
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
        if ($validated['state'] === 'done' && $validated['actual_time']) {
            $updateData['override_actual_time'] = $validated['actual_time'];
            // Use the same date as scheduled_at but with the actual time provided
            if ($checkpoint->scheduled_at) {
                $scheduledDateTime = \Carbon\Carbon::parse($checkpoint->scheduled_at);
                list($hours, $minutes) = explode(':', $validated['actual_time']);
                $actualDateTime = $scheduledDateTime->copy()->setTime((int)$hours, (int)$minutes, 0);
            } else {
                // Fallback to today's date if no scheduled time
                $actualDateTime = \Carbon\Carbon::createFromFormat('H:i', $validated['actual_time']);
            }
            
            $updateData['completed_at'] = $actualDateTime;
            $updateData['completed_by'] = $user->id;
            $updateData['completion_method'] = 'web';
            $updateData['notes'] = $validated['notes'] ?? null;
            
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
        ]);

        $checkpoint = JobCheckpoint::with('job')->findOrFail($checkpointId);
        
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

        // Save photo to private storage if provided
        if (!empty($validated['photo'])) {
            $photoPath = $this->saveBase64File(
                $validated['photo'],
                'checkpoints/photos',
                $checkpoint->job_id,
                'photo'
            );
            if ($photoPath) {
                $updateData['photo_path'] = $photoPath;
            }
        }

        // Save signature to private storage if provided
        if (!empty($validated['signature'])) {
            $signaturePath = $this->saveBase64File(
                $validated['signature'],
                'checkpoints/signatures',
                $checkpoint->job_id,
                'signature'
            );
            if ($signaturePath) {
                $updateData['signature_path'] = $signaturePath;
            }
        }

        // Set completion time
        if (!empty($validated['actual_time'])) {
            // Use the same date as scheduled_at but with the actual time provided
            if ($checkpoint->scheduled_at) {
                $scheduledDateTime = \Carbon\Carbon::parse($checkpoint->scheduled_at);
                list($hours, $minutes) = explode(':', $validated['actual_time']);
                $actualDateTime = $scheduledDateTime->copy()->setTime((int)$hours, (int)$minutes, 0);
            } else {
                // Fallback to today's date if no scheduled time
                $actualDateTime = \Carbon\Carbon::createFromFormat('H:i', $validated['actual_time']);
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
    private function saveBase64File(string $base64Data, string $directory, int $jobId, string $type): ?string
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

            // Generate unique filename
            $timestamp = now()->format('YmdHis');
            $filename = "job_{$jobId}_{$type}_{$timestamp}.{$extension}";
            $filePath = "{$directory}/{$filename}";

            // Save to private storage (storage/app/private/...)
            Storage::disk('local')->put("private/{$filePath}", $fileData);

            return $filePath;
        } catch (\Exception $e) {
            Log::error('Failed to save file: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieve checkpoint photo
     */
    public function getCheckpointPhoto(string $checkpointId)
    {
        $checkpoint = JobCheckpoint::findOrFail($checkpointId);
        
        if (!$checkpoint->photo_path) {
            abort(404, 'Photo not found');
        }

        $filePath = "private/{$checkpoint->photo_path}";
        
        if (!Storage::disk('local')->exists($filePath)) {
            abort(404, 'Photo file not found');
        }

        $file = Storage::disk('local')->get($filePath);
        $extension = pathinfo($checkpoint->photo_path, PATHINFO_EXTENSION);
        $mimeType = $this->getMimeType($extension);

        return response($file, 200)->header('Content-Type', $mimeType);
    }

    /**
     * Retrieve checkpoint signature
     */
    public function getCheckpointSignature(string $checkpointId)
    {
        $checkpoint = JobCheckpoint::findOrFail($checkpointId);
        
        if (!$checkpoint->signature_path) {
            abort(404, 'Signature not found');
        }

        $filePath = "private/{$checkpoint->signature_path}";
        
        if (!Storage::disk('local')->exists($filePath)) {
            abort(404, 'Signature file not found');
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
