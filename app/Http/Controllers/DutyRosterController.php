<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\RosterMember;
use App\Models\RosterShift;
use App\Models\ShiftType;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The event's staff duty roster: who works which shift on which day. */
class DutyRosterController extends Controller
{
    private const WEEKS = [1, 2, 4, 6];

    public function index(Request $request): Response
    {
        $eventId = $this->eventId($request, required: false);
        [$from, $weeks] = $this->range($request);
        $to = $from->copy()->addWeeks($weeks)->subDay();
        $canManage = $request->user()->can('roster.manage');

        $members = $eventId ? $this->members($eventId) : collect();

        return Inertia::render('DutyRoster', [
            'from' => $from->toDateString(),
            'weeks' => $weeks,
            'shiftTypes' => $this->shiftTypes(),
            'members' => $members->map(fn (RosterMember $m) => [
                'user_id' => $m->user_id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'designation' => $m->designation ?? $m->user?->job_title,
            ])->values(),
            // 'userId|Y-m-d' => shift type id
            'shifts' => $eventId ? $this->shiftMap($eventId, $from, $to) : (object) [],
            'candidates' => $canManage && $eventId
                ? User::whereNotIn('id', $members->pluck('user_id'))->orderBy('name')->get(['id', 'name', 'email', 'job_title'])
                : [],
        ]);
    }

    public function addMembers(Request $request): RedirectResponse
    {
        $eventId = $this->eventId($request);
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:200',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $sort = (int) RosterMember::where('event_id', $eventId)->max('sort');
        $existing = RosterMember::where('event_id', $eventId)->pluck('user_id')->all();
        $added = User::whereIn('id', array_diff($validated['user_ids'], $existing))->orderBy('name')->get();

        foreach ($added as $user) {
            RosterMember::create(['event_id' => $eventId, 'user_id' => $user->id, 'sort' => ++$sort, 'designation' => $user->job_title]);
        }

        AuditLog::change('Duty roster staff added', $added->pluck('name')->implode(', '), ['count' => $added->count()], eventId: $eventId);

        return back()->with('success', $added->count() === 1 ? "{$added->first()->name} added to the roster." : "{$added->count()} staff added to the roster.");
    }

    public function updateMember(Request $request, User $user): RedirectResponse
    {
        $eventId = $this->eventId($request);
        $member = RosterMember::where('event_id', $eventId)->where('user_id', $user->id)->firstOrFail();
        $validated = $request->validate([
            'designation' => 'nullable|string|max:100',
            'move' => 'nullable|in:up,down',
        ]);

        if (array_key_exists('designation', $validated)) {
            $original = $member->getOriginal();
            $member->update(['designation' => $validated['designation']]);
            if ($member->wasChanged()) {
                AuditLog::change('Duty roster staff updated', $user->name, AuditLog::changes($member, $original), $member, $eventId);
            }
        }

        if ($move = $validated['move'] ?? null) {
            $this->move($eventId, $member, $move);
        }

        return back();
    }

    public function removeMember(Request $request, User $user): RedirectResponse
    {
        $eventId = $this->eventId($request);
        $member = RosterMember::where('event_id', $eventId)->where('user_id', $user->id)->firstOrFail();

        DB::transaction(function () use ($eventId, $member) {
            RosterShift::where('event_id', $eventId)->where('user_id', $member->user_id)->delete();
            $member->delete();
        });

        AuditLog::change('Duty roster staff removed', $user->name, [], eventId: $eventId);

        return back()->with('success', "{$user->name} removed from the roster.");
    }

    /** Sets (or clears, with a null shift) the shift on a set of staff/day cells. */
    public function assign(Request $request): RedirectResponse
    {
        $eventId = $this->eventId($request);
        $validated = $request->validate([
            'shift_type_id' => 'nullable|integer|exists:shift_types,id',
            'cells' => 'required|array|min:1|max:1000',
            'cells.*.user_id' => [
                'required', 'integer',
                Rule::exists('roster_members', 'user_id')->where('event_id', $eventId),
            ],
            'cells.*.date' => 'required|date_format:Y-m-d',
        ]);

        $cells = collect($validated['cells'])->unique(fn ($c) => $c['user_id'] . '|' . $c['date'])->values();
        $shiftTypeId = $validated['shift_type_id'] ?? null;

        DB::transaction(function () use ($eventId, $cells, $shiftTypeId) {
            if ($shiftTypeId === null) {
                foreach ($cells->groupBy('user_id') as $userId => $days) {
                    RosterShift::where('event_id', $eventId)->where('user_id', $userId)->whereIn('date', $days->pluck('date'))->delete();
                }

                return;
            }

            $now = now();
            RosterShift::upsert(
                $cells->map(fn ($c) => [
                    'event_id' => $eventId, 'user_id' => $c['user_id'], 'date' => $c['date'],
                    'shift_type_id' => $shiftTypeId, 'created_at' => $now, 'updated_at' => $now,
                ])->all(),
                ['event_id', 'user_id', 'date'],
                ['shift_type_id', 'updated_at'],
            );
        });

        $dates = $cells->pluck('date')->sort()->values();
        AuditLog::change('Duty roster updated', $cells->count() . ' day(s)', [
            'shift' => $shiftTypeId ? ShiftType::find($shiftTypeId)?->name : 'cleared',
            'staff' => User::whereIn('id', $cells->pluck('user_id')->unique())->pluck('name')->all(),
            'from' => $dates->first(),
            'to' => $dates->last(),
        ], eventId: $eventId);

        return back();
    }

