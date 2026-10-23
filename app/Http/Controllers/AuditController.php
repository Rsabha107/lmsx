<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The Audit Trail page and its Excel export, sharing one set of server-side filters. */
class AuditController extends Controller
{
    private const PAGE_LIMIT = 500;

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $query = AuditLog::filtered($filters);
        $events = Event::pluck('name', 'id');

        $entries = (clone $query)->latest()->latest('id')->limit(self::PAGE_LIMIT)->get()
            ->map(function (AuditLog $log) use ($events) {
                ['ip' => $ip, 'details' => $details] = $log->readableMeta();

                return [
                    't' => $log->created_at->format('d M Y H:i'),
                    'who' => $log->user_name ?? 'System',
                    'role' => $log->user_role ?? '',
                    'action' => $log->action,
                    'target' => $log->target ?? '',
                    'meta' => $details,
                    'ip' => $ip,
                    'event' => $log->event_id ? ($events[$log->event_id] ?? "Event #{$log->event_id}") : null,
                ];
            })->all();

        return Inertia::render('Audit', [
            'audit' => $entries,
            'total' => $query->count(),
            'filters' => $filters,
            'roles' => AuditLog::query()->whereNotNull('user_role')->distinct()->orderBy('user_role')->pluck('user_role'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $events = Event::pluck('name', 'id');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Audit Trail');
        $headers = ['Date & time', 'Who', 'Role', 'Action', 'Target', 'Event', 'IP', 'Details'];
        $sheet->fromArray($headers, null, 'A1');
        $head = $sheet->getStyle('A1:H1');
        $head->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3864');
        $head->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $row = 2;
        // Newest first, read in chunks so a long log doesn't sit in memory twice.
        foreach (AuditLog::filtered($filters)->orderByDesc('created_at')->orderByDesc('id')->lazy(1000) as $log) {
            ['ip' => $ip, 'details' => $details] = $log->readableMeta();
            $values = [
                $log->created_at->format('Y-m-d H:i:s'),
                $log->user_name ?? 'System',
                $log->user_role,
                $log->action,
                $log->target,
                $log->event_id ? ($events[$log->event_id] ?? "Event #{$log->event_id}") : null,
                $ip,
                $details,
            ];
            // As text, so a target like "=…" or "+974…" is never read as a formula or a number.
            foreach ($values as $i => $value) {
                if ($value !== null && $value !== '') {
                    $sheet->setCellValueExplicit([$i + 1, $row], (string) $value, DataType::TYPE_STRING);
                }
            }
            $row++;
        }

        $last = max(1, $row - 1);
        $sheet->setAutoFilter("A1:H{$last}");
        $sheet->freezePane('A2');
        foreach (['A' => 20, 'B' => 24, 'C' => 16, 'D' => 30, 'E' => 36, 'F' => 30, 'G' => 16, 'H' => 90] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getStyle("H2:H{$last}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("A2:H{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        AuditLog::change('Audit trail exported', ($row - 2).' entries', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        $range = ($filters['from'] || $filters['to'])
            ? str_replace('-', '', ($filters['from'] ?? 'start').'_'.($filters['to'] ?? now()->toDateString()))
            : 'all';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(fn () => $writer->save('php://output'), "AUDIT_TRAIL_{$range}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @return array{search: ?string, role: ?string, from: ?string, to: ?string} */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:200',
            'role' => 'nullable|string|max:100',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);

        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;
        // A reversed range is read the way it was meant.
        if ($from && $to && $to < $from) {
            [$from, $to] = [$to, $from];
        }

        return [
            'search' => $validated['search'] ?? null,
            'role' => $validated['role'] ?? null,
            'from' => $from,
            'to' => $to,
        ];
    }
}
