<?php

namespace App\Http\Controllers;

use App\Models\GameMatch;
use App\Models\Movement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KitTruckDashboardController extends Controller
{
    public function index(Request $request)
    {
        $kind       = $request->query('kind', 'match');
        $filterDate = $request->query('date');

        // Load movements of the selected kind with all needed relations
        $movements = Movement::with([
            'team',
            'match',
            'checkpointTemplate.checkpoints', // fallback column defs when no jobs
            'job.checkpoints'                  => fn ($q) => $q->orderBy('order'),
        ])
            ->where('kind', $kind)
            ->when($filterDate, fn ($q) => $q->whereDate('window_start', $filterDate))
            ->orderBy('window_start')
            ->get();

        // Pre-load all matches for cross-referencing movements that have no match_id
        $allMatches  = GameMatch::with(['team1', 'team2'])->get();
        $matchIndex  = [];
        foreach ($allMatches as $m) {
            $date = $m->match_date?->toDateString();
            if ($m->team1_id && $date) $matchIndex[$m->team1_id . '_' . $date] = $m;
            if ($m->team2_id && $date) $matchIndex[$m->team2_id . '_' . $date] = $m;
        }

        // ── Build column definitions ───────────────────────────────────────
        // Priority 1: job checkpoint snapshots (have order + snapshotted name)
        $columnMap = []; // order => name
        foreach ($movements as $movement) {
            foreach ($movement->job?->checkpoints ?? [] as $jcp) {
                if (!isset($columnMap[$jcp->order])) {
                    $columnMap[$jcp->order] = $jcp->name;
                }
            }
        }

        // Priority 2: checkpoint template library (for movements without jobs yet)
        if (empty($columnMap)) {
            foreach ($movements as $movement) {
                if ($movement->checkpointTemplate) {
                    foreach ($movement->checkpointTemplate->checkpoints as $cp) {
                        $order = $cp->pivot->order ?? $cp->id;
                        if (!isset($columnMap[$order])) {
                            $columnMap[$order] = $cp->name;
                        }
                    }
                    break; // one template is enough for the column scaffold
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
        $rows = $movements->map(function ($movement) use ($matchIndex) {
            // Resolve match via direct FK or team+date index
            $match = $movement->match;
            if (!$match && $movement->team) {
                $key   = $movement->team->code . '_' . $movement->window_start?->toDateString();
                $match = $matchIndex[$key] ?? null;
            }

            // Map job checkpoints to a simple array indexed by order
            $checkpoints = collect($movement->job?->checkpoints ?? [])
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

            return [
                'movement_id'      => $movement->id,
                'kind'             => $movement->kind,
                'movement_status'  => $movement->status,
                'job_status'       => $movement->job?->status,

                // Team
                'flag'             => $movement->team?->flag ?? '',
                'team_code'        => $movement->team?->code ?? '',
                'team_name'        => $movement->team?->team_name ?? '',
                'hotel'            => $movement->team?->hotel_name ?? $movement->from_location ?? '',
                'flight_number'    => $movement->flight_number ?? $movement->team?->flight_number ?? '',
                'sc_liaison'       => $movement->team?->sc_liaison_name ?? '',

                // Match (for 'match' kind)
                'match_number'     => $match?->match_number ?? '',
                'match_date'       => $match?->match_date?->format('Y-m-d') ?? $movement->window_start?->format('Y-m-d'),
                'match_date_label' => $match?->match_date?->format('j-M') ?? $movement->window_start?->format('j-M'),
                'kick_off'         => $match?->kick_off?->format('H:i') ?? '',
                'stadium'          => $match?->venue ?? $movement->to_location ?? '',

                // Generic locations (for all kinds)
                'from_location'    => $movement->from_location ?? '',
                'to_location'      => $movement->to_location ?? '',
                'window_start'     => $movement->window_start?->format('H:i'),
                'window_end'       => $movement->window_end?->format('H:i'),

                'checkpoints'      => $checkpoints,
            ];
        })
        ->sortBy([['match_date', 'asc'], ['team_code', 'asc']])
        ->values();

        // ── Kind counts for the filter pills ──────────────────────────────
        $kindCounts = Movement::select('kind', DB::raw('count(*) as total'))
            ->groupBy('kind')
            ->pluck('total', 'kind');

        // ── Date filter options from movement window_start for the selected kind ──
        $dates = Movement::where('kind', $kind)
            ->selectRaw('DATE(window_start) as d')
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('d')
            ->map(fn ($d) => [
                'value' => $d,
                'label' => Carbon::parse($d)->format('D j M'),
            ]);

        return Inertia::render('KitTruckDashboard', [
            'rows'       => $rows,
            'columns'    => $columns,
            'kindCounts' => $kindCounts,
            'dates'      => $dates,
            'filters'    => ['kind' => $kind, 'date' => $filterDate],
        ]);
    }
}
