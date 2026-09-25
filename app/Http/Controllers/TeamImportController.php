<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsSpreadsheetRows;
use App\Services\TeamImportService;
use App\Services\TeamSheetReader;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamImportController extends Controller
{
    // Only for buildTemplateResponse() - reading uploaded sheets lives in TeamSheetReader.
    use ReadsSpreadsheetRows;

    /**
     * Download a blank import template (with one example row and an instructions sheet).
     */
    public function template(): StreamedResponse
    {
        $example = ['EGY', 'Egypt', 'EGY', 'Group A', 'Holiday Villa', 11, 'CAI', 'MS 905', '2026-11-15', '18:05', 34, 'MS 906', '2026-12-03', '02:40', 29, ''];

        return $this->buildTemplateResponse(
            'Teams',
            TeamImportService::HEADERS,
            $example,
            $this->instructionRows(),
            'event-teams-import-template.xlsx',
        );
    }

    /**
     * Import teams (with flights and accommodation) from an uploaded spreadsheet into one event.
     */
    public function import(Request $request, int $eventId, TeamImportService $service, TeamSheetReader $reader)
    {
        $request->validate([
            // Checked by extension, not sniffed MIME: an .xlsx is a ZIP container and
            // some libmagic builds report it as application/zip, which makes a
            // mimes:xlsx rule reject perfectly valid files on some hosts. Content is
            // still proven by the reader, which fails loudly on anything unparseable.
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
            ['Trigram', 'Required. The team\'s 3-letter code, unique within this event. Rows are matched to existing teams by this code.'],
            ['Team Name', 'Required when creating a new team. Ignored (kept as-is) when updating an existing one, unless changed.'],
            ['Country Code', 'Optional. 3-letter code matching a country already in the system (e.g. EGY, FRA).'],
            ['Group', 'Optional, e.g. "Group A".'],
            ['Hotel Name', 'Optional. Creates or updates the team\'s accommodation record.'],
            ['Room Count', 'Optional whole number.'],
            ['Airport Code', 'Optional. 3-letter code for the airport the team lands at (the event venue airport, e.g. HIA). Used as the arrival destination and the departure origin.'],
            ['Arrival Flight Number', 'Optional. Leave blank if not booked yet.'],
            ['Arrival Date', 'Optional. Format YYYY-MM-DD.'],
            ['Arrival Time', 'Optional. Format HH:MM, 24-hour.'],
            ['Arrival Passengers', 'Optional whole number.'],
            ['Departure Flight Number', 'Optional. Leave blank if not booked yet.'],
            ['Departure Date', 'Optional. Format YYYY-MM-DD.'],
            ['Departure Time', 'Optional. Format HH:MM, 24-hour.'],
            ['Departure Passengers', 'Optional whole number.'],
            ['Notes', 'Optional free text.'],
            ['', ''],
            ['Re-importing this file', 'Safe to do. Rows are matched by Trigram - an existing team is updated, never duplicated. A flight is only touched if its flight number in the file differs from what is already saved, so re-uploading doesn\'t erase manual corrections. Blank cells never erase existing data.'],
        ];
    }
}
