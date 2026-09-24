<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsSpreadsheetRows;
use App\Services\AbstractSheetReader;
use App\Services\MatchImportService;
use App\Services\MatchSheetReader;
use App\Services\TeamImportService;
use App\Services\TeamSheetReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * One-off data tools that sit beside the main workflow rather than inside it.
 *
 * The sheet converters turn an organiser's own spreadsheet into one of our
 * import templates. Nothing here writes to the database - the output is a file
 * the user reviews and then uploads through the normal import screens.
 */
class UtilitiesController extends Controller
{
    use ReadsSpreadsheetRows;

    /**
     * The converters on offer, keyed by the URL segment that selects one.
     *
     * @var array<string, array{title: string, reader: class-string<AbstractSheetReader>, headers: array<int, string>, blurb: string, importHint: string}>
     */
    private const CONVERTERS = [
        'teams' => [
            'title' => 'Team Sheet Converter',
            'reader' => TeamSheetReader::class,
            'headers' => TeamImportService::HEADERS,
            'blurb' => "Convert an organiser's team spreadsheet into the Event Teams import template.",
            'importHint' => 'Download this file, then upload it on the Event Teams page to import. Rows are matched by Trigram, so importing the same file twice updates rather than duplicates.',
        ],
        'matches' => [
            'title' => 'Match Sheet Converter',
            'reader' => MatchSheetReader::class,
            'headers' => MatchImportService::HEADERS,
            'blurb' => "Convert an organiser's fixtures spreadsheet into the Matches import template.",
            'importHint' => 'Download this file, then upload it on the Matches page to import. Rows are matched by Match Number, so importing the same file twice updates rather than duplicates.',
        ],
    ];

    public function index(): Response
    {
        return Inertia::render('Utilities/Index', [
            'converters' => collect(self::CONVERTERS)
                ->map(fn (array $c, string $type) => [
                    'type' => $type,
                    'title' => $c['title'],
                    'blurb' => $c['blurb'],
                ])
                ->values()
                ->all(),
        ]);
    }

    public function converter(Request $request, string $type): Response
    {
        $converter = $this->definition($type);

        return Inertia::render('Utilities/SheetConverter', [
            'type' => $type,
            'title' => $converter['title'],
            'blurb' => $converter['blurb'],
            'importHint' => $converter['importHint'],
            'headers' => $converter['headers'],
            'canUseAi' => (bool) $request->user()?->can('ai.use'),
        ]);
    }

    /**
     * Converts an uploaded sheet and returns the result for review. Never saves
     * anything - the file is only written once the user confirms, via download().
     */
    public function preview(Request $request, string $type): JsonResponse
    {
        $converter = $this->definition($type);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'use_ai' => 'sometimes|boolean',
        ]);

        $allowAi = $request->boolean('use_ai') && $request->user()->can('ai.use');

        /** @var AbstractSheetReader $reader */
        $reader = app($converter['reader']);

        try {
            $rows = $reader->read($request->file('file'), allowAi: $allowAi);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        if ($rows === []) {
            return response()->json(['ok' => false, 'message' => 'No data rows were found in that file.'], 422);
        }

        $report = $reader->report();
        $headers = $converter['headers'];

        return response()->json([
            'ok' => true,
            'headers' => $headers,
            'rows' => array_map(fn (array $row) => $this->toTemplateRow($row, $headers), $rows),
            'mapping' => $report['columns'],
            'headerRow' => $report['header_row'],
            'usedAi' => $report['used_ai'],
            'aiNotes' => $report['ai_notes'],
            'unmappedFields' => array_values(array_diff(
                array_map($this->fieldKey(...), $headers),
                array_column($report['columns'], 'field'),
            )),
            'suggestedFilename' => $this->suggestFilename($request->file('file')->getClientOriginalName()),
        ]);
    }

    /**
     * Writes the reviewed rows out as an import-template file. The rows come back
     * from the browser rather than being re-read server-side, so what the user
     * approved on screen is exactly what they download.
     */
    public function download(Request $request, string $type): StreamedResponse
    {
        $converter = $this->definition($type);
        $headers = $converter['headers'];

        $validated = $request->validate([
            'rows' => 'required|array|min:1|max:1000',
            'rows.*' => 'array|size:' . count($headers),
            'rows.*.*' => 'nullable|string|max:500',
            'filename' => 'sometimes|string|max:120',
        ]);

        $writer = new Xlsx($this->buildTemplateSpreadsheet(
            $converter['title'],
            $headers,
            $validated['rows'],
        ));

        $filename = $this->sanitizeFilename($validated['filename'] ?? 'converted.xlsx');

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{title: string, reader: class-string<AbstractSheetReader>, headers: array<int, string>, blurb: string, importHint: string}
     */
    private function definition(string $type): array
    {
        abort_unless(isset(self::CONVERTERS[$type]), 404);

        return self::CONVERTERS[$type];
    }

    /**
     * Flattens one reader row into the template's column order. A team row may
     * carry an airport as a soft hint parsed from an itinerary rather than a
     * validated "Airport Code" - the venue leg is what that column means, so
     * that is what gets written.
     *
     * @param array<string, mixed> $row
     * @param array<int, string> $headers
     * @return array<int, string>
     */
    private function toTemplateRow(array $row, array $headers): array
    {
        $row['airport_code'] ??= $row['venue_airport_code_hint'] ?? null;

        return array_map(
            fn (string $header) => (string) ($row[$this->fieldKey($header)] ?? ''),
            $headers,
        );
    }

    private function fieldKey(string $header): string
    {
        return str_replace(' ', '_', strtolower($header));
    }

    private function suggestFilename(string $originalName): string
    {
        return $this->sanitizeFilename(
            pathinfo($originalName, PATHINFO_FILENAME) . ' - import.xlsx'
        );
    }

    /** Keeps a user-supplied name from steering the Content-Disposition header. */
    private function sanitizeFilename(string $name): string
    {
        $base = preg_replace('/[^A-Za-z0-9 _.\-]/', '', pathinfo($name, PATHINFO_FILENAME)) ?: 'converted';

        return trim(substr($base, 0, 100)) . '.xlsx';
    }
}