    public function storeShiftType(Request $request): RedirectResponse
    {
        $type = ShiftType::create($this->validateShiftType($request) + ['sort' => (int) ShiftType::max('sort') + 1]);
        AuditLog::change('Shift type created', $type->name, ['to' => $type->only('name', 'code', 'color', 'start_time', 'end_time', 'is_leave')], $type);

        return back()->with('success', "Shift type {$type->name} created.");
    }

    public function updateShiftType(Request $request, ShiftType $shiftType): RedirectResponse
    {
        $original = $shiftType->getOriginal();
        $shiftType->update($this->validateShiftType($request, $shiftType));
        if ($shiftType->wasChanged()) {
            AuditLog::change('Shift type updated', $shiftType->name, AuditLog::changes($shiftType, $original), $shiftType);
        }

        return back()->with('success', "Shift type {$shiftType->name} saved.");
    }

    public function destroyShiftType(ShiftType $shiftType): RedirectResponse
    {
        $used = RosterShift::where('shift_type_id', $shiftType->id)->count();
        if ($used) {
            return back()->with('error', "{$shiftType->name} is on {$used} roster day(s). Change those days first.");
        }

        $shiftType->delete();
        AuditLog::change('Shift type deleted', $shiftType->name);

        return back()->with('success', "Shift type {$shiftType->name} deleted.");
    }

    public function export(Request $request): StreamedResponse
    {
        $eventId = $this->eventId($request);
        [$from, $weeks] = $this->range($request);
        $to = $from->copy()->addWeeks($weeks)->subDay();
        $days = collect(CarbonPeriod::create($from, $to))->map(fn (Carbon $d) => $d->copy());
        $types = ShiftType::orderBy('sort')->orderBy('name')->get()->keyBy('id');
        $shifts = $this->shiftMap($eventId, $from, $to);
        $members = $this->members($eventId);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Duty Roster');
        $navy = '1F3864';
        $fill = fn (string $range, string $rgb) => $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
        $col = fn (int $i) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);

        $event = Event::find($eventId);
        $sheet->setCellValue('A1', strtoupper(trim(($event?->short_name ?? '') . ' DUTY ROSTER')));
        $sheet->mergeCells('A1:B1');
        $fill('A1:B1', $navy);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('FFFFFF');
        $sheet->setCellValue('C1', strtoupper($from->format('d M') . ' - ' . $to->format('d M Y')));
        $fill('C1', 'FFC000');
        $sheet->getStyle('C1')->getFont()->setBold(true)->setSize(13);

        // Legend: shift name over its hours, in the shift's colour.
        $c = 5;
        foreach ($types as $type) {
            $range = $col($c) . '1:' . $col($c + 1) . '1';
            $sheet->setCellValue($col($c) . '1', $type->name . ($type->is_leave ? '' : ' Shift'));
            $sheet->mergeCells($range);
            $fill($range, ltrim($type->color, '#'));
            $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB($this->textOn($type->color));
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            if ($type->hours()) {
                $sheet->setCellValue($col($c) . '2', $type->hours());
                $sheet->mergeCells($col($c) . '2:' . $col($c + 1) . '2');
                $sheet->getStyle($col($c) . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $c += 3;
        }

        $sheet->setCellValue('A4', '#');
        $sheet->setCellValue('B4', 'Employee Name');
        $sheet->setCellValue('C4', 'Designation');
        foreach (['A', 'B', 'C'] as $letter) {
            $sheet->mergeCells("{$letter}4:{$letter}5");
        }
        foreach ($days as $i => $day) {
            $sheet->setCellValue($col($i + 4) . '4', $day->format('j-M'));
            $sheet->setCellValue($col($i + 4) . '5', $day->format('D'));
        }
        $last = $col($days->count() + 3);
        $fill("A4:{$last}5", $navy);
        $head = $sheet->getStyle("A4:{$last}5");
        $head->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $head->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $row = 6;
        foreach ($members->values() as $i => $member) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $member->user?->name);
            $sheet->setCellValue("C{$row}", $member->designation ?? $member->user?->job_title);
            foreach ($days as $d => $day) {
                $type = $types[$shifts[$member->user_id . '|' . $day->toDateString()] ?? 0] ?? null;
                if (! $type) {
                    continue;
                }
                $cell = $col($d + 4) . $row;
                $sheet->setCellValue($cell, $type->name);
                $fill($cell, ltrim($type->color, '#'));
                $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB($this->textOn($type->color));
            }
            $row++;
        }

