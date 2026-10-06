<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Movement;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ConflictDetectionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One vehicle, driver or supervisor's week: every movement they are on,
 * planned or already a job, as a Gantt with PDF (print) and Excel export.
 */
class ResourceScheduleController extends Controller
{
    private const TYPES = ['vehicle', 'driver', 'supervisor'];

    public function index(Request $request, ConflictDetectionService $conflicts): Response
    {
        [$type, $id, $weekStart] = $this->params($request);
        $resource = $id ? $this->resource($type, $id) : null;

        return Inertia::render('ResourceSchedule', [
            'type' => $type,
            'resourceId' => $resource ? $id : null,
            'resource' => $resource ? ['id' => $id, 'label' => $this->label($type, $resource), 'detail' => $this->detail($type, $resource)] : null,
            'weekStart' => $weekStart->toDateString(),
            'items' => $resource ? $this->items($request, $conflicts, $type, $resource, $weekStart)->all() : [],
            'bookedDates' => $resource ? $this->bookedDates($request, $type, $id) : [],
            'resources' => [
                'vehicle' => Vehicle::orderBy('code')->get(['id', 'code', 'plate_number', 'vehicle_type', 'capacity'])
                    ->map(fn ($v) => ['id' => $v->id, 'label' => $this->label('vehicle', $v), 'detail' => $this->detail('vehicle', $v)]),
                'driver' => Driver::orderBy('name')->get(['id', 'name', 'phone'])
                    ->map(fn ($d) => ['id' => $d->id, 'label' => $d->name, 'detail' => $d->phone]),
                'supervisor' => $this->supervisors()->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($u) => ['id' => $u->id, 'label' => $u->name, 'detail' => null]),
            ],
        ]);
    }

    public function export(Request $request, ConflictDetectionService $conflicts): StreamedResponse
    {
        [$type, $id, $weekStart] = $this->params($request);
        $resource = $this->resource($type, $id);
        abort_unless($resource, 404);

        $label = $this->label($type, $resource);
        $items = $this->items($request, $conflicts, $type, $resource, $weekStart);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Schedule');
        $sheet->setCellValue('A1', ucfirst($type).': '.$label);
        $sheet->setCellValue('A2', 'Week of '.$weekStart->format('j M Y').' – '.$weekStart->copy()->addDays(6)->format('j M Y'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $headers = ['Date', 'Start', 'End', 'Hours', 'Movement', 'Job', 'Status', 'Role', 'Team', 'From', 'To', 'Clashes'];
        $sheet->fromArray($headers, null, 'A4');
        $sheet->getStyle('A4:L4')->getFont()->setBold(true);
        $sheet->getStyle('A4:L4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');

        $row = 5;
        foreach ($items as $item) {
            $start = $item['start'] ? Carbon::parse($item['start']) : null;
            $end = $item['end'] ? Carbon::parse($item['end']) : null;
            $sheet->fromArray([
                $start?->format('D j M Y'),
                $start?->format('H:i'),
                $end ? $end->format('H:i').($start && ! $end->isSameDay($start) ? ' (+1)' : '') : null,
                $start && $end ? round($start->diffInMinutes($end, true) / 60, 2) : null,
                $item['code'],
                $item['job_code'] ?? '—',
                $item['job_code'] ? $item['job_status_label'] : 'Planned',
                $item['role'],
                trim(($item['team_code'] ?? '').' '.($item['team'] ?? '')),
                $item['from'],
                $item['to'],
                implode("\n", $item['clashes']),
            ], null, 'A'.$row);
            $row++;
        }

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = sprintf('Schedule_%s_%s.xlsx', preg_replace('/[^A-Za-z0-9]+/', '-', $label), $weekStart->format('Ymd'));
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @return array{0: string, 1: ?int, 2: Carbon} */
    private function params(Request $request): array
    {
        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'vehicle';
        $id = $request->integer('id') ?: null;

        try {
            $date = Carbon::parse($request->query('week', now()->toDateString()));
        } catch (\Exception) {
            $date = now();
        }

        return [$type, $id, $date->startOfWeek(Carbon::MONDAY)->startOfDay()];
    }

    private function resource(string $type, int $id): Vehicle|Driver|User|null
    {
        return match ($type) {
            'vehicle' => Vehicle::find($id),
            'driver' => Driver::find($id),
            'supervisor' => User::find($id),
        };
    }

    private function label(string $type, $r): string
    {
        return $type === 'vehicle' ? ($r->code ?? $r->plate_number ?? $r->vehicle_type ?? "#{$r->id}") : $r->name;
    }

    private function detail(string $type, $r): ?string
    {
        return match ($type) {
            'vehicle' => collect([$r->plate_number, $r->vehicle_type, $r->capacity ? $r->capacity.' seats' : null])->filter()->unique()->implode(' · ') ?: null,
            'driver' => $r->phone,
            default => null,
        };
    }

    /**
     * Movements in the active event where the resource is the lead, an extra unit or the supervisor.
     */
    private function movementsFor(Request $request, string $type, int $id)
    {
        $user = $request->user();
        $column = ['vehicle' => 'vehicle_id', 'driver' => 'driver_id', 'supervisor' => 'field_supervisor_id'][$type];

        return Movement::query()
            ->where('event_id', $request->session()->get('active_event_id'))
            ->where('status', '!=', 'cancelled')
            ->when(! $user->can('movements.view-all-functional-areas'),
                fn ($q) => $q->whereIn('functional_area', $user->functionalAreaCodes()))
            ->where(fn ($q) => $q->where($column, $id)
                ->when($type !== 'supervisor', fn ($q) => $q->orWhereHas('units', fn ($u) => $u->where($column, $id)))
                ->when($type === 'supervisor', fn ($q) => $q->orWhereHas('extraSupervisors', fn ($u) => $u->whereKey($id))));
    }

    /** @return list<string> every Y-m-d the resource has a movement starting on */
    private function bookedDates(Request $request, string $type, int $id): array
    {
        if (! $request->session()->get('active_event_id')) {
            return [];
        }

        return $this->movementsFor($request, $type, $id)
            ->whereNotNull('window_start')
            ->pluck('window_start')
            ->map(fn ($start) => $start->toDateString())
            ->unique()->sort()->values()->all();
    }

    /**
     * Movements in the week where the resource is the lead, an extra unit or the supervisor.
     */
    private function items(Request $request, ConflictDetectionService $conflicts, string $type, $resource, Carbon $weekStart): Collection
    {
        $eventId = $request->session()->get('active_event_id');
        if (! $eventId) {
            return collect();
        }

        $id = $resource->id;
        $column = ['vehicle' => 'vehicle_id', 'driver' => 'driver_id', 'supervisor' => 'field_supervisor_id'][$type];
        $weekEnd = $weekStart->copy()->addWeek();

        $movements = $this->movementsFor($request, $type, $id)
            ->with(['team:id,code,team_name', 'job:id,movement_id,job_id,status', 'units:id,movement_id,vehicle_id,driver_id'])
            // A day earlier so last night's run that ends this morning still shows.
            ->where('window_start', '>=', $weekStart->copy()->subDay())
            ->where('window_start', '<', $weekEnd)
            ->orderBy('window_start')
            ->get();

        $schedule = $conflicts->crewSchedule((int) $eventId);
        $name = $this->label($type, $resource);

        return $movements
            ->map(function (Movement $m) use ($schedule, $type, $column, $id, $name) {
                $start = $schedule[$m->id]['start'] ?? $m->window_start;
                $end = $schedule[$m->id]['end'] ?? $m->window_end ?? $m->window_start?->copy()->addMinutes(30);
                $lead = (int) $m->$column === (int) $id;

                return [
                    'id' => $m->id,
                    'code' => $m->code,
                    'plan_id' => $m->plan_id,
                    'kind' => $m->kind,
                    'team' => $m->team?->team_name,
                    'team_code' => $m->team?->code,
                    'from' => $m->from_location,
                    'to' => $m->to_location,
                    'start' => $start?->format('Y-m-d H:i'),
                    'end' => $end?->format('Y-m-d H:i'),
                    'window' => $m->window_start ? $m->window_start->format('H:i').'–'.($m->window_end?->format('H:i') ?? '--:--') : null,
                    'role' => ($lead ? 'Lead ' : 'Extra ').$type,
                    'job_code' => $m->job?->job_id,
                    'job_db_id' => $m->job?->id,
                    'job_status' => $m->job?->status,
                    'job_status_label' => $m->job ? \App\Models\JobOperation::statusLabel($m->job->status) : null,
                    // Only clashes about this resource, not the movement's other crew.
                    'clashes' => collect($schedule[$m->id]['clashes'][$type] ?? [])
                        ->filter(fn ($text) => str_contains($text, $name))
                        ->values()->all(),
                ];
            })
            ->filter(fn ($item) => $item['end'] && $item['end'] > $weekStart->format('Y-m-d H:i'))
            ->values();
    }

    private function supervisors()
    {
        return User::permission('jobs.view');
    }
}
