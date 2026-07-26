<?php

namespace App\Http\Controllers;

use App\Data\LmsData;
use App\Models\CheckpointTemplate;
use App\Models\MovementTemplate;
use App\Models\Plan;
use App\Models\Movement;
use App\Models\Event;
use App\Services\JobGenerationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Example controller showing how to use the checkpoint system.
 * 
 * This demonstrates the full workflow from template selection
 * to job generation and checkpoint completion.
 */
class PlanManagementController extends Controller
{
    protected JobGenerationService $jobService;

    public function __construct(JobGenerationService $jobService)
    {
        $this->jobService = $jobService;
    }

    /**
     * Display a listing of plans.
     */
    public function index(Request $request)
    {
        $activeEventId = $request->session()->get('active_event_id');
        Log::info('Plans page accessed via PlanManagementController', ['active_event_id' => $activeEventId]);
        
        // Get active event
        $activeEvent = $activeEventId ? Event::with('country')->find($activeEventId) : null;

        // The same movement appears in both the "by plan" ($plans) and "by
        // team" ($movementsByTeam) views below via two separate queries, so
        // estimateCheckpointSchedule() would otherwise run twice per
        // movement. Memoize by movement id since the estimate is a pure
        // function of the movement's own (already-loaded) data.
        $estimateCache = [];
        $getEstimatedSchedule = function ($movement) use (&$estimateCache) {
            if ($movement->job) {
                return [];
            }
            return $estimateCache[$movement->id] ??= $this->jobService->estimateCheckpointSchedule($movement);
        };

        $plans = Plan::with([
                'movements.team', 
                'movements.flight.originAirport',
                'movements.flight.destinationAirport',
                'movements.accommodation',
                'movements.vehicle', 
                'movements.driver',
                'movements.fieldSupervisor',
                'movements.match.team1',
                'movements.match.team2',
                'movements.match.venue',
                'movements.checkpointTemplate.checkpoints',
                'movements.job.checkpoints.completedBy', // Load job checkpoints for status
                'movementTemplate'
            ])
            ->when($activeEventId, function ($query) use ($activeEventId) {
                $query->where('event_id', $activeEventId);
            })
            ->latest()
            ->get()
            ->map(function ($plan) use ($getEstimatedSchedule) {
                // Transform each movement to include merged checkpoint data
                $plan->movements->transform(function ($movement) use ($getEstimatedSchedule) {
                    // Get actual job checkpoints if job exists
                    $jobCheckpoints = $movement->job?->checkpoints ?? collect();

                    // Live estimate for checkpoints that don't have a job yet
                    $estimatedSchedule = $getEstimatedSchedule($movement);

                    // BUS movements (flight_number === 'BUS') have no
                    // reference time — no checkpoints get generated or shown.
                    if ($movement->isBusMovement()) {
                        $movement->checkpoints = [];
                        return $movement;
                    }

                    // Build checkpoint data with real status from job_checkpoints table
                    $checkpoints = $movement->checkpointTemplate?->checkpoints->map(function ($checkpoint) use ($jobCheckpoints, $estimatedSchedule, $movement) {
                        // Find matching job checkpoint by checkpoint_id
                        $jobCheckpoint = $jobCheckpoints->firstWhere('checkpoint_id', $checkpoint->id);

                        if ($jobCheckpoint) {
                            // Use actual data from job_checkpoints table
                            return [
                                'id' => $checkpoint->id,
                                'name' => $checkpoint->name,
                                'type' => $checkpoint->type,
                                'requires_photo' => $checkpoint->requires_photo,
                                'requires_signature' => $checkpoint->requires_signature,
                                'requires_baggage_count' => $checkpoint->requires_baggage_count,
                                'planned_bags' => $jobCheckpoint->planned_bags,
                                'bags_loaded' => $jobCheckpoint->bags_loaded,
                                'food_bags' => $jobCheckpoint->food_bags,
                                'oversized_pieces' => $jobCheckpoint->oversized_pieces,
                                'state' => $jobCheckpoint->state ?? 'pending',
                                'scheduled_at' => $jobCheckpoint->scheduled_at?->format('Y-m-d H:i:s'),
                                'started_at' => $jobCheckpoint->started_at?->format('Y-m-d H:i:s'),
                                'completed_at' => $jobCheckpoint->completed_at?->format('Y-m-d H:i:s'),
                                'completed_by' => $jobCheckpoint->completedBy?->name,
                                'photo_path' => $jobCheckpoint->photo_path,
                                'signature_path' => $jobCheckpoint->signature_path,
                                'pivot' => $checkpoint->pivot,
                            ];
                        } else {
                            // No job yet, show template checkpoint
                            return [
                                'id' => $checkpoint->id,
                                'name' => $checkpoint->name,
                                'type' => $checkpoint->type,
                                'requires_photo' => $checkpoint->requires_photo,
                                'requires_signature' => $checkpoint->requires_signature,
                                'requires_baggage_count' => $checkpoint->requires_baggage_count,
                                'planned_bags' => $checkpoint->requires_baggage_count ? $movement->flight?->planned_bags : null,
                                'bags_loaded' => null,
                                'food_bags' => null,
                                'oversized_pieces' => null,
                                'state' => 'pending',
                                'scheduled_at' => null,
                                'estimated_at' => $estimatedSchedule[$checkpoint->id] ?? null,
                                'started_at' => null,
                                'completed_at' => null,
                                'completed_by' => null,
                                'photo_path' => null,
                                'signature_path' => null,
                                'pivot' => $checkpoint->pivot,
                            ];
                        }
                    }) ?? collect();
                    
                    // Add the merged checkpoints array to the movement
                    $movement->checkpoints = $checkpoints->toArray();
                    
                    return $movement;
                });
                
                return $plan;
            });

        $movementTemplates = \App\Models\MovementTemplate::with(['legs.checkpointTemplate'])
            ->select('id', 'code', 'name', 'description', 'scenario_type', 'estimated_duration_minutes', 'total_legs')
            ->orderBy('name')
            ->get();

        // Load teams from active event only
        $teams = collect();
        if ($activeEventId) {
            $teams = \App\Models\Team::where('event_id', $activeEventId)
                ->with([
                    'originAirport',
                    'destinationAirport',
                    'country',
                    'classification',
                    'flights' => function ($query) use ($activeEventId) {
                        $query->where('event_id', $activeEventId)
                              ->with(['destinationAirport', 'originAirport'])
                              ->orderBy('scheduled_at');
                    },
                    'stay'
                ])
                ->get()
                ->map(function ($team) {
                    $mapFlight = function ($flight) {
                        return [
                            'id' => $flight->id,
                            'flight_number' => $flight->flight_number,
                            'scheduled_at' => $flight->scheduled_at?->format('Y-m-d H:i:s'),
                            'scheduled_date' => $flight->scheduled_at?->format('Y-m-d'),
                            'scheduled_time' => $flight->scheduled_at?->format('H:i'),
                            'origin_airport' => $flight->originAirport?->code,
                            'destination_airport' => $flight->destinationAirport?->code,
                        ];
                    };

                    // Split the team's flights by direction
                    $arrivalFlights = $team->flights->where('direction', 'arrival')->map($mapFlight)->values();
                    $departureFlights = $team->flights->where('direction', 'departure')->map($mapFlight)->values();

                    // Use first flight of each direction as default
                    $defaultFlight = $arrivalFlights->first();
                    $defaultDepartureFlight = $departureFlights->first();

                    // Build a combined object with team info + all flights
                    return [
                        'id' => $team->id,
                        'code' => $team->code ?? 'TEAM',
                        'team_name' => $team->team_name ?? 'Unnamed Team',
                        'arrival_date_time' => $defaultFlight ? $defaultFlight['scheduled_at'] : null,
                        'arrival_date' => $defaultFlight ? $defaultFlight['scheduled_date'] : null,
                        'departure_date_time' => $defaultDepartureFlight ? $defaultDepartureFlight['scheduled_at'] : null,
                        'departure_date' => $defaultDepartureFlight ? $defaultDepartureFlight['scheduled_date'] : null,
                        'flights' => $arrivalFlights,
                        'departure_flights' => $departureFlights,
                        'selected_flight_id' => $defaultFlight ? $defaultFlight['id'] : null,
                        'origin_airport' => $team->originAirport,
                        'destination_airport' => $team->destinationAirport,
                        'country' => $team->country,
                        'classification' => $team->classification,
                    ];
                })
                ->sortBy('team_name')
                ->values();
        }

        // Load vehicles and drivers for movement editing
        $vehicles = \App\Models\Vehicle::select('id', 'code', 'vehicle_type', 'capacity')
            ->orderBy('code')
            ->get();
        
        $drivers = \App\Models\Driver::select('id', 'name', 'phone')
            ->orderBy('name')
            ->get();

        $supervisors = \App\Models\User::select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        // Load all movements grouped by team with their relationships
        $movementsByTeam = Movement::with([
                'team.originAirport',
                'team.destinationAirport',
                'team.country',
                'team.flights' => function ($query) use ($activeEventId) {
                    if ($activeEventId) {
                        $query->where('event_id', $activeEventId);
                    }
                },
                'flight.originAirport',
                'flight.destinationAirport',
                'accommodation',
                'vehicle',
                'driver',
                'fieldSupervisor',
                'plan',
                'match.team1',
                'match.team2',
                'match.venue',
                'checkpointTemplate.checkpoints',
                'job.checkpoints' // Load job checkpoints for status
            ])
            ->whereNotNull('team_id')
            ->when($activeEventId, function ($query) use ($activeEventId) {
                $query->where('event_id', $activeEventId);
            })
            ->orderBy('window_start')
            ->get()
            ->groupBy('team_id')
            ->map(function ($movements, $teamId) use ($activeEventId, $getEstimatedSchedule) {
                $team = $movements->first()->team;
                
                // Get arrival and departure dates from TeamFlight records for this event
                $arrivalFlight = $team->flights
                    ->where('direction', 'arrival')
                    ->where('event_id', $activeEventId)
                    ->sortBy('scheduled_at')
                    ->first();
                    
                $departureFlight = $team->flights
                    ->where('direction', 'departure')
                    ->where('event_id', $activeEventId)
                    ->sortByDesc('scheduled_at')
                    ->first();
                
                return [
                    'team_id' => $teamId,
                    'team' => $team->team_name,
                    'code' => $team->code,
                    'country' => $team->country?->country_name,
                    'flag' => $team->flag,
                    'party_size_total' => $team->party_size_total ?? 0,
                    'origin_airport' => $team->originAirport?->code,
                    'destination_airport' => $team->destinationAirport?->code,
                    'hotel_name' => $team->hotel_name,
                    'training_ground' => $team->training_ground,
                    'liaison' => $team->sc_liaison_name,
                    'arrival_date_time' => $arrivalFlight?->scheduled_at?->format('Y-m-d H:i:s'),
                    'departure_date_time' => $departureFlight?->scheduled_at?->format('Y-m-d H:i:s'),
                    'items' => $movements->map(function ($movement) use ($getEstimatedSchedule) {
                        // Get actual job checkpoints if job exists
                        $jobCheckpoints = $movement->job?->checkpoints ?? collect();

                        // Live estimate for checkpoints that don't have a job yet
                        $estimatedSchedule = $getEstimatedSchedule($movement);

                        // BUS movements (flight_number === 'BUS') have no
                        // reference time — no checkpoints get generated or shown.
                        $checkpoints = $movement->isBusMovement()
                            ? collect()
                            : ($movement->checkpointTemplate?->checkpoints->map(function ($checkpoint) use ($jobCheckpoints, $estimatedSchedule, $movement) {
                            // Find matching job checkpoint by checkpoint_id
                            $jobCheckpoint = $jobCheckpoints->firstWhere('checkpoint_id', $checkpoint->id);

                            if ($jobCheckpoint) {
                                // Use actual data from job_checkpoints table
                                return [
                                    'id' => $checkpoint->id,
                                    'name' => $checkpoint->name,
                                    'type' => $checkpoint->type,
                                    'requires_photo' => $checkpoint->requires_photo,
                                    'requires_signature' => $checkpoint->requires_signature,
                                    'requires_baggage_count' => $checkpoint->requires_baggage_count,
                                    'planned_bags' => $jobCheckpoint->planned_bags,
                                    'bags_loaded' => $jobCheckpoint->bags_loaded,
                                    'food_bags' => $jobCheckpoint->food_bags,
                                    'oversized_pieces' => $jobCheckpoint->oversized_pieces,
                                    'state' => $jobCheckpoint->state ?? 'pending',
                                    'scheduled_at' => $jobCheckpoint->scheduled_at?->format('Y-m-d H:i:s'),
                                    'started_at' => $jobCheckpoint->started_at?->format('Y-m-d H:i:s'),
                                    'completed_at' => $jobCheckpoint->completed_at?->format('Y-m-d H:i:s'),
                                    'completed_by' => $jobCheckpoint->completedBy?->name,
                                    'photo_path' => $jobCheckpoint->photo_path,
                                    'signature_path' => $jobCheckpoint->signature_path,
                                ];
                            } else {
                                // No job yet, show template checkpoint
                                return [
                                    'id' => $checkpoint->id,
                                    'name' => $checkpoint->name,
                                    'type' => $checkpoint->type,
                                    'requires_photo' => $checkpoint->requires_photo,
                                    'requires_signature' => $checkpoint->requires_signature,
                                    'requires_baggage_count' => $checkpoint->requires_baggage_count,
                                    'planned_bags' => $checkpoint->requires_baggage_count ? $movement->flight?->planned_bags : null,
                                    'bags_loaded' => null,
                                    'food_bags' => null,
                                    'oversized_pieces' => null,
                                    'state' => 'pending',
                                    'scheduled_at' => null,
                                    'estimated_at' => $estimatedSchedule[$checkpoint->id] ?? null,
                                    'started_at' => null,
                                    'completed_at' => null,
                                    'completed_by' => null,
                                    'photo_path' => null,
                                    'signature_path' => null,
                                ];
                            }
                        }) ?? collect());
                        
                        return [
                            'id' => $movement->id,
                            'code' => $movement->code,
                            'plan_id' => $movement->plan_id,
                            'team' => $movement->team->team_name,
                            'team_code' => $movement->team->code,
                            'team_id' => $movement->team_id,
                            'kind' => $movement->kind,
                            'from' => $movement->from_location,
                            'to' => $movement->to_location,
                            'dep' => $movement->window_start?->format('H:i'),
                            'arr' => $movement->flight?->scheduled_at?->format('H:i') ?? $movement->window_end?->format('H:i'),
                            'window_start' => $movement->window_start?->format('Y-m-d H:i:s'),
                            'window_end' => $movement->window_end?->format('Y-m-d H:i:s'),
                            'actual' => $movement->actual_departure?->format('H:i'),
                            'actual_arr' => $movement->actual_arrival?->format('H:i'),
                            'pax' => $movement->passengers ?? 0,
                            'vehicle' => $movement->vehicle?->code ?? $movement->vehicle?->plate_number ?? $movement->vehicle?->vehicle_type,
                            'vehicle_id' => $movement->vehicle_id,
                            'status' => $movement->status,
                            'delay' => $movement->delay_minutes,
                            'jobId' => $movement->job_id,
                            'source' => $movement->source,
                            'driver' => $movement->driver?->name,
                            'driver_id' => $movement->driver_id,
                            'field_supervisor' => $movement->fieldSupervisor?->name,
                            'field_supervisor_id' => $movement->field_supervisor_id,
                            'flight_number' => $movement->flight_number,
                            'notes' => $movement->notes,
                            'match_id' => $movement->match_id,
                            'match' => $movement->match ? [
                                'id' => $movement->match->id,
                                'match_number' => $movement->match->match_number,
                                'team1' => $movement->match->team1,
                                'team2' => $movement->match->team2,
                                'venue' => $movement->match->venue,
                                'kick_off' => $movement->match->kick_off?->format('Y-m-d H:i:s'),
                            ] : null,
                            'flight_id' => $movement->flight_id,
                            'flight' => $movement->flight ? [
                                'id' => $movement->flight->id,
                                'flight_number' => $movement->flight->flight_number,
                                'origin_airport' => $movement->flight->originAirport?->code ?? $movement->flight->origin_airport_id,
                                'destination_airport' => $movement->flight->destinationAirport?->code ?? $movement->flight->destination_airport_id,
                                'scheduled_at' => $movement->flight->scheduled_at?->format('Y-m-d H:i:s'),
                                'planned_bags' => $movement->flight->planned_bags,
                            ] : null,
                            'accommodation_id' => $movement->accommodation_id,
                            'accommodation' => $movement->accommodation ? [
                                'id' => $movement->accommodation->id,
                                'hotel_name' => $movement->accommodation->hotel_name,
                            ] : null,
                            'checkpoints' => $checkpoints->toArray(),
                        ];
                    })->values()->all()
                ];
            })
            ->values()
            ->all();

        // Load matches for the active event
        $matches = \App\Models\GameMatch::with(['team1', 'team2', 'venue', 'event'])
            ->when($activeEventId, function ($query) use ($activeEventId) {
                $query->where('event_id', $activeEventId);
            })
            ->orderBy('kick_off')
            ->get();

        // Get active plan from session (shared with Jobs page)
        $activePlanId = $request->session()->get('active_plan_id');

        // Next globally-unique movement code, so the UI can show the real
        // M-number a movement will get instead of a template-relative index
        // (matches JobGenerationService::generateMovementCode()'s logic —
        // derived from the highest CODE in use including soft-deleted rows,
        // since a "deleted" movement's code is still enforced by the DB's
        // unique index).
        $maxMovementNumber = Movement::withTrashed()
            ->whereNotNull('code')
            ->selectRaw("MAX(CAST(SUBSTRING(code, 2) AS UNSIGNED)) as max_number")
            ->value('max_number');
        $nextMovementNumber = ($maxMovementNumber ?? 0) + 1;

        return Inertia::render('Plans', [
            'activeEvent' => $activeEvent,
            'activePlan' => $activePlanId,
            'plans' => $plans,
            'movementTemplates' => $movementTemplates,
            'teams' => $teams,
            'movementsByTeam' => $movementsByTeam,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'supervisors' => $supervisors,
            'matches' => $matches,
            'nextMovementNumber' => $nextMovementNumber,
            'schedule' => LmsData::schedule(), // For backward compatibility - will be removed
        ]);
    }