        $body = $sheet->getStyle("A4:{$last}" . max(5, $row - 1));
        $body->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BFBFBF');
        $sheet->getStyle("D6:{$last}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(26);
        foreach ($days->keys() as $d) {
            $sheet->getColumnDimension($col($d + 4))->setWidth(11);
        }
        $sheet->freezePane('D6');

        $filename = implode('_', array_filter([
            'DUTY_ROSTER',
            $event?->short_name ? preg_replace('/[^A-Za-z0-9]/', '', $event->short_name) : null,
            $from->format('dmY') . '-' . $to->format('dmY'),
        ])) . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function eventId(Request $request, bool $required = true): ?int
    {
        $eventId = $request->session()->get('active_event_id');
        if (! $eventId) {
            abort_if($required, 422, 'Select an event first.');

            return null;
        }
        abort_unless($request->user()->canAccessEvent((int) $eventId), 403);

        return (int) $eventId;
    }

    /** @return array{0: Carbon, 1: int} first day (a Sunday) and number of weeks */
    private function range(Request $request): array
    {
        try {
            $from = Carbon::parse($request->query('from', now()->toDateString()));
        } catch (\Exception) {
            $from = now();
        }
        $weeks = (int) $request->query('weeks', 2);

        return [$from->startOfWeek(Carbon::SUNDAY)->startOfDay(), in_array($weeks, self::WEEKS, true) ? $weeks : 2];
    }

    private function members(int $eventId): Collection
    {
        return RosterMember::where('event_id', $eventId)
            ->with('user:id,name,email,job_title')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    private function shiftTypes(): Collection
    {
        return ShiftType::orderBy('sort')->orderBy('name')->get()->map(fn (ShiftType $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'code' => $t->code,
            'color' => $t->color,
            'start_time' => $t->start_time ? substr($t->start_time, 0, 5) : null,
            'end_time' => $t->end_time ? substr($t->end_time, 0, 5) : null,
            'hours' => $t->hours(),
            'is_leave' => $t->is_leave,
        ]);
    }

    /** @return array<string, int> 'userId|Y-m-d' => shift type id */
    private function shiftMap(int $eventId, Carbon $from, Carbon $to): array
    {
        return RosterShift::where('event_id', $eventId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['user_id', 'date', 'shift_type_id'])
            ->mapWithKeys(fn (RosterShift $s) => [$s->user_id . '|' . $s->date->toDateString() => $s->shift_type_id])
            ->all();
    }

    private function move(int $eventId, RosterMember $member, string $direction): void
    {
        $list = $this->members($eventId)->values();
        $index = $list->search(fn ($m) => $m->id === $member->id);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if ($index === false || ! isset($list[$swap])) {
            return;
        }

        [$list[$index], $list[$swap]] = [$list[$swap], $list[$index]];
        DB::transaction(function () use ($list) {
            foreach ($list as $i => $m) {
                $m->update(['sort' => $i + 1]);
            }
        });
    }

    private function validateShiftType(Request $request, ?ShiftType $type = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('shift_types', 'name')->ignore($type?->id)],
            'code' => 'required|string|max:4',
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'start_time' => 'nullable|date_format:H:i|required_with:end_time',
            'end_time' => 'nullable|date_format:H:i|required_with:start_time',
            'is_leave' => 'boolean',
        ]);
        $validated['code'] = strtoupper($validated['code']);
        $validated['color'] = strtoupper($validated['color']);

        return $validated;
    }

    /** Black or white text, whichever reads better on the given fill. */
    private function textOn(string $hex): string
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '1F2937' : 'FFFFFF';
    }
}
