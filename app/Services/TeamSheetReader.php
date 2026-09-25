<?php

namespace App\Services;

use App\Models\Country;

/**
 * Reads an event-teams sheet into rows keyed as TeamImportService expects.
 */
class TeamSheetReader extends AbstractSheetReader
{
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

    /** @var array<string, array{0: string, 1: string}>|null country lookups, loaded once per read */
    private ?array $countriesByCode = null;
    private ?array $countriesByName = null;

    protected function requiredField(): string
    {
        return 'trigram';
    }

    protected function requiredFieldLabel(): string
    {
        return 'Trigram (or Code)';
    }

    protected function mappableFields(): array
    {
        return [
            'trigram',
            'team_name',
            'country_code',
            'group',
            'hotel_name',
            'room_count',
            'airport_code',
            'arrival_flight_number',
            'arrival_date',
            'arrival_time',
            'arrival_passengers',
            'departure_flight_number',
            'departure_date',
            'departure_time',
            'departure_passengers',
            'itinerary',
            'notes',
        ];
    }

    protected function aiSubject(): string
    {
        return 'sports-event team logistics (travel and accommodation)';
    }

    protected function aiGuidance(): string
    {
        return <<<'GUIDANCE'
            - Many sheets repeat a header for both legs (two "Flight Number" or
              "Total Pax" columns). Decide which leg each belongs to from the
              grouping row above it, or from left-to-right order: inbound/arrival
              first, outbound/departure second.
            - "trigram" is the team's short code (3 letters, e.g. QAT, BRA). Do not
              confuse it with a country code column or the full team name.
            - "itinerary" is a routing string such as "TAS-DOH-TAS".
            - "arrival_date" is the day the team travels in, from a column such as
              "Arrival Date", "Arrival Date to Doha" or "Inbound Date". Likewise
              "departure_date" comes from "Departure Date" / "Departure Date from
              Doha". Never take either from a fixture column - "First Match",
              "Last Match", "Match Date" - even when it sits right next to it; a
              match date is not a travel date. Put match dates in "notes" instead.
            - A team arrives before its first match, so an arrival date that equals
              or follows the first match date means you read the wrong column.
            GUIDANCE;
    }

    /**
     * "Notes" can be sourced from several columns at once (PMA Details,
     * Accommodation Notes, Travel Notes, ...) and concatenated.
     */
    protected function concatenatedField(): ?string
    {
        return 'notes';
    }

    protected function transformRow(array $row): array
    {
        // A dedicated "Airport Code" column is often left blank in real sheets,
        // but the "Itinerary" column (e.g. "TAS-DOH-TAS": home -> venue -> home)
        // usually isn't - offer both legs of that route as soft hints (not the
        // strict, validated "Airport Code" field), so an airport missing from
        // our Airports list just leaves this row's airport blank instead of
        // failing the row.
        if (!empty($row['itinerary']) && preg_match('/^([A-Za-z]{3})-([A-Za-z]{3})/', (string) $row['itinerary'], $matches)) {
            $row['airport_code_hint'] = strtoupper($matches[1]);
            $row['venue_airport_code_hint'] = strtoupper($matches[2]);
        }
        unset($row['itinerary']);

        return $this->applyCountry($row);
    }

    /**
     * Source sheets label national teams with a discipline suffix ("BHR-V",
     * "BAHRAIN-V") and rarely carry a country code at all. Where the row can be
     * tied to a country, that country's own code and name replace whatever the
     * sheet said, so teams are named consistently across events. Rows that match
     * no country (club sides, invitational squads) are left exactly as found.
     *
     * The Trigram is deliberately untouched - it is the event-unique key rows are
     * matched on, and two squads from one country can share a country but not a
     * trigram.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function applyCountry(array $row): array
    {
        $country = $this->matchCountry($row);

        if ($country !== null) {
            [$row['country_code'], $row['team_name']] = $country;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{0: string, 1: string}|null [country_code, country_name]
     */
    private function matchCountry(array $row): ?array
    {
        $this->loadCountries();

        $byCode = fn (string $value) => $this->countriesByCode[strtoupper(trim($value))] ?? null;
        $byName = fn (string $value) => $this->countriesByName[strtolower(trim($value))] ?? null;

        foreach ([
            fn () => $byCode((string) ($row['country_code'] ?? '')),
            fn () => $byCode((string) ($row['trigram'] ?? '')),
            // Full value first: a country name can legitimately contain a hyphen
            // (Guinea-Bissau), so only fall back to the stem if the whole thing missed.
            fn () => $byName((string) ($row['team_name'] ?? '')),
            fn () => $byCode($this->stem((string) ($row['trigram'] ?? ''))),
            fn () => $byName($this->stem((string) ($row['team_name'] ?? ''))),
        ] as $attempt) {
            if ($match = $attempt()) {
                return $match;
            }
        }

        return null;
    }

    /** Drops a trailing discipline/squad suffix: "BHR-V" -> "BHR". */
    private function stem(string $value): string
    {
        return trim(explode('-', trim($value), 2)[0]);
    }

    private function loadCountries(): void
    {
        if ($this->countriesByCode !== null) {
            return;
        }

        $this->countriesByCode = [];
        $this->countriesByName = [];

        foreach (Country::query()->get(['country_code', 'country_name']) as $country) {
            $pair = [$country->country_code, $country->country_name];
            $this->countriesByCode[strtoupper($country->country_code)] = $pair;
            $this->countriesByName[strtolower($country->country_name)] = $pair;
        }
    }
}
