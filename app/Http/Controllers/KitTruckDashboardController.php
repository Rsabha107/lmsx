<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Event;
use App\Models\EventTeam;
use App\Models\GameMatch;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\TeamFlight;
use App\Models\TeamStay;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KitTruckDashboardController extends Controller
{
    public function index(Request $request)
    {
        $kind        = $request->query('kind', 'match');
        $filterDate  = $request->query('date');
        $filterEvent = $request->query('event');

        // Load jobs of the selected movement kind with all needed relations
        $jobs = JobOperation::with([
            'movement.team.country',
            'movement.match',
            'checkpoints' => fn ($q) => $q->orderBy('order'),
        ])
            ->whereHas('movement', function($q) use ($kind) {
                $q->where('kind', $kind);
            })
            ->when(!empty($filterEvent), fn ($q) => $q->where('event_id', $filterEvent))
            ->when($filterDate, fn ($q) => $q->whereHas('movement', function($query) use ($filterDate) {
                $query->whereDate('window_start', $filterDate);
            }))
            ->orderBy('dispatched_at')
            ->get();

        // Load EventTeam data with flights and accommodation for all jobs
        $eventTeamPairs = $jobs->map(fn($j) => [
            'event_id' => $j->event_id, 
            'team_id' => $j->team_id
        ])->unique()->filter()->values();
        
        $eventTeams = collect();
        if ($eventTeamPairs->isNotEmpty()) {
            // First load EventTeam records
            $eventTeams = EventTeam::with('team.country')
                ->where(function($query) use ($eventTeamPairs) {
                    foreach ($eventTeamPairs as $pair) {
                        $query->orWhere(function($q) use ($pair) {
                            $q->where('event_id', $pair['event_id'])
                              ->where('team_id', $pair['team_id']);
                        });
                    }
                })
                ->get()
                ->keyBy(fn($et) => $et->event_id . '_' . $et->team_id);
            
            // Load flights for each event-team pair
            $flightsMap = [];
            foreach ($eventTeamPairs as $pair) {
                $flights = TeamFlight::with(['originAirport', 'destinationAirport'])
                    ->where('event_id', $pair['event_id'])
                    ->where('team_id', $pair['team_id'])
                    ->orderBy('scheduled_at')
                    ->get();
                $flightsMap[$pair['event_id'] . '_' . $pair['team_id']] = $flights;
            }
            
            // Load stays for each event-team pair
            $staysMap = [];
            foreach ($eventTeamPairs as $pair) {
                $stay = TeamStay::where('event_id', $pair['event_id'])
                    ->where('team_id', $pair['team_id'])
                    ->first();
                $staysMap[$pair['event_id'] . '_' . $pair['team_id']] = $stay;
            }
            
            // Attach flights and stays to EventTeam records
            foreach ($eventTeams as $key => $eventTeam) {
                $eventTeam->setRelation('flights', $flightsMap[$key] ?? collect());
                $eventTeam->setRelation('stay', $staysMap[$key] ?? null);
            }
        }

        // Attach EventTeam to each job
        foreach ($jobs as $job) {
            $key = $job->event_id . '_' . $job->team_id;
            $job->eventTeam = $eventTeams[$key] ?? null;
        }

        // Pre-load all matches for cross-referencing jobs that have no match_id
        $allMatches  = GameMatch::with(['team1', 'team2'])->get();
        $matchIndex  = [];
        foreach ($allMatches as $m) {
            $date = $m->match_date?->toDateString();
            if ($m->team1_id && $date) $matchIndex[$m->team1_id . '_' . $date] = $m;
            if ($m->team2_id && $date) $matchIndex[$m->team2_id . '_' . $date] = $m;
        }

        // ── Build column definitions ───────────────────────────────────────
        // Use job checkpoint snapshots (have order + snapshotted name)
        $columnMap = []; // order => name
        foreach ($jobs as $job) {
            foreach ($job->checkpoints ?? [] as $jcp) {
                if (!isset($columnMap[$jcp->order])) {
                    $columnMap[$jcp->order] = $jcp->name;
                }
            }
        }

        ksort($columnMap);
        $columns = array_values(array_map(
            fn ($order, $name) => ['order' => (int) $order, 'name' => $name],
            array_keys($columnMap),
            $columnMap
        ));

        // ── Build rows ─────────────────────────────────────────────────────
        $rows = $jobs->map(function ($job) use ($matchIndex) {
            $movement = $job->movement;
            
            // Resolve match via movement's direct FK or team+date index
            $match = $movement?->match;
            if (!$match && $job->team) {
                $key   = $job->team->code . '_' . $movement?->window_start?->toDateString();
                $match = $matchIndex[$key] ?? null;
            }

            // Map job checkpoints to a simple array indexed by order
            $checkpoints = collect($job->checkpoints ?? [])
                ->map(fn ($cp) => [
                    'order'            => $cp->order,
                    'name'             => $cp->name,
                    'state'            => $cp->state,
                    'scheduled_at'     => $cp->scheduled_at?->format('H:i'),
                    'completed_at'     => $cp->completed_at?->format('H:i'),
                    'scheduled_ts'     => $cp->scheduled_at?->timestamp,
                    'completed_ts'     => $cp->completed_at?->timestamp,
                    'is_on_time'       => $cp->is_on_time,
                    'delay_minutes'    => $cp->delay_minutes,
                    'bags_loaded'      => $cp->bags_loaded,
                    'oversized_pieces' => $cp->oversized_pieces,
                ])
                ->values();

            // Use EventTeam data if available, fallback to direct Team
            $eventTeam = $job->eventTeam;
            $team = $eventTeam?->team ?? $job->team;
            $flights = $eventTeam?->flights ?? collect();
            
            // Match flight to movement kind (arrival/departure)
            $primaryFlight = $flights->where('direction', $movement?->kind)->first() 
                ?? $flights->first();
            
            $stay = $eventTeam?->stay;

            // Extract flight date/time (use actual if available, otherwise scheduled)
            $flightDateTime = $primaryFlight?->actual_at ?? $primaryFlight?->scheduled_at;
            $originAirport = $primaryFlight?->originAirport;
            $destAirport = $primaryFlight?->destinationAirport;

            return [
                'job_id'           => $job->id,
                'movement_id'      => $movement?->id,
                'kind'             => $movement?->kind,
                'movement_status'  => $movement?->status,
                'job_status'       => $job->status,

                // Team (from EventTeam)
                'flag'             => $team?->flag ?? '',
                'team_code'        => $team?->code ?? '',
                'team_name'        => $team?->team_name ?? '',
                'hotel'            => $stay?->hotel_name ?? '',
                'flight_number'    => $primaryFlight?->flight_number ?? '',
                'flight_date'      => $flightDateTime?->format('Y-m-d'),
                'flight_time'      => $flightDateTime?->format('H:i'),
                
                // Debug info
                'has_event_team'   => $eventTeam !== null,
                'flights_count'    => $flights->count(),
                'has_stay'         => $stay !== null,

                // Match (for 'match' kind)
                'match_number'     => $match?->match_number ?? '',
                'match_date'       => $match?->match_date?->format('Y-m-d') ?? $flightDateTime?->format('Y-m-d') ?? $movement?->window_start?->format('Y-m-d'),
                'match_date_label' => $match?->match_date?->format('j-M') ?? $flightDateTime?->format('j-M') ?? $movement?->window_start?->format('j-M'),
                'kick_off'         => $match?->kick_off?->format('H:i') ?? '',
                'stadium'          => $match?->venue ?? $movement?->to_location ?? '',

                // Generic locations (use airports from flights if available)
                'from_location'    => $originAirport?->name ?? $movement?->from_location ?? '',
                'to_location'      => $destAirport?->name ?? $movement?->to_location ?? '',
                'window_start'     => $movement?->window_start?->format('H:i'),
                'window_end'       => $movement?->window_end?->format('H:i'),

                'checkpoints'      => $checkpoints,
            ];
        })
        ->sortBy([['match_date', 'asc'], ['team_code', 'asc']])
        ->values();

        // ── Kind counts for the filter pills ──────────────────────────────
        $kindCounts = Movement::select('kind', DB::raw('count(*) as total'))
            ->whereHas('job') // Only count movements that have jobs
            ->groupBy('kind')
            ->pluck('total', 'kind');

        // ── Date filter options from movement window_start for the selected kind ──
        $dates = Movement::where('kind', $kind)
            ->whereHas('job') // Only include movements with jobs
            ->when(!empty($filterEvent), fn ($q) => $q->where('event_id', $filterEvent))
            ->selectRaw('DATE(window_start) as d')
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('d')
            ->map(fn ($d) => [
                'value' => $d,
                'label' => Carbon::parse($d)->format('D j M'),
            ]);

        // ── Event filter options ──
        $events = Event::orderBy('name')->get(['id', 'name']);

        return Inertia::render('KitTruckDashboard', [
            'rows'       => $rows,
            'columns'    => $columns,
            'kindCounts' => $kindCounts,
            'dates'      => $dates,
            'events'     => $events,
            'filters'    => ['kind' => $kind, 'date' => $filterDate, 'event' => $filterEvent],
        ]);
    }
}
