<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\GameMatch;
use App\Models\HotelCrewAssignment;
use App\Models\HotelDutyDay;
use App\Models\HotelVsaDay;
use App\Models\User;
use App\Services\HotelScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Duty Roster → Hotel schedule: supervisors, crew teams and kit runs per team hotel and match day. */
class HotelScheduleController extends Controller
{
    private const TIME = ['nullable', 'date_format:H:i'];

    public function __construct(private readonly HotelScheduleService $schedule)
    {
    }

    /** Supervisor and/or kit runs of one hotel on one day; only the keys sent are changed. */
    public function updateDay(Request $request): RedirectResponse
    {
        $eventId = $this->eventId($request);
        $validated = $request->validate([
            'hotel' => ['required', 'string', Rule::in($this->schedule->hotelKeys($eventId))],
            'date' => 'required|date_format:Y-m-d',
            'supervisor_id' => ['sometimes', 'nullable', 'integer', Rule::exists('roster_members', 'user_id')->where('event_id', $eventId)],
            'departure' => 'sometimes|array',
            'departure.crews' => 'nullable|array|max:10',
            'departure.crews.*' => 'integer|min:1|max:99',
            'departure.time' => self::TIME,
            'arrival' => 'sometimes|array',
            'arrival.crews' => 'nullable|array|max:10',
            'arrival.crews.*' => 'integer|min:1|max:99',
            'arrival.time' => self::TIME,
        ]);

        $attrs = [];
        if (array_key_exists('supervisor_id', $validated)) {
            $attrs['supervisor_id'] = $validated['supervisor_id'];
        }
        foreach (['departure', 'arrival'] as $run) {
            if (isset($validated[$run])) {
                $attrs["{$run}_crews"] = $this->crewList($validated[$run]['crews'] ?? []);
                $attrs["{$run}_time"] = $validated[$run]['time'] ?? null;
            }
        }

        $day = HotelDutyDay::firstOrNew(['event_id' => $eventId, 'hotel' => $validated['hotel'], 'date' => $validated['date']]);
        $original = $day->getOriginal();
        $day->fill($attrs)->save();

        if ($day->wasChanged() || $day->wasRecentlyCreated) {
            AuditLog::change('Hotel schedule updated', "{$validated['hotel']} · {$validated['date']}", array_filter([
                'supervisor' => array_key_exists('supervisor_id', $attrs) ? (User::find($attrs['supervisor_id'])?->name ?? 'none') : null,
                'departure' => isset($attrs['departure_crews']) ? $this->runLabel($attrs['departure_crews'], $attrs['departure_time']) : null,
                'arrival' => isset($attrs['arrival_crews']) ? $this->runLabel($attrs['arrival_crews'], $attrs['arrival_time']) : null,
            ], fn ($v) => $v !== null), $day, $eventId);
        }

        return back();
    }

    /** The crew team handling one team's kit for one match (null clears it). */
    public function updateCrew(Request $request): RedirectResponse
    {
        $eventId = $this->eventId($request);
        $validated = $request->validate([
            'match_id' => ['required', 'integer', Rule::exists('matches', 'id')->where('event_id', $eventId)],
            'team_id' => 'required|integer',
            'crew_team' => 'nullable|integer|min:1|max:99',
        ]);

        $match = GameMatch::findOrFail($validated['match_id']);
        abort_unless(in_array((int) $validated['team_id'], [(int) $match->team1_id, (int) $match->team2_id], true), 422, 'That team does not play this match.');

        $key = ['match_id' => $match->id, 'team_id' => $validated['team_id']];
        if ($validated['crew_team'] === null) {
            HotelCrewAssignment::where($key)->delete();
        } else {
            HotelCrewAssignment::updateOrCreate($key, ['event_id' => $eventId, 'crew_team' => $validated['crew_team']]);
        }

        AuditLog::change('Hotel crew team set', "{$match->match_number} · team {$validated['team_id']}", ['crew_team' => $validated['crew_team'] ?? 'cleared'], eventId: $eventId);

        return back();
    }

