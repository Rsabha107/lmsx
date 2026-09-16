<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsSpreadsheetRows;
use App\Services\TeamImportService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamImportController extends Controller
{
    use ReadsSpreadsheetRows;

    /**
     * Field aliases, keyed by normalized header text (letters/digits only, lowercased).
     * A value can be a single field key (unambiguous - always wins outright) or a list
     * of candidate field keys tried in order (used for headers that appear more than once
     * in real-world sheets, e.g. "Flight Number" / "Total Passengers" showing up for both
     * the inbound and outbound legs - see resolveColumnMap() for how these are split).
     */
    protected const HEADER_ALIASES = [
        // Unambiguous - my own template headers, and single-occurrence real-sheet headers.
        'trigram' => 'trigram',
        'teamname' => 'team_name',
        'country' => 'team_name',
        'countrycode' => 'country_code',
        'group' => 'group',
        'grouppool' => 'group',
        'hotelname' => 'hotel_name',
        'allocatedhotel' => 'hotel_name',
        'roomcount' => 'room_count',
        'additionalrooms' => 'room_count',
        'airportcode' => 'airport_code',
        'arrivaldate' => 'arrival_date',
        'arrivaldatetodoha' => 'arrival_date',
        'arrivaltime' => 'arrival_time',
        'arrivaltimeindoha' => 'arrival_time',
        'arrivalflightnumber' => 'arrival_flight_number',
        'arrivalpassengers' => 'arrival_passengers',
        'departureflightnumber' => 'departure_flight_number',
        'departuredate' => 'departure_date',
        'departuredatefromdoha' => 'departure_date',
        'departuretime' => 'departure_time',
        'departuretimefromdoha' => 'departure_time',
        'departurepassengers' => 'departure_passengers',
        'notes' => 'notes',
        'pmadetails' => 'notes',
        'accommodationnotes' => 'notes',
        'travelnotes' => 'notes',
        'itinerary' => 'itinerary',

        // Ambiguous - the same header text is reused for both legs in the real PMA
        // sheet (e.g. two "Flight Number" columns, one per leg). Resolved by
        // left-to-right occurrence order in resolveColumnMap(): 1st -> arrival,
        // 2nd -> departure.
        'flightnumber' => ['arrival_flight_number', 'departure_flight_number'],
        'totalpassengers' => ['arrival_passengers', 'departure_passengers'],
    ];

    protected const DATE_FIELDS = ['arrival_date', 'departure_date'];
    protected const TIME_FIELDS = ['arrival_time', 'departure_time'];

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
    public function import(Request $request, int $eventId, TeamImportService $service)
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
        ['columnMap' => $columnMap, 'headerRowIndex' => $headerRowIndex] = $this->findHeaderRow($sheet, 'trigram', 'Trigram (or Code)');

        // "Notes" can be sourced from several different columns at once (PMA
        // Details, Accommodation Notes, Travel Notes, ...) and concatenated,
        // unlike every other field which just takes the first non-blank value.
        $noteColumns = $columnMap['notes'] ?? [];
        unset($columnMap['notes']);

        $highestRow = $sheet->getHighestDataRow();
        $rows = [];

        for ($r = $headerRowIndex + 1; $r <= $highestRow; $r++) {
            $trigramCol = $columnMap['trigram'][0];
            $trigram = $this->cellValue($sheet->getCell([$trigramCol, $r]));
            if ($trigram === null || $trigram === '') {
                continue; // skip blank/spacer rows
            }

            $rowValues = [];
            foreach ($columnMap as $field => $candidateCols) {
                $rowValues[$field] = $this->firstNonBlank($sheet, $candidateCols, $r, $this->fieldKind($field));
            }

            // A dedicated "Airport Code" column is often left blank in real sheets,
            // but the "Itinerary" column (e.g. "TAS-DOH-TAS": home -> venue -> home)
            // usually isn't - offer both legs of that route as soft hints (not the
            // strict, validated "Airport Code" field), so an airport missing from
            // our Airports list just leaves this row's airport blank instead of
            // failing the row.
            if (!empty($rowValues['itinerary']) && preg_match('/^([A-Za-z]{3})-([A-Za-z]{3})/', (string) $rowValues['itinerary'], $matches)) {
                $rowValues['airport_code_hint'] = strtoupper($matches[1]);
                $rowValues['venue_airport_code_hint'] = strtoupper($matches[2]);
            }
            unset($rowValues['itinerary']);

            if ($noteColumns !== []) {
                $noteParts = [];
                foreach ($noteColumns as $col) {
                    $text = $this->cellValue($sheet->getCell([$col, $r]));
                    if ($text !== null && $text !== '') {
                        $noteParts[] = $text;
                    }
                }
                if ($noteParts !== []) {
                    $rowValues['notes'] = implode(' / ', array_unique($noteParts));
                }
            }

            $rows[] = $rowValues;
        }

        return $rows;
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