    /**
     * Show plan creation page with available movement templates.
     */
    public function create()
    {
        $movementTemplates = MovementTemplate::active()
            ->with('legs.checkpointTemplate')
            ->get();

        return Inertia::render('Plans/Create', [
            'movementTemplates' => $movementTemplates,
        ]);
    }

    /**
     * Create a new plan (simple or from a movement template).
     */
    public function store(Request $request)
    {
        Log::info('Creating plan', ['request' => $request->all()]);
        
        // Get active event from session
        $activeEventId = session('active_event_id');
        
        $validated = $request->validate([
            'date' => 'required|date',
            'name' => 'nullable|string|max:255',
            'team_id' => 'nullable|exists:teams,id',
            'flight_id' => 'nullable|exists:team_flights,id',
            'start_time' => 'nullable|date_format:H:i',
            'movement_template_id' => 'nullable|exists:movement_templates,id',
            'functional_area' => 'nullable|in:LOG,AND,MOB',
            'team_assignments' => 'nullable|array', // ['default' => team_id] or [1 => team1_id, 2 => team2_id]
            'notes' => 'nullable|string',
        ]);
        
        // If flight_id is provided, use that flight's own time — but flight_id
        // here only ever refers to the team's ARRIVAL flight (the New Plan
        // modal's flight picker is arrival-only), so this must not run for a
        // departure template: doing so silently overwrote the frontend's
        // already-correct departure date/time with the arrival flight's time.
        $startTime = $validated['start_time'] ?? '09:00';

        $isDepartureTemplate = false;
        if (!empty($validated['movement_template_id'])) {
            $timingTemplate = MovementTemplate::find($validated['movement_template_id']);
            $templateText = strtolower(($timingTemplate->scenario_type ?? '') . ' ' . ($timingTemplate->name ?? '') . ' ' . ($timingTemplate->code ?? ''));
            $isDepartureTemplate = str_contains($templateText, 'departure');
        }

        if (!$isDepartureTemplate && !empty($validated['flight_id'])) {
            $flight = \App\Models\TeamFlight::find($validated['flight_id']);
            if ($flight) {
                // Use actual, estimated, or scheduled time (in that priority)
                $arrivalDateTime = $flight->actual_at ?? $flight->estimated_at ?? $flight->scheduled_at;
                if ($arrivalDateTime) {
                    $startTime = $arrivalDateTime->format('H:i');
                    $validated['date'] = $arrivalDateTime->format('Y-m-d');
                }
            }
        }

        // Use provided name or auto-generate based on date and template
        $planName = $validated['name'] ?? $this->generatePlanName($validated['date'], $validated['movement_template_id'] ?? null);

        // If movement template is provided, create plan from template
        if (!empty($validated['movement_template_id'])) {
            $template = MovementTemplate::findOrFail($validated['movement_template_id']);

            // Determine team assignments
            // Priority: team_assignments array > team_id > all event teams > first available team
            $teamAssignments = $validated['team_assignments'] ?? null;
            if (!$teamAssignments && !empty($validated['team_id'])) {
                // User specified a single team
                $teamAssignments = ['default' => $validated['team_id']];
            } elseif (!$teamAssignments && $activeEventId) {
                // No team specified but we have an event - create for all event teams
                $eventTeamIds = \App\Models\Team::where('event_id', $activeEventId)
                    ->pluck('id')
                    ->toArray();
                if (!empty($eventTeamIds)) {
                    $teamAssignments = ['teams' => $eventTeamIds];
                }
            } elseif (!$teamAssignments) {
                // No team specified and no event, use first available team as default
                $defaultTeam = \App\Models\Team::first();
                if ($defaultTeam) {
                    $teamAssignments = ['default' => $defaultTeam->id];
                }
            }

            // Only generate movements if we have team assignments
            if ($teamAssignments) {
                // Build base_time from date + start_time (already computed above)
                $baseTime = Carbon::createFromFormat('Y-m-d H:i', $validated['date'] . ' ' . $startTime, config('app.timezone'));
                
                $plan = $this->jobService->createPlanFromTemplate(
                    $template,
                    [
                        'name' => $planName,
                        'date' => $validated['date'],
                        'base_time' => $baseTime,
                        'status' => 'draft',
                        'event_id' => $activeEventId,
                        'flight_id' => $validated['flight_id'] ?? null,
                        'accommodation_id' => $validated['accommodation_id'] ?? null,
                        'created_by' => Auth::id(),
                        'notes' => $validated['notes'] ?? null,
                    ],
                    $teamAssignments
                );

                return redirect()->route('plans.index')
                    ->with('success', "Plan '{$plan->name}' created with {$plan->movements_count} movements from template");
            }
        }

        // Otherwise, create a simple blank plan
        $functionalArea = null;
        if (!empty($validated['movement_template_id'])) {
            $template = MovementTemplate::find($validated['movement_template_id']);
            $functionalArea = $template?->functional_area;
        }
        
        $plan = Plan::create([
            'code' => $this->generatePlanCode($validated['date']),
            'name' => $planName,
            'date' => $validated['date'],
            'status' => 'draft',
            'event_id' => $activeEventId,
            'movement_template_id' => $validated['movement_template_id'] ?? null,
            'functional_area' => $functionalArea ?? $validated['functional_area'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('plans.index')
            ->with('success', "Plan '{$plan->name}' created successfully");
    }

    /**
     * Generate a unique plan code.
     */
    protected function generatePlanCode(string $date): string
    {
        // Format: PLN-2026-0430-001 (Year-MonthDay-Sequence)
        $year = date('Y', strtotime($date));
        $monthDay = date('md', strtotime($date));
        $prefix = "PLN-{$year}-{$monthDay}";
        
        // Find the next available number for this date (including soft-deleted)
        $latestPlan = Plan::withTrashed()
            ->where('code', 'like', "{$prefix}%")
            ->orderBy('code', 'desc')
            ->first();

        if ($latestPlan) {
            // Extract the sequence number from the end (after last dash)
            $parts = explode('-', $latestPlan->code);
            $lastNumber = (int) end($parts);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // Generate code and ensure it's unique (handle race conditions)
        $attempts = 0;
        do {
            $code = sprintf('%s-%03d', $prefix, $nextNumber);
            $exists = Plan::withTrashed()->where('code', $code)->exists();
            
            if ($exists) {
                $nextNumber++;
                $attempts++;
                if ($attempts > 100) {
                    throw new \Exception("Unable to generate unique plan code after 100 attempts");
                }
            }
        } while ($exists);

        return $code;
    }

    /**
     * Generate a plan name based on date and template.
     */
    protected function generatePlanName(string $date, ?int $templateId): string
    {
        $dateObj = new \DateTime($date);
        $datePart = $dateObj->format('F j, Y'); // e.g., "April 30, 2026"
        
        if ($templateId) {
            $template = MovementTemplate::find($templateId);
            if ($template) {
                return "{$template->name} – {$datePart}";
            }
        }
        
        return "Plan – {$datePart}";
    }

    /**
     * Bulk create plans grouped by arrival date.
     * Each plan will contain all teams arriving on that date.
     */
    public function bulkStore(Request $request)
    {
        Log::info('Bulk creating plans', ['request' => $request->all()]);
        
        // Get active event from session
        $activeEventId = session('active_event_id');
        
        $validated = $request->validate([
            'plans' => 'required|array|min:1',
            'plans.*.date' => 'required|date',
            'plans.*.teams' => 'required|array|min:1',
            'plans.*.teams.*.team_id' => 'required|exists:teams,id',
            'plans.*.teams.*.start_time' => 'nullable|date_format:H:i',
            'plans.*.teams.*.flight_id' => 'nullable|exists:team_flights,id',
            'plans.*.movement_template_id' => 'required|exists:movement_templates,id',
        ]);
        
        $createdPlans = [];
        $totalMovements = 0;
        $errors = [];

        foreach ($validated['plans'] as $planData) {
            $date = $planData['date'];
            $teamsData = $planData['teams']; // Array of ['team_id' => X, 'start_time' => 'HH:MM']
            $templateId = $planData['movement_template_id'];
            
            // Get the template
            $template = MovementTemplate::findOrFail($templateId);
            
            // Extract team IDs and build team-time map
            $teamIds = [];
            $teamStartTimes = [];
            $teamFlightIds = [];
            foreach ($teamsData as $teamInfo) {
                $teamIds[] = $teamInfo['team_id'];
                $teamStartTimes[$teamInfo['team_id']] = $teamInfo['start_time'] ?? '09:00';
                $teamFlightIds[$teamInfo['team_id']] = $teamInfo['flight_id'] ?? null;
            }
            
            // Generate plan name
            $dateObj = new \DateTime($date);
            $datePart = $dateObj->format('F j, Y');
            $teamCount = count($teamIds);
            $planName = "{$template->name} – {$datePart} ({$teamCount} " . ($teamCount === 1 ? 'team' : 'teams') . ")";
            
            // Use earliest time as plan's base_time (for display purposes)
            $earliestTime = min(array_values($teamStartTimes));
            $baseTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $earliestTime, config('app.timezone'));
            
            // Create team assignments with start times and flight IDs
            $teamAssignments = [
                'teams' => $teamIds,
                'team_start_times' => $teamStartTimes, // Pass per-team start times
                'team_flight_ids' => $teamFlightIds, // Pass per-team flight IDs
            ];
            
            try {
                $plan = $this->jobService->createPlanFromTemplate(
                    $template,
                    [
                        'name' => $planName,
                        'date' => $date,
                        'base_time' => $baseTime,
                        'status' => 'draft',
                        'event_id' => $activeEventId,
                        'created_by' => Auth::id(),
                    ],
                    $teamAssignments
                );
                
                $createdPlans[] = $plan;
                $totalMovements += $plan->movements_count ?? 0;
                
                Log::info('Created plan from bulk', [
                    'plan_id' => $plan->id,
                    'date' => $date,
                    'teams' => $teamCount,
                    'movements' => $plan->movements_count
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to create plan from bulk', [
                    'date' => $date,
                    'teams' => $teamIds,
                    'error' => $e->getMessage()
                ]);
                $errors[] = "{$date}: {$e->getMessage()}";
                // Continue with next plan
            }
        }

        $planCount = count($createdPlans);

        if ($planCount === 0) {
            return redirect()->route('plans.index')
                ->with('error', 'Failed to create any plans. ' . ($errors[0] ?? 'Unknown error.'));
        }

        $message = "Successfully created {$planCount} " . ($planCount === 1 ? 'plan' : 'plans') . " with {$totalMovements} movements";
        if (!empty($errors)) {
            $message .= '. ' . count($errors) . ' failed: ' . $errors[0];
        }

        return redirect()->route('plans.index')->with('success', $message);
    }

    /**
     * Bulk create plans for matches (5 hours before kick-off).
     * Groups teams by date - creates one plan per date with multiple teams.
     */
    public function bulkMatchStore(Request $request)
    {
        Log::info('Bulk creating match plans', ['request' => $request->all()]);
        
        // Get active event from session
        $activeEventId = session('active_event_id');
        
        $validated = $request->validate([
            'plans' => 'required|array|min:1',
            'plans.*.date' => 'required|date',
            'plans.*.teams' => 'required|array|min:1',
            'plans.*.teams.*.team_id' => 'required|exists:teams,id',
            'plans.*.teams.*.start_time' => 'required|date_format:H:i',
            'plans.*.teams.*.match_id' => 'required|exists:matches,id',
            'plans.*.movement_template_id' => 'required|exists:movement_templates,id',
        ]);
        
        $createdPlans = [];
        $totalMovements = 0;
        $errors = [];

        foreach ($validated['plans'] as $planData) {
            $date = $planData['date'];
            $teamsData = $planData['teams'];
            $templateId = $planData['movement_template_id'];
            
            // Get the template
            $template = MovementTemplate::findOrFail($templateId);
            
            // Extract team IDs and build team-time map and match map
            $teamIds = [];
            $teamStartTimes = [];
            $teamMatchIds = [];
            foreach ($teamsData as $teamInfo) {
                $teamIds[] = $teamInfo['team_id'];
                $teamStartTimes[$teamInfo['team_id']] = $teamInfo['start_time'];
                $teamMatchIds[$teamInfo['team_id']] = $teamInfo['match_id'];
            }
            
            // Generate plan name
            $dateObj = new \DateTime($date);
            $datePart = $dateObj->format('F j, Y');
            $teamCount = count($teamIds);
            $planName = "{$template->name} – Match Day – {$datePart} ({$teamCount} " . ($teamCount === 1 ? 'team' : 'teams') . ")";
            
            // Use earliest time as plan's base_time
            $earliestTime = min(array_values($teamStartTimes));
            $baseTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $earliestTime, config('app.timezone'));
            
            // Create team assignments with start times and match IDs
            $teamAssignments = [
                'teams' => $teamIds,
                'team_start_times' => $teamStartTimes,
                'team_match_ids' => $teamMatchIds,
            ];
            
            try {
                // Debug: Log date being sent to createPlanFromTemplate
                Log::info('Creating plan for date', [
                    'input_date' => $date,
                    'base_time' => $baseTime->toDateTimeString(),
                    'earliest_time' => $earliestTime,
                    'teams' => count($teamIds)
                ]);
                
                $plan = $this->jobService->createPlanFromTemplate(
                    $template,
                    [
                        'name' => $planName,
                        'date' => $date,
                        'base_time' => $baseTime,
                        'status' => 'draft',
                        'event_id' => $activeEventId,
                        'created_by' => Auth::id(),
                    ],
                    $teamAssignments
                );
                
                // Debug: Log what was actually stored
                $plan->refresh(); // Reload from DB
                Log::info('Plan created in database', [
                    'plan_id' => $plan->id,
                    'stored_date' => $plan->date ? $plan->date->toDateTimeString() : 'null',
                    'input_date' => $date,
                    'date_column_value' => $plan->getAttributes()['date'] // Raw DB value
                ]);
                
                // Link movements to their respective matches based on team
                foreach ($teamMatchIds as $teamId => $matchId) {
                    $plan->movements()->where('team_id', $teamId)->update(['match_id' => $matchId]);
                }
                
                $createdPlans[] = $plan;
                $totalMovements += $plan->movements_count ?? 0;
                
                Log::info('Created match plan', [
                    'plan_id' => $plan->id,
                    'date' => $date,
                    'teams' => $teamCount,
                    'movements' => $plan->movements_count
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to create match plan', [
                    'date' => $date,
                    'teams' => $teamIds,
                    'error' => $e->getMessage()
                ]);
                $errors[] = "{$date}: {$e->getMessage()}";
                // Continue with next plan
            }
        }

        $planCount = count($createdPlans);

        if ($planCount === 0) {
            return redirect()->route('plans.index')
                ->with('error', 'Failed to create any match-day plans. ' . ($errors[0] ?? 'Unknown error.'));
        }

        $message = "Successfully created {$planCount} match-day " . ($planCount === 1 ? 'plan' : 'plans') . " with {$totalMovements} movements";
        if (!empty($errors)) {
            $message .= '. ' . count($errors) . ' failed: ' . $errors[0];
        }

        return redirect()->route('plans.index')->with('success', $message);
    }

    /**
     * Update an existing plan.
     */
    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'movement_template_id' => 'nullable|exists:movement_templates,id',
            'functional_area' => 'nullable|in:LOG,AND,MOB',
            'status' => 'required|in:draft,active,completed,archived',
            'notes' => 'nullable|string',
        ]);