    public function updateVsa(Request $request): RedirectResponse
    {
        $eventId = $this->eventId($request);
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'crews' => 'present|array|max:20',
            'crews.*' => 'integer|min:1|max:99',
        ]);

        $crews = $this->crewList($validated['crews']);
        if ($crews) {
            HotelVsaDay::updateOrCreate(['event_id' => $eventId, 'date' => $validated['date']], ['crews' => $crews]);
        } else {
            HotelVsaDay::where('event_id', $eventId)->where('date', $validated['date'])->delete();
        }

        AuditLog::change('Hotel schedule VSA crews set', $validated['date'], ['crews' => $crews ?: 'cleared'], eventId: $eventId);

        return back();
    }

    public function updateOffsets(Request $request): RedirectResponse
    {
        $eventId = $this->eventId($request);
        $validated = $request->validate([
            'report_before' => 'required|integer|min:0|max:720',
            'end_after' => 'required|integer|min:0|max:720',
        ]);

        $before = $this->schedule->offsets($eventId);
        $this->schedule->setOffsets($eventId, $validated['report_before'], $validated['end_after']);
        AuditLog::change('Hotel schedule times changed', Event::find($eventId)?->name, ['from' => $before, 'to' => $validated], eventId: $eventId);

        return back()->with('success', 'Report and shift end times saved.');
    }

    public function autoFill(Request $request): RedirectResponse
    {
        $eventId = $this->eventId($request);
        $filled = $this->schedule->autoFill($eventId);
        if ($filled) {
            AuditLog::change('Hotel schedule auto-filled', Event::find($eventId)?->name, ['filled' => $filled], eventId: $eventId);
        }

        return back()->with('success', $filled ? "Filled {$filled} empty crew team(s) and kit run(s)." : 'Nothing left to fill.');
    }

    public function export(Request $request): StreamedResponse
    {
        $eventId = $this->eventId($request);
        $data = $this->schedule->build($eventId);
        $event = Event::find($eventId);
        $days = collect($data['days']);
        $supervisors = collect($data['supervisors'])->keyBy('id');
        $offsets = $data['offsets'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Hotel Team Schedule');
        $col = fn (int $i) => Coordinate::stringFromColumnIndex($i);
        $fill = fn (string $range, string $rgb) => $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
        $white = fn (string $range) => $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $firstDay = 2;
        $sumCol = $firstDay + $days->count();
        $lastCol = $col($sumCol + 2);
        $hm = fn (int $m) => intdiv($m, 60) . 'h' . ($m % 60 ? str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT) : '');

        $sheet->setCellValue('A1', strtoupper(trim(($event?->name ?? '') . ' — hotel-based kit loading crew & supervisor schedule')));
        $sheet->mergeCells("A1:{$lastCol}1");
        $fill("A1:{$lastCol}1", '1F3864');
        $white('A1');
        $sheet->getStyle('A1')->getFont()->setSize(14);
        $range = $days->isEmpty() ? '' : \Carbon\Carbon::parse($days->first()['date'])->format('j M') . ' to ' . \Carbon\Carbon::parse($days->last()['date'])->format('j M Y');
        $sheet->setCellValue('A2', "{$range}  |  All times local Doha  |  Report time = KO −{$hm($offsets['report_before'])}  |  Shift ends KO +{$hm($offsets['end_after'])}");
        $sheet->mergeCells("A2:{$lastCol}2");
        $fill("A2:{$lastCol}2", '762828');
        $white('A2');

        $legend = [['1 team per hotel', '9DC3E6'], ['2 teams per hotel', 'C6E0B4'], ['3 teams per hotel', 'CCC0DA'], ['4+ teams per hotel (peak)', 'F4B6C2']];
        $sheet->setCellValue('A3', 'Legend:');
        foreach ($legend as $i => [$label, $rgb]) {
            $cell = $col($firstDay + $i * 2) . '3';
            $sheet->setCellValue($cell, $label);
            $sheet->mergeCells($cell . ':' . $col($firstDay + $i * 2 + 1) . '3');
            $fill($cell, $rgb);
        }

        // Stage band over the days, one merged block per run of the same stage.
        $start = 0;
        foreach ($days->values() as $i => $day) {
            $next = $days->values()[$i + 1] ?? null;
            $label = $day['rest'] ? 'REST DAY' : strtoupper($day['stage_name'] ?? '');
            $nextLabel = $next ? ($next['rest'] ? 'REST DAY' : strtoupper($next['stage_name'] ?? '')) : null;
            if ($nextLabel === $label) {
                continue;
            }
            $range4 = $col($firstDay + $start) . '4:' . $col($firstDay + $i) . '4';
            $sheet->setCellValue($col($firstDay + $start) . '4', $label);
            $sheet->mergeCells($range4);
            $fill($range4, $day['rest'] ? 'C00000' : '1F3864');
            $white($range4);
            $start = $i + 1;
        }

        $sheet->setCellValue('A5', 'Hotel');
        foreach ($days->values() as $i => $day) {
            $sheet->setCellValue($col($firstDay + $i) . '5', \Carbon\Carbon::parse($day['date'])->format("D\nj-M"));
        }
        foreach (['Active days', 'Peak crew', '# Teams'] as $i => $label) {
            $sheet->setCellValue($col($sumCol + $i) . '5', $label);
        }
        $fill("A5:{$lastCol}5", '1F3864');
        $white("A5:{$lastCol}5");
        $fill($col($sumCol) . "5:{$lastCol}5", 'BF8F00');
        $sheet->getStyle("A4:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

        $row = 6;
        $colours = [1 => '9DC3E6', 2 => 'C6E0B4', 3 => 'CCC0DA'];
        $totals = [];
        $summaryTotals = ['active' => 0, 'peak' => 0, 'teams' => 0];
        foreach ($data['hotels'] as $n => $hotel) {
            $sheet->setCellValue("A{$row}", ($n + 1) . '. ' . $hotel['name']);
            $fill("A{$row}", '762828');
            $white("A{$row}");
            $sheet->setCellValue('A' . ($row + 1), 'Kits departure to venue');
            $sheet->setCellValue('A' . ($row + 2), 'Kits arrival from venue');

            $active = 0;
            $peak = 0;
            foreach ($days->values() as $i => $day) {
                $cell = $col($firstDay + $i);
                if ($day['rest']) {
                    $sheet->setCellValue("{$cell}{$row}", 'REST DAY');
                    continue;
                }
                $d = $hotel['days'][$day['date']] ?? null;
                if (! $d || ! $d['teams']) {
                    continue;
                }
                $crew = $this->crewCount($d['teams']);
                $active++;
                $peak = max($peak, $crew);
                $totals[$day['date']]['hotels'] = ($totals[$day['date']]['hotels'] ?? 0) + 1;
                $totals[$day['date']]['crew'] = ($totals[$day['date']]['crew'] ?? 0) + $crew;

                $lines = [count($d['teams']) . ' PMAs (' . $crew . ' crew)'];
                if ($d['supervisor_id'] && isset($supervisors[$d['supervisor_id']])) {
                    $lines[] = 'Spvr: ' . $supervisors[$d['supervisor_id']]['name'];
                }
                foreach ($d['teams'] as $t) {
                    $lines[] = $t['code'] . ' ' . ($t['report'] ?? '--:--') . ($t['crew'] ? ' · Crew ' . $t['crew'] : '');
                }
                $sheet->setCellValue("{$cell}{$row}", implode("\n", $lines));
                $fill("{$cell}{$row}", $colours[count($d['teams'])] ?? 'F4B6C2');
                $sheet->setCellValue("{$cell}" . ($row + 1), $this->runLabel($d['departure']['crews'], $d['departure']['time']));
                $sheet->setCellValue("{$cell}" . ($row + 2), $this->runLabel($d['arrival']['crews'], $d['arrival']['time']));
            }

            foreach ([$active, $peak, count($hotel['teams'])] as $i => $value) {
                $sheet->setCellValue($col($sumCol + $i) . $row, $value);
            }
            $fill($col($sumCol) . "{$row}:{$lastCol}" . ($row + 2), 'FCE4D6');
            $summaryTotals = ['active' => $summaryTotals['active'] + $active, 'peak' => max($summaryTotals['peak'], $peak), 'teams' => $summaryTotals['teams'] + count($hotel['teams'])];
            $row += 3;
        }

        $sheet->setCellValue("A{$row}", 'TOURNAMENT TOTALS');
        foreach ($days->values() as $i => $day) {
            if (isset($totals[$day['date']])) {
                $sheet->setCellValue($col($firstDay + $i) . $row, $totals[$day['date']]['hotels'] . 'H / ' . $totals[$day['date']]['crew'] . ' crew');
            }
        }
        foreach (array_values($summaryTotals) as $i => $value) {
            $sheet->setCellValue($col($sumCol + $i) . $row, $value);
        }
        $fill("A{$row}:{$lastCol}{$row}", 'BF8F00');
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue("A{$row}", 'ASPIRE VSA TEAM');
        $fill("A{$row}", '00B050');
        $white("A{$row}");
        foreach ($days->values() as $i => $day) {
            $crews = $data['vsa']->{$day['date']} ?? [];
            if ($crews) {
                $sheet->setCellValue($col($firstDay + $i) . $row, 'Crew ' . implode(', ', $crews));
            }
        }

        $grid = $sheet->getStyle("A5:{$lastCol}{$row}");
        $grid->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('7F7F7F');
        $grid->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($col($firstDay) . "6:{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(38);
        for ($c = $firstDay; $c <= $sumCol + 2; $c++) {
            $sheet->getColumnDimension($col($c))->setWidth($c >= $sumCol ? 10 : 18);
        }
        $sheet->freezePane($col($firstDay) . '6');

        $filename = implode('_', array_filter([
            'HOTEL_TEAM_SCHEDULE',
            $event?->short_name ? preg_replace('/[^A-Za-z0-9]/', '', $event->short_name) : null,
        ])) . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Crew teams needed: each distinct crew team assigned, plus one per team still without one. */
    private function crewCount(array $teams): int
    {
        $assigned = array_filter(array_column($teams, 'crew'));

        return count(array_unique($assigned)) + (count($teams) - count($assigned));
    }

    private function crewList(array $crews): array
    {
        $crews = array_values(array_unique(array_map('intval', $crews)));
        sort($crews);

        return $crews;
    }

    private function runLabel(?array $crews, ?string $time): ?string
    {
        if (! $crews && ! $time) {
            return null;
        }

        return trim(($crews ? 'Crew ' . implode(', ', $crews) : '') . ($time ? ' @ ' . substr($time, 0, 5) : ''));
    }

    private function eventId(Request $request): int
    {
        $eventId = $request->session()->get('active_event_id');
        abort_unless($eventId, 422, 'Select an event first.');
        abort_unless($request->user()->canAccessEvent((int) $eventId), 403);

        return (int) $eventId;
    }
}
