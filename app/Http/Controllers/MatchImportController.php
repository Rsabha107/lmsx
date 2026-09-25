<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsSpreadsheetRows;
use App\Services\MatchImportService;
use App\Services\MatchSheetReader;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MatchImportController extends Controller
{
    // Only for buildTemplateResponse() - reading uploaded sheets lives in MatchSheetReader.
    use ReadsSpreadsheetRows;

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
    public function import(Request $request, int $eventId, MatchImportService $service, MatchSheetReader $reader)
    {
        $request->validate([
            // See TeamImportController: extension beats sniffed MIME for xlsx.
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        try {
            $rows = $reader->read($request->file('file'));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($service->import($rows, $eventId));
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