        $plan->update($validated);

        // If date or start_time changed, recalculate movement times
        if (($request->has('date') || $request->has('start_time')) && $plan->movements()->count() > 0) {
            $startTime = $validated['start_time'] ?? '09:00';
            $baseTime = Carbon::createFromFormat('Y-m-d H:i', $validated['date'] . ' ' . $startTime, config('app.timezone'));
            
            $movements = $plan->movements()->orderBy('id')->get();
            $currentTime = $baseTime->copy();
            
            foreach ($movements as $movement) {
                // Calculate existing duration in minutes
                $existingDeparture = Carbon::parse($movement->window_start);
                $existingArrival = Carbon::parse($movement->window_end);
                $duration = $existingDeparture->diffInMinutes($existingArrival);
                
                $movement->update([
                    'window_start' => $currentTime->copy(),
                    'window_end' => $currentTime->copy()->addMinutes($duration),
                ]);
                
                // Move to next time slot
                $currentTime->addMinutes($duration);
            }
        }

        return redirect()->route('plans.index')
            ->with('success', "Plan '{$plan->name}' updated successfully");
    }

    /**
     * Delete a plan and all its movements.
     */
    public function destroy(Plan $plan)
    {
        $planName = $plan->name;
        $movementsCount = $plan->movements()->count();
        
        $plan->delete();

        return redirect()->route('plans.index')
            ->with('success', "Plan '{$planName}' and {$movementsCount} movement(s) deleted successfully");
    }

    /**
     * Show a plan with its movements.
     */
    public function show(Plan $plan)
    {
        $plan->load([
            'movements.team',
            'movements.vehicle',
            'movements.driver',
            'movements.checkpointTemplate.checkpoints',
            'movementTemplate',
        ]);

        // Count movements ready for job generation
        $readyForGeneration = $plan->movements()
            ->whereNull('job_id')
            ->count();

        // Get checkpoint templates for dropdown
        $checkpointTemplates = CheckpointTemplate::active()
            ->select('id', 'code', 'name', 'movement_type')
            ->get();

        return Inertia::render('Plans/Show', [
            'plan' => $plan,
            'readyForGeneration' => $readyForGeneration,
            'checkpointTemplates' => $checkpointTemplates,
        ]);
    }

    /**
     * Generate jobs from selected movements.
     */
    public function generateJobs(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'movement_ids' => 'required|array',
            'movement_ids.*' => 'exists:movements,id',
            'supervisor_id' => 'nullable|exists:users,id',
            'auto_assign' => 'boolean',
            'notify_liaisons' => 'boolean',
        ]);

        // Verify movements belong to this plan
        $movements = Movement::whereIn('id', $validated['movement_ids'])
            ->where('plan_id', $plan->id)
            ->whereNull('job_id')
            ->get();

        if ($movements->isEmpty()) {
            return back()->with('error', 'No valid movements to generate jobs from');
        }

        // Generate jobs (BUS movements are silently skipped internally —
        // they have no reference time to schedule against)
        $jobs = $this->jobService->generateJobsFromMovements(
            $movements->pluck('id')->toArray(),
            [
                'supervisor_id' => $validated['supervisor_id'] ?? null,
            ]
        );

        $skipped = $movements->count() - count($jobs);
        $message = count($jobs) . ' jobs generated successfully';
        if ($skipped > 0) {
            $message .= ". {$skipped} skipped (BUS movement, no reference time).";
        }

        if (count($jobs) === 0) {
            return back()->with('error', $skipped > 0 ? "No jobs generated. {$skipped} movement(s) skipped (BUS movement, no reference time)." : 'No jobs generated.');
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Add movements to a plan.
     * Can add a single manual movement, or multiple movements from a template for a team.
     */
    public function addMovement(Request $request, Plan $plan)
    {
        // Check if this is a template-based creation for a team
        if ($request->has('movement_template_id') && $request->filled('movement_template_id')) {
            $validated = $request->validate([
                'team_id' => 'required|exists:teams,id',
                'movement_template_id' => 'required|exists:movement_templates,id',
            ]);

            $template = MovementTemplate::with('legs.checkpointTemplate')->findOrFail($validated['movement_template_id']);
            $team = \App\Models\Team::findOrFail($validated['team_id']);

            // Use the team's arrival time if available, otherwise default to 9:00 AM on the plan date
            $baseTime = $team->arrival_date_time 
                ? Carbon::parse($team->arrival_date_time)
                : Carbon::parse($plan->date)->setTime(9, 0);

            // Create movements from template legs
            $movementsCreated = 0;
            $cumulativeMinutes = 0;

            foreach ($template->legs as $leg) {
                $scheduledDeparture = $baseTime->copy()->addMinutes($cumulativeMinutes);
                $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);

                Movement::create([
                    'code' => $this->generateMovementCode($plan),
                    'plan_id' => $plan->id,
                    'event_id' => $plan->event_id,
                    'team_id' => $team->id,
                    'checkpoint_template_id' => $leg->checkpoint_template_id,
                    'kind' => $leg->leg_type ?? 'transfer',
                    'functional_area' => $template->functional_area,
                    'from_location' => $leg->from_location,
                    'to_location' => $leg->to_location,
                    'window_start' => $scheduledDeparture,
                    'window_end' => $scheduledArrival,
                    'passengers' => $team->party_size_total ?? 0,
                    'status' => 'scheduled',
                    'source' => 'template',
                ]);

                $cumulativeMinutes += $leg->estimated_duration_minutes ?? 30;
                $movementsCreated++;
            }

            // Update plan counters
            $plan->increment('movements_count', $movementsCreated);
            
            // Update teams_count if this is a new team in the plan
            $teamsInPlan = Movement::where('plan_id', $plan->id)
                ->distinct('team_id')
                ->count('team_id');
            $plan->update(['teams_count' => $teamsInPlan]);

            return back()->with('success', "{$movementsCreated} movements added to plan for {$team->team_name}");
        }

        // Otherwise, add a single manual movement (existing functionality)
        $validated = $request->validate([
            'team_id' => 'required|exists:teams,id',
            'flight_id' => 'nullable|exists:team_flights,id',
            'checkpoint_template_id' => 'required|exists:checkpoint_templates,id',
            'kind' => 'required|in:arrival,departure,transfer,training,match',
            'functional_area' => 'nullable|in:LOG,AND,MOB',
            'from_location' => 'required|string',
            'to_location' => 'required|string',
            'window_start' => 'required|date',
            'window_end' => 'required|date',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'passengers' => 'required|integer|min:1',
        ]);

        $movement = Movement::create([
            'code' => $this->generateMovementCode($plan),
            'plan_id' => $plan->id,
            'event_id' => $plan->event_id,
            ...$validated,
            'status' => 'scheduled',
            'source' => 'manual',
        ]);

        $plan->increment('movements_count');

        return back()->with('success', 'Movement added to plan');
    }

    /**
     * Update checkpoint template for a movement.
     */
    public function updateCheckpointTemplate(Request $request, Movement $movement)
    {
        $validated = $request->validate([
            'checkpoint_template_id' => 'required|exists:checkpoint_templates,id',
        ]);

        if ($movement->hasJob()) {
            return back()->with('error', 'Cannot change checkpoint template after job generation');
        }

        $movement->update($validated);

        return back()->with('success', 'Checkpoint template updated');
    }

    /**
     * Generate a unique movement code.
     */
    protected function generateMovementCode(Plan $plan): string
    {
        // Generate globally unique movement code. Derived from the highest
        // CODE in use, not the highest row id — after deletions, id order
        // and code order can diverge, so "latest by id + 1" can recompute a
        // code an older, undeleted row already holds and collide on the
        // unique constraint. Includes soft-deleted rows (withTrashed):
        // Movement uses SoftDeletes, so a "deleted" movement's code is
        // still physically in the table and still enforced by the DB's
        // unique index, which doesn't know about deleted_at.
        $maxNumber = Movement::withTrashed()
            ->whereNotNull('code')
            ->selectRaw("MAX(CAST(SUBSTRING(code, 2) AS UNSIGNED)) as max_number")
            ->value('max_number');

        return sprintf('M%d', ($maxNumber ?? 0) + 1);
    }

    /**
     * API: Get checkpoint templates by movement type.
     */
    public function getCheckpointTemplates(Request $request)
    {
        $type = $request->query('type');

        $query = CheckpointTemplate::active()->with('checkpoints');

        if ($type) {
            $query->where('movement_type', $type);
        }

        return response()->json([
            'templates' => $query->get(),
        ]);
    }

    /**
     * API: Preview what checkpoints a template contains.
     */
    public function previewCheckpointTemplate(CheckpointTemplate $template)
    {
        $template->load('checkpoints');

        return response()->json([
            'template' => $template,
            'checkpoints' => $template->checkpoints->map(fn($cp) => [
                'id' => $cp->id,
                'name' => $cp->name,
                'type' => $cp->type,
                'order' => $cp->pivot->order,
                'is_required' => $cp->pivot->is_required,
                'estimated_minutes' => $cp->pivot->estimated_minutes,
                'requires_photo' => $cp->requires_photo,
                'requires_signature' => $cp->requires_signature,
            ]),
            'total_checkpoints' => $template->checkpoints->count(),
            'estimated_duration' => $template->estimated_duration_minutes,
        ]);
    }

    /**
     * Update a movement's details (operational fields only).
     */
    public function updateMovement(Request $request, Movement $movement)
    {
        // Only allow updating operational fields
        // Core fields (team, flight, kind, locations, passengers) are read-only if linked to a flight
        $validated = $request->validate([
            'window_start' => 'nullable|date',
            'window_end' => 'nullable|date',
            'functional_area' => 'nullable|in:LOG,AND,MOB',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'field_supervisor_id' => 'nullable|exists:users,id',
            'match_id' => 'nullable|exists:matches,id',
            'notes' => 'nullable|string',
        ]);

        $movement->update($validated);

        return redirect()->back()->with('success', 'Movement updated successfully.');
    }

    /**
     * Update a plan's status.
     */
    public function updateStatus(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:draft,approved,active,completed,cancelled',
        ]);

        $plan->update($validated);

        return redirect()->back()->with('success', 'Plan status updated successfully.');
    }

    /**
     * Delete a movement.
     */
    public function deleteMovement(Movement $movement)
    {
        // Check if movement has generated jobs
        if ($movement->job_id) {
            return redirect()->back()->with('error', 'Cannot delete movement with generated jobs. Delete the job first.');
        }

        $movement->delete();

        return redirect()->back()->with('success', 'Movement deleted successfully.');
    }

    /**
     * Delete multiple movements in one request. Movements that already have
     * a generated job are skipped rather than blocking the whole batch.
     */
    public function deleteMovementsBulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:movements,id',
        ]);

        $movements = Movement::whereIn('id', $validated['ids'])->get();

        $deletableIds = $movements->filter(fn ($movement) => !$movement->job_id)->pluck('id');
        $skippedCount = $movements->count() - $deletableIds->count();

        Movement::whereIn('id', $deletableIds)->delete();

        $message = "Deleted {$deletableIds->count()} movement(s).";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} skipped (job already generated).";
        }

        return redirect()->back()->with(
            $deletableIds->count() > 0 ? 'success' : 'error',
            $message
        );
    }

    /**
     * Check for duplicate movements based on movement type.
     * Returns array with 'exists' boolean and optional 'existing' movement details.
     */
    protected function checkDuplicateMovement(array $data): array
    {
        $kind = $data['kind'] ?? 'transfer';
        $teamId = $data['team_id'] ?? null;

        if (!$teamId) {
            return ['exists' => false];
        }

        // STRICT: Check flight-based movements (arrival/departure)
        if (in_array($kind, ['arrival', 'departure']) && !empty($data['flight_id'])) {
            $existing = Movement::where('team_id', $teamId)
                ->where('flight_id', $data['flight_id'])
                ->where('kind', $kind)
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                return [
                    'exists' => true,
                    'strict' => true,
                    'message' => "This team already has a {$kind} movement for this flight.",
                    'existing' => [
                        'id' => $existing->id,
                        'code' => $existing->code,
                        'plan_name' => $existing->plan->name ?? null,
                    ]
                ];
            }
        }

        // STRICT: Check match movements
        if ($kind === 'match' && !empty($data['match_id'])) {
            $existing = Movement::where('team_id', $teamId)
                ->where('match_id', $data['match_id'])
                ->where('kind', 'match')
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                return [
                    'exists' => true,
                    'strict' => true,
                    'message' => "This team already has a match movement for this match.",
                    'existing' => [
                        'id' => $existing->id,
                        'code' => $existing->code,
                        'plan_name' => $existing->plan->name ?? null,
                    ]
                ];
            }
        }

        // SOFT WARNING: Check similar transfers/training/daily_ops
        if (in_array($kind, ['transfer', 'training', 'daily_ops']) 
            && !empty($data['from_location']) 
            && !empty($data['to_location'])
            && !empty($data['window_start'])) {
            
            $windowStart = Carbon::parse($data['window_start']);
            $windowStartMinus30 = $windowStart->copy()->subMinutes(30);
            $windowStartPlus30 = $windowStart->copy()->addMinutes(30);

            $similar = Movement::where('team_id', $teamId)
                ->where('kind', $kind)
                ->where('from_location', $data['from_location'])
                ->where('to_location', $data['to_location'])
                ->whereBetween('window_start', [$windowStartMinus30, $windowStartPlus30])
                ->whereNull('deleted_at')
                ->first();

            if ($similar) {
                return [
                    'exists' => true,
                    'strict' => false,
                    'message' => "Similar {$kind} movement found: {$similar->from_location} → {$similar->to_location} at " . $similar->window_start->format('H:i'),
                    'existing' => [
                        'id' => $similar->id,
                        'code' => $similar->code,
                        'plan_name' => $similar->plan->name ?? null,
                        'window_start' => $similar->window_start->format('Y-m-d H:i:s'),
                    ]
                ];
            }
        }

        return ['exists' => false];
    }

    /**
     * API endpoint to check for duplicates before creating movement.
     */
    public function checkDuplicate(Request $request)
    {
        $validated = $request->validate([
            'kind' => 'required|in:arrival,departure,transfer,training,match,daily_ops',
            'team_id' => 'required|exists:teams,id',
            'flight_id' => 'nullable|exists:team_flights,id',
            'match_id' => 'nullable|exists:matches,id',
            'from_location' => 'nullable|string',
            'to_location' => 'nullable|string',
            'window_start' => 'nullable|date',
        ]);

        $result = $this->checkDuplicateMovement($validated);

        return response()->json($result);
    }

    /**
     * Bulk variant of checkDuplicate — runs many checks in a single request
     * instead of one HTTP round trip per team, which is what bulk plan
     * creation (dozens of teams) needs to stay fast.
     */
    public function checkDuplicateBulk(Request $request)
    {
        $validated = $request->validate([
            'checks' => 'required|array',
            'checks.*.key' => 'required|string',
            'checks.*.kind' => 'required|in:arrival,departure,transfer,training,match,daily_ops',
            'checks.*.team_id' => 'required|integer|exists:teams,id',
            'checks.*.flight_id' => 'nullable|integer|exists:team_flights,id',
            'checks.*.match_id' => 'nullable|integer|exists:matches,id',
            'checks.*.from_location' => 'nullable|string',
            'checks.*.to_location' => 'nullable|string',
            'checks.*.window_start' => 'nullable|date',
        ]);

        $results = [];

        foreach ($validated['checks'] as $check) {
            $results[$check['key']] = $this->checkDuplicateMovement($check);
        }

        return response()->json(['results' => $results]);
    }
}
