<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Event;
use App\Models\GameMatch;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\Team;
use App\Models\TeamFlight;
use App\Models\TeamStay;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KitTruckDashboardController extends Controller
{
    /**
     * Library checkpoints that are the same real step under two entries (old code => new code),
     * merged into one column under the new name: the match-day back-at-hotel step (CK108 → CK94,
     * 18 Oct 2026) and the arrival kit load end and offload end (CK90 → CK123, CK93 → CK126).
     * The stat tiles in KitTruckDashboard.vue list both codes for each.
     */
    private const SAME_STEP = [
        'CK108' => 'CK94',
        'CK90' => 'CK123',
        'CK93' => 'CK126',
    ];

    public function index(Request $request)
    {
        return Inertia::render('KitTruckDashboard', $this->dashboard($request));
    }

    /** Everything the page shows, for the current filters; the Excel export reuses it. */
    private function dashboard(Request $request): array
    {
        $kind              = $request->query('kind', 'match');
        $filterDate        = $request->query('date');
        // No event parameter = the active event; an empty one = all events.
        $filterEvent       = $request->has('event')
            ? $request->query('event')
            : $request->session()->get('active_event_id');
        $filterFunctionalArea = $request->query('functional_area');

        // Only events the user may open and, unless they see every area, their own functional areas.
        $user = $request->user();
        $allAreas = $user->can('movements.view-all-functional-areas') || $user->can('jobs.view-all-functional-areas');
        $scope = fn ($q) => $q
            ->when(! empty($filterEvent), fn ($q) => $q->where('event_id', $filterEvent))
            ->when(! $user->canAccessAllEvents(), fn ($q) => $q->whereIn('event_id', $user->accessibleEventIds()))
            ->when(! $allAreas, fn ($q) => $q->whereIn('functional_area', $user->functionalAreaCodes()));

        // Load jobs of the selected movement kind with all needed relations
        $jobs = JobOperation::with([
            'movement.team.country',
            'movement.match',
            'checkpoints' => fn ($q) => $q->orderBy('order'),
            'checkpoints.checkpoint', // Load checkpoint library reference
        ])
            ->whereHas('movement', function($q) use ($kind) {
                $q->where('kind', $kind);
            })
            ->where($scope)
            ->when(!empty($filterFunctionalArea), fn ($q) => $q->where('functional_area', $filterFunctionalArea))
            ->when($filterDate, fn ($q) => $q->whereHas('movement', function($query) use ($filterDate) {
                $query->whereDate('window_start', $filterDate);
            }))
            ->orderBy('dispatched_at')
            ->get();

        // Load Team data with flights and accommodation for all jobs
        $eventTeamPairs = $jobs->map(fn($j) => [
            'event_id' => $j->event_id, 
            'team_id' => $j->team_id
        ])->unique()->filter()->values();
        
        $eventTeams = collect();
        if ($eventTeamPairs->isNotEmpty()) {
            // First load Team records
            $eventTeams = Team::with('country')
                ->whereIn('id', $eventTeamPairs->pluck('team_id'))
                ->get()
                ->keyBy(fn($team) => $team->event_id . '_' . $team->id);
            
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
            
            // Attach flights and stays to Team records
            foreach ($eventTeams as $key => $team) {
                $team->setRelation('flights', $flightsMap[$key] ?? collect());
                $team->setRelation('stay', $staysMap[$key] ?? null);
            }
        }

        // Attach Team (with flights/stay) to each job
        foreach ($jobs as $job) {
            $key = $job->event_id . '_' . $job->team_id;
            $job->eventTeam = $eventTeams[$key] ?? null;
        }

        // Pre-load all matches for cross-referencing jobs that have no match_id
        $allMatches  = GameMatch::with(['team1', 'team2'])->whereIn('event_id', $jobs->pluck('event_id')->unique())->get();
        $matchIndex  = [];
        foreach ($allMatches as $m) {
            $date = $m->match_date?->toDateString();
            if ($m->team1_id && $date) $matchIndex[$m->team1_id . '_' . $date] = $m;
            if ($m->team2_id && $date) $matchIndex[$m->team2_id . '_' . $date] = $m;
        }

        // ── Build column definitions ───────────────────────────────────────
        // One column per library checkpoint (renames and template changes can't split or mix a
        // column), headed by its current name, placed by where it usually sits in the job.
        $keyOf = function ($jcp, array &$seen): string {
            $code = $jcp->checkpoint?->code;
            $code = self::SAME_STEP[$code] ?? $code;
            $base = $code ? "k:{$code}" : 'n:'.mb_strtoupper(trim((string) $jcp->name));
            $seen[$base] = ($seen[$base] ?? 0) + 1;

            // The same checkpoint twice in one job gets a column per occurrence.
            return $seen[$base] > 1 ? "{$base}#{$seen[$base]}" : $base;
        };
        $library = \App\Models\Checkpoint::whereIn('code', array_values(self::SAME_STEP))->pluck('name', 'code');
        $columnMap = [];
        $stepKeys = [];
        foreach ($jobs as $job) {
            $seen = [];
            foreach ($job->checkpoints ?? [] as $jcp) {
                $key = $keyOf($jcp, $seen);
                $stepKeys[$jcp->id] = $key;
                $code = self::SAME_STEP[$jcp->checkpoint?->code] ?? $jcp->checkpoint?->code;
                $columnMap[$key] ??= [
                    'name' => $library[$code] ?? $jcp->checkpoint?->name ?? $jcp->name,
                    'requires_baggage_count' => $jcp->checkpoint?->requires_baggage_count ?? false,
                    'position' => $jcp->order,
                ];
                $columnMap[$key]['position'] = min($columnMap[$key]['position'], $jcp->order);
            }
        }

        uasort($columnMap, fn ($a, $b) => $a['position'] <=> $b['position']);
        $columns = collect($columnMap)->map(fn ($data, $key) => [
            'key' => $key,
            'name' => $data['name'],
            'requires_baggage_count' => $data['requires_baggage_count'],
        ])->values()->all();

        // ── Build rows ─────────────────────────────────────────────────────
        $rows = $jobs->map(function ($job) use ($matchIndex, $stepKeys) {
            $movement = $job->movement;
            
            // Resolve match via movement's direct FK or team+date index
            $match = $movement?->match;
            if (!$match && $job->team) {
                $key   = $job->team->id . '_' . $movement?->window_start?->toDateString();
                $match = $matchIndex[$key] ?? null;
            }

            // Each step carries its column key and library code (the stat tiles look steps up by code).
            $checkpoints = collect($job->checkpoints ?? [])
                ->map(fn ($cp) => [
                    'key'              => $stepKeys[$cp->id],
                    'code'             => $cp->checkpoint?->code,
                    'order'            => $cp->order,
                    'name'             => $cp->name,
                    'state'            => $cp->state,
                    'scheduled_at'     => $cp->scheduled_at?->format('H:i'),
                    'completed_at'     => $cp->completed_at?->format('H:i'),
                    'scheduled_ts'     => $cp->scheduled_at?->timestamp,
                    'completed_ts'     => $cp->completed_at?->timestamp,
                    'is_on_time'       => $cp->is_on_time,
                    'delay_minutes'    => $cp->delay_minutes,
                    'planned_bags'     => $cp->planned_bags,
                    'bags_loaded'      => $cp->bags_loaded,
                    'food_bags'        => $cp->food_bags,
                    'oversized_pieces' => $cp->oversized_pieces,
                ])
                ->values();

            // Use the event-scoped Team (with flights/stay attached) if available, fallback to direct Team
            $eventTeam = $job->eventTeam;
            $team = $eventTeam ?? $job->team;
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
                'event_id'         => $job->event_id,
                'movement_id'      => $movement?->id,
                'kind'             => $movement?->kind,
                'movement_status'  => $movement?->status,
                'job_status'       => $job->status,

                // Team
                'flag'             => $team?->flag ?? '',
                'country_code'     => $team?->country_id ?? '',
                'team_code'        => $team?->code ?? '',
                'team_name'        => $team?->team_name ?? '',
                'hotel'            => $stay?->hotel_name ?? '',
                'flight_number'    => $primaryFlight?->flight_number ?? '',
                'flight_date'      => $flightDateTime?->format('Y-m-d'),
                'flight_time'      => $flightDateTime?->format('H:i'),
                'flight_scheduled_at' => $primaryFlight?->scheduled_at?->format('H:i'),
                'flight_actual_at'    => $primaryFlight?->actual_at?->format('H:i'),
                
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
            ->where($scope)
            ->groupBy('kind')
            ->pluck('total', 'kind');

        // ── Date filter options from movement window_start for the selected kind ──
        $dates = Movement::where('kind', $kind)
            ->whereHas('job') // Only include movements with jobs
            ->where($scope)
            ->selectRaw('DATE(window_start) as d')
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('d')
            ->map(fn ($d) => [
                'value' => $d,
                'label' => Carbon::parse($d)->format('D j M'),
            ]);

        // ── Event filter options ──
        $events = Event::accessibleTo($user)->orderBy('name')->get(['id', 'name']);

        // ── Functional area options ──
        $functionalAreas = array_values(array_filter([
            ['value' => 'LOG', 'label' => 'LOG - Logistics'],
            ['value' => 'AND', 'label' => 'AND - Arrival and Departure'],
            ['value' => 'MOB', 'label' => 'MOB - Mobility'],
        ], fn ($fa) => $allAreas || in_array($fa['value'], $user->functionalAreaCodes(), true)));

        return [
            'rows'            => $rows,
            'columns'         => $columns,
            'kindCounts'      => $kindCounts,
            'dates'           => $dates,
            'events'          => $events,
            'functionalAreas' => $functionalAreas,
            'filters'         => [
                'kind' => $kind, 
                'date' => $filterDate, 
                'event' => $filterEvent ? (int) $filterEvent : '',
                'functional_area' => $filterFunctionalArea,
            ],
        ];
    }

    private const KIND_LABELS = ['arrival' => 'Arrivals', 'departure' => 'Departures', 'match' => 'Match Day', 'training' => 'Training', 'transfer' => 'Transfer'];

    /**
     * The stat tiles the page shows above the table, sent with the export so the file carries
     * exactly those figures (they are worked out in the browser). Plain text only, size-capped.
     *
     * @return list<array{title: string, tiles: list<array{label: string, value: string|int|null, sub: ?string}>}>
     */
    private function statsFrom(Request $request): array
    {
        $raw = json_decode((string) $request->query('stats', ''), true);
        if (! is_array($raw)) {
            return [];
        }

        $text = fn ($v) => is_scalar($v) ? mb_substr(trim((string) $v), 0, 120) : null;

        return collect($raw)->take(6)->filter(fn ($g) => is_array($g) && is_array($g['tiles'] ?? null))
            ->map(fn ($g) => [
                'title' => $text($g['title'] ?? '') ?? '',
                'tiles' => collect($g['tiles'])->take(12)->filter(fn ($t) => is_array($t) && $text($t['label'] ?? null))
                    ->map(fn ($t) => [
                        'label' => $text($t['label']),
                        'value' => is_int($t['value'] ?? null) ? $t['value'] : $text($t['value'] ?? null),
                        'sub' => $text($t['sub'] ?? null) ?: null,
                        'tone' => in_array($t['tone'] ?? null, ['late', 'early'], true) ? $t['tone'] : null,
                    ])->values()->all(),
            ])
            ->filter(fn ($g) => $g['tiles'])
            ->values()->all();
    }

    /**
     * The table as shown, for the same movement type, event, date and functional area (plus the
     * team search, `q`): one row per job, Planned / Actual / Late-or-early / +/- min per checkpoint.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->dashboard($request);
        $filters = $data['filters'];
        $kind = $filters['kind'];
        $search = mb_strtolower(trim((string) $request->query('q', '')));
        $rows = collect($data['rows'])
            ->filter(fn ($r) => $search === '' || str_contains(mb_strtolower($r['team_code'].' '.$r['team_name']), $search))
            ->values();
        $columns = $data['columns'];

        $eventNames = Event::whereIn('id', $rows->pluck('event_id')->unique())->pluck('name', 'id');
        $event = $filters['event'] ? Event::find($filters['event']) : null;
        $allEvents = ! $event;

        $date = fn ($d) => $d ? Carbon::parse($d)->format('D j M Y') : null;
        $name = fn ($v) => is_object($v) ? ($v->name ?? null) : ($v ?: null);
        // Fixed columns per kind, as on the page: [header, value].
        $fixed = match ($kind) {
            'match' => [['Match', fn ($r) => $r['match_number'] ?: null], ['Date', fn ($r) => $date($r['match_date'])], ['KO', fn ($r) => $r['kick_off'] ?: null],
                ['Hotel', fn ($r) => $name($r['hotel'])], ['Stadium', fn ($r) => $name($r['stadium'])]],
            'arrival' => [['Flight', fn ($r) => $r['flight_number'] ?: null], ['Date', fn ($r) => $date($r['match_date'])],
                ['Flight planned', fn ($r) => $r['flight_scheduled_at']], ['Flight actual', fn ($r) => $r['flight_actual_at']],
                ['From', fn ($r) => $r['from_location'] ?: null], ['To', fn ($r) => $r['to_location'] ?: null], ['Hotel', fn ($r) => $name($r['hotel'])]],
            'departure' => [['Date', fn ($r) => $date($r['match_date'])], ['From', fn ($r) => $r['from_location'] ?: null],
                ['Hotel', fn ($r) => $name($r['hotel'])], ['To', fn ($r) => $r['to_location'] ?: null]],
            default => [['Date', fn ($r) => $date($r['match_date'])], ['From', fn ($r) => $r['from_location'] ?: null], ['To', fn ($r) => $r['to_location'] ?: null]],
        };
        array_unshift($fixed, ['PMA', fn ($r) => $r['team_code'] ?: null], ['Team', fn ($r) => $r['team_name'] ?: null]);
        if ($allEvents) {
            array_unshift($fixed, ['Event', fn ($r) => $eventNames[$r['event_id']] ?? null]);
        }
        $fixed[] = ['Status', fn ($r) => $r['job_status'] ? JobOperation::statusLabel($r['job_status']) : null];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr(self::KIND_LABELS[$kind] ?? ucfirst($kind), 0, 31));
        $sheet->setCellValue('A1', 'Movement Tracking · '.(self::KIND_LABELS[$kind] ?? ucfirst($kind)).' · '.($event?->name ?? 'All events'));
        $sheet->setCellValue('A2', implode(' · ', array_filter([
            $filters['date'] ? 'Date '.$date($filters['date']) : 'All dates',
            $filters['functional_area'] ? 'Functional area '.$filters['functional_area'] : null,
            $search !== '' ? 'Team search “'.$request->query('q').'”' : null,
            $rows->count().' job(s)',
            'Exported '.now()->format('D j M Y H:i'),
        ])));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->getColor()->setRGB('5B6472');

        $cell = fn (int $c, int $r) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c).$r;

        // The stat tiles above the table, laid out like the page's strip: group titles over
        // one card per tile (value, label, note), groups split by a darker rule.
        $h = 4;
        $stats = $this->statsFrom($request);
        $tileCols = 0;
        if ($stats) {
            $hasNotes = collect($stats)->flatMap(fn ($g) => $g['tiles'])->contains(fn ($t) => $t['sub']);
            $last = $hasNotes ? 7 : 6;
            $col = 1;
            foreach ($stats as $g => $group) {
                $from = $col;
                foreach ($group['tiles'] as $tile) {
                    $sheet->setCellValue($cell($col, 5), $tile['value'] ?? '—');
                    $sheet->setCellValue($cell($col, 6), $tile['label']);
                    if ($hasNotes) {
                        $sheet->setCellValue($cell($col, 7), $tile['sub']);
                    }
                    $value = $sheet->getStyle($cell($col, 5))->getFont()->setBold(true)->setSize(14);
                    $value->getColor()->setRGB(['late' => 'B91C1C', 'early' => '047857'][$tile['tone']] ?? '111827');
                    $card = $sheet->getStyle($cell($col, 5).':'.$cell($col, $last));
                    $card->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FB');
                    $card->getBorders()->getOutline()->setBorderStyle('thin')->getColor()->setRGB('E3E6EA');
                    $card->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
                    $sheet->getStyle($cell($col, 6))->getFont()->setSize(9)->getColor()->setRGB('4B5563');
                    $sheet->getStyle($cell($col, 7))->getFont()->setSize(8)->setItalic(true)->getColor()->setRGB('6B7280');
                    $col++;
                }
                $sheet->setCellValue($cell($from, 4), mb_strtoupper($group['title']));
                if ($col - 1 > $from) {
                    $sheet->mergeCells($cell($from, 4).':'.$cell($col - 1, 4));
                }
                $sheet->getStyle($cell($from, 4))->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('6B7280');
                if ($g > 0) {
                    $sheet->getStyle($cell($from, 4).':'.$cell($from, $last))->getBorders()->getLeft()->setBorderStyle('medium')->getColor()->setRGB('9CA3AF');
                }
            }
            $tileCols = $col - 1;
            $sheet->getRowDimension(5)->setRowHeight(28);
            $sheet->getRowDimension(6)->setRowHeight(30);
            if ($hasNotes) {
                $sheet->getRowDimension(7)->setRowHeight(24);
            }
            $h = $last + 2;
        }

        // Two header rows: group names (row $h) over Planned / Actual / Late-or-early (row $h + 1).
        $col = 1;
        foreach ($fixed as [$header]) {
            $sheet->setCellValue($cell($col, $h), $header);
            $sheet->mergeCells($cell($col, $h).':'.$cell($col, $h + 1));
            $col++;
        }
        $cpStart = [];
        foreach ($columns as $cp) {
            $subs = $cp['requires_baggage_count'] ? ['Planned bags', 'Actual bags', 'Food bags', 'Oversized'] : ['Planned', 'Actual', 'Late / early (min)', '+/- min'];
            $cpStart[$cp['key']] = $col;
            $sheet->setCellValue($cell($col, $h), $cp['name']);
            $sheet->mergeCells($cell($col, $h).':'.$cell($col + count($subs) - 1, $h));
            foreach ($subs as $i => $sub) {
                $sheet->setCellValue($cell($col + $i, $h + 1), $sub);
            }
            $col += count($subs);
        }
        $lastCol = max($col - 1, 1);
        $headerRange = 'A'.$h.':'.$cell($lastCol, $h + 1);
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);

        $r = $h + 2;
        foreach ($rows as $row) {
            $c = 1;
            foreach ($fixed as [, $value]) {
                $sheet->setCellValue($cell($c++, $r), $value($row));
            }
            $byKey = collect($row['checkpoints'])->keyBy('key');
            foreach ($columns as $cp) {
                $start = $cpStart[$cp['key']];
                $point = $byKey->get($cp['key']);
                if (! $point) {
                    continue;
                }
                if ($cp['requires_baggage_count']) {
                    foreach (['planned_bags', 'bags_loaded', 'food_bags', 'oversized_pieces'] as $i => $field) {
                        $sheet->setCellValue($cell($start + $i, $r), $point[$field]);
                    }
                    continue;
                }
                $sheet->setCellValue($cell($start, $r), $point['scheduled_at']);
                $sheet->setCellValue($cell($start + 1, $r), $point['completed_at']);
                if ($point['scheduled_ts'] && $point['completed_ts']) {
                    $delta = (int) round(($point['completed_ts'] - $point['scheduled_ts']) / 60);
                    $sheet->setCellValue($cell($start + 2, $r), match (true) {
                        $delta > 0 => 'Late '.$delta,
                        $delta < 0 => 'Early '.abs($delta),
                        default => 'On time',
                    });
                    // The same variance as a signed number (+ late, - early), for sorting and sums.
                    $sheet->setCellValue($cell($start + 3, $r), $delta);
                    $sheet->getStyle($cell($start + 3, $r))->getNumberFormat()->setFormatCode('+0;-0;0');
                    // Same thresholds as the page: late over 5 minutes, early under -5.
                    $tone = $delta > 5 ? 'FDE2E2' : ($delta < -5 ? 'DCFCE7' : 'ECFDF5');
                    $sheet->getStyle($cell($start + 1, $r).':'.$cell($start + 3, $r))->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($tone);
                }
            }
            $r++;
        }
        if ($rows->isEmpty()) {
            $sheet->setCellValue($cell(1, $h + 2), 'No jobs match these filters.');
        }

        // Width from the table's own text (the stat cards wrap): fixed columns count their header,
        // checkpoint columns their sub-header, since the checkpoint name spans several columns.
        for ($i = 1; $i <= max($lastCol, $tileCols); $i++) {
            $len = 0;
            for ($row = $i <= count($fixed) ? $h : $h + 1; $row < max($r, $h + 3); $row++) {
                $len = max($len, mb_strlen((string) $sheet->getCell($cell($i, $row))->getValue()));
            }
            $width = min(max($len + 2, 9), 40);
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))
                ->setWidth($i <= $tileCols ? max($width, 20) : $width);
        }
        $sheet->freezePane($cell(count($fixed) + 1, $h + 2));

        $filename = sprintf('Movement-Tracking_%s_%s_%s.xlsx',
            self::KIND_LABELS[$kind] ?? $kind,
            preg_replace('/[^A-Za-z0-9]+/', '-', $event?->short_name ?: ($event?->name ?? 'All-events')),
            $filters['date'] ? str_replace('-', '', $filters['date']) : now()->format('Ymd'));
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(fn () => $writer->save('php://output'), str_replace(' ', '-', $filename), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
