<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsSpreadsheetRows;
use App\Services\MatchImportService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MatchImportController extends Controller
{
    use ReadsSpreadsheetRows;

    /**
     * Field aliases, keyed by normalized header text (letters/digits only, lowercased).
     */
    protected const HEADER_ALIASES = [
        // My own template headers.
        'matchnumber' => 'match_number',
        'matchdate' => 'match_date',
        'kickoff' => 'kick_off',
        'team1code' => 'team1_code',
        'team2code' => 'team2_code',
        'venue' => 'venue',
        'stage' => 'stage',

        // Real-world fixtures sheet headers (e.g. FIFA-style exports).
        'matchno' => 'match_number',
        'ko' => 'kick_off',
        // PMA1/PMA2 hold the actual team code (e.g. "QAT-17") - the reliable
        // key, unlike the display-only "TEAM1"/"TEAM2" name columns, which
        // aren't mapped at all.
        'pma1' => 'team1_code',
        'pma2' => 'team2_code',
        'matchround' => 'stage',
    ];

    protected const DATE_FIELDS = ['match_date'];
    protected const TIME_FIELDS = ['kick_off'];

    /**
     * Download a blank import template (with one example row and an instructions sheet).
     */
    public function template(): StreamedResponse
    {
        $example = ['FU17-001', '2026-11-19', '18:30', 'QAT', 'PAN', 'Aspire Zone (Pitch 2)', 'Group Stage'];

        return $this->buildTemplateResponse(
            'Matches',
            MatchImportService::HEADERS,
            $example,
            $this->instructionRows(),
            'matches-import-template.xlsx',
        );
    }

    /**
     * Import matches from an uploaded spreadsheet into one event.
     */
    public function import(Request $request, int $eventId, MatchImportService $service)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        $rows = $this->readRows($request->file('file'));

        return response()->json($service->import($rows, $eventId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        $sheet = $this->loadSheet($file);
        ['columnMap' => $columnMap, 'headerRowIndex' => $headerRowIndex] = $this->findHeaderRow($sheet, 'match_number', 'Match Number (or Match No.)');

        $highestRow = $sheet->getHighestDataRow();
        $rows = [];

        for ($r = $headerRowIndex + 1; $r <= $highestRow; $r++) {
            $matchNumberCol = $columnMap['match_number'][0];
            $matchNumber = $this->cellValue($sheet->getCell([$matchNumberCol, $r]));
            if ($matchNumber === null || $matchNumber === '') {
                continue; // skip blank/spacer rows
            }

            $rowValues = [];
            foreach ($columnMap as $field => $candidateCols) {
                $rowValues[$field] = $this->firstNonBlank($sheet, $candidateCols, $r, $this->fieldKind($field));
            }

            $rows[] = $rowValues;
        }

        return $rows;
    }

    private function instructionRows(): array
    {
        return [
            ['Column', 'Description'],
            ['Match Number', 'Required. Unique within this event (the same number can exist independently in a different event). Rows are matched to existing matches by this number.'],
            ['Match Date', 'Optional. Format YYYY-MM-DD. Leave blank for a not-yet-scheduled fixture (e.g. a future knockout round).'],
            ['Kick Off', 'Optional. Format HH:MM, 24-hour. Ignored if Match Date is blank.'],
            ['Team1 Code', 'Optional. Must match an existing team\'s code in the active event. Leave blank if not decided yet (e.g. a knockout round awaiting an earlier result).'],
            ['Team2 Code', 'Optional. Same rules as Team1 Code.'],
            ['Venue', 'Optional. Must match an existing venue\'s name exactly (case-insensitive).'],
            ['Stage', 'Optional free text, e.g. "Group Stage", "Round of 16", "Quarter Final".'],
            ['', ''],
            ['Re-importing this file', 'Safe to do. Rows are matched by Match Number - an existing match is updated, never duplicated. Blank cells never erase existing data, and a team/venue that can\'t be matched just leaves that field blank instead of failing the row.'],
        ];
    }
}
