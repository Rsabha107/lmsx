<?php

namespace App\Services;

use App\Models\Airport;
use App\Models\Country;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates/updates event teams (and their arrival/departure flight and
 * accommodation records) from rows parsed out of an uploaded spreadsheet.
 */
class TeamImportService
{
    /**
     * Column headers, in order, for the import template and the parsed rows.
     * Row arrays passed to import() must be keyed by the snake_case version
     * of these (spaces -> underscores, lowercased).
     */
    public const HEADERS = [
        'Trigram',
        'Team Name',
        'Country Code',
        'Group',
        'Hotel Name',
        'Room Count',
        'Airport Code',
        'Arrival Flight Number',
        'Arrival Date',
        'Arrival Time',
        'Arrival Passengers',
        'Departure Flight Number',
        'Departure Date',
        'Departure Time',
        'Departure Passengers',
        'Notes',
    ];

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{total: int, created: int, updated: int, unchanged: int, incomplete: array, failed: array}
     */
    public function import(array $rows, int $eventId): array
    {
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $incomplete = [];
        $failed = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // header occupies row 1

            try {
                $result = DB::transaction(fn () => $this->processRow($row, $eventId));

                match ($result['status']) {
                    'created' => $created++,
                    'updated' => $updated++,
                    default => $unchanged++,
                };

                if (!empty($result['missing'])) {
                    $incomplete[] = [
                        'row' => $rowNumber,
                        'code' => $result['code'],
                        'missing' => $result['missing'],
                    ];
                }
            } catch (\Throwable $e) {
                $failed[] = [
                    'row' => $rowNumber,
                    'error' => $e instanceof RuntimeException ? $e->getMessage() : 'Unexpected error: ' . $e->getMessage(),
                    'original' => $row,
                ];
            }
        }

        return [
            'total' => count($rows),
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'incomplete' => $incomplete,
            'failed' => $failed,
        ];
    }

    /**
     * @return array{status: string, code: string, missing: array<int, string>}
     */
    private function processRow(array $row, int $eventId): array
    {
        $code = strtoupper(trim((string) ($row['trigram'] ?? '')));
        if ($code === '') {
            throw new RuntimeException('Trigram is required.');
        }
        if (strlen($code) > 10) {
            throw new RuntimeException('Trigram must be 10 characters or fewer.');
        }

        $team = Team::where('event_id', $eventId)->where('code', $code)->first();
        $isNew = !$team;

        $teamName = trim((string) ($row['team_name'] ?? ''));
        $countryId = $this->resolveCountry($row['country_code'] ?? null, $teamName !== '' ? $teamName : null, $code);

        // Team Name is usually the country's display name in real-world sheets - if
        // that column is blank or broken (e.g. a formula error), fall back to the
        // name of whatever country we did manage to resolve.
        if ($teamName === '' && $countryId !== null) {
            $teamName = Country::where('country_code', $countryId)->value('country_name') ?? '';
        }

        if ($isNew && $teamName === '') {
            throw new RuntimeException('Team Name is required to create a new team.');
        }
        if ($isNew && $countryId === null) {
            throw new RuntimeException("Could not determine a country for '{$teamName}' ({$code}). Add a 'Country Code' column, or add this country to the system first.");
        }

        // "Home" airport (the team's own city) and "venue" airport (the event's host
        // city, e.g. Doha) - an itinerary like "TAS-DOH-TAS" gives us both: arrival
        // flies home -> venue, departure flies venue -> home.
        $airportId = $this->resolveAirport($row['airport_code'] ?? null, $row['airport_code_hint'] ?? null);
        $venueAirportId = $this->resolveAirport(null, $row['venue_airport_code_hint'] ?? null);
        $groupPool = trim((string) ($row['group'] ?? ''));
        $hotelName = trim((string) ($row['hotel_name'] ?? ''));
        $roomCount = $this->parseRoomCount($row['room_count'] ?? null);
        $notes = trim((string) ($row['notes'] ?? ''));

        if ($isNew) {
            $team = Team::create([
                'event_id' => $eventId,
                'code' => $code,
                'team_name' => $teamName,
                'country_id' => $countryId,
                'group_pool' => $groupPool !== '' ? $groupPool : null,
                'origin_airport_id' => $airportId,
                'destination_airport_id' => $venueAirportId,
                'notes' => $notes !== '' ? $notes : null,
                'is_active' => true,
            ]);
            $teamChanged = true;
        } else {
            $attrs = [];
            if ($teamName !== '' && $team->team_name !== $teamName) {
                $attrs['team_name'] = $teamName;
            }
            if ($countryId !== null && $team->country_id !== $countryId) {
                $attrs['country_id'] = $countryId;
            }
            if ($groupPool !== '' && $team->group_pool !== $groupPool) {
                $attrs['group_pool'] = $groupPool;
            }
            if ($airportId !== null && $team->origin_airport_id !== $airportId) {
                $attrs['origin_airport_id'] = $airportId;
            }
            if ($venueAirportId !== null && $team->destination_airport_id !== $venueAirportId) {
                $attrs['destination_airport_id'] = $venueAirportId;
            }
            if ($notes !== '' && $team->notes !== $notes) {
                $attrs['notes'] = $notes;
            }

            $teamChanged = !empty($attrs);
            if ($teamChanged) {
                $team->update($attrs);
            }
        }

        $arrivalChanged = $this->upsertFlight(
            $team, $eventId, 'arrival', $row,
            'arrival_flight_number', 'arrival_date', 'arrival_time', 'arrival_passengers',
            originAirportId: $airportId, destinationAirportId: $venueAirportId,
        );
        $departureChanged = $this->upsertFlight(
            $team, $eventId, 'departure', $row,
            'departure_flight_number', 'departure_date', 'departure_time', 'departure_passengers',
            originAirportId: $venueAirportId, destinationAirportId: $airportId,
        );
        $stayChanged = $this->upsertStay($team, $eventId, $hotelName, $roomCount);

        $missing = [];
        if (!$team->flights()->where('event_id', $eventId)->where('direction', 'arrival')->exists()) {
            $missing[] = 'arrival flight';
        }
        if (!$team->flights()->where('event_id', $eventId)->where('direction', 'departure')->exists()) {
            $missing[] = 'departure flight';
        }
        if (!$team->stays()->where('event_id', $eventId)->exists()) {
            $missing[] = 'accommodation';
        }

        $status = $isNew
            ? 'created'
            : (($teamChanged || $arrivalChanged || $departureChanged || $stayChanged) ? 'updated' : 'unchanged');

        return ['status' => $status, 'code' => $code, 'missing' => $missing];
    }

    /**
     * Creates/updates the flight record for one leg. When a flight number is given,
     * the record is only touched if that number differs from what's already saved -
     * this protects any manual corrections (actual times, gate, delay) made after a
     * previous import, and lets the same file be re-uploaded safely once a flight
     * number is filled in. Some source sheets never track an outbound flight number
     * at all (only date/time/passengers) - in that case, falls back to comparing
     * those fields instead of skipping the leg entirely.
     */
    private function upsertFlight(
        Team $team,
        int $eventId,
        string $direction,
        array $row,
        string $numberKey,
        string $dateKey,
        string $timeKey,
        string $paxKey,
        ?int $originAirportId,
        ?int $destinationAirportId,
    ): bool {
        $flightNumber = trim((string) ($row[$numberKey] ?? ''));
        $scheduledAt = $this->combineDateTime($row[$dateKey] ?? null, $row[$timeKey] ?? null);
        $partySize = $this->toInt($row[$paxKey] ?? null);

        if ($flightNumber === '' && $scheduledAt === null && $partySize === null) {
            return false;
        }

        $existing = $team->flights()->where('event_id', $eventId)->where('direction', $direction)->first();

        // Newly-resolved airport info (e.g. from a re-import after adding an
        // airport, or after the itinerary-parsing fix) always counts as a change,
        // even if the flight number/schedule/passengers this file provides match
        // what's already saved - otherwise it would never get backfilled.
        $airportsChanged = $existing !== null && (
            ($originAirportId !== null && $existing->origin_airport_id !== $originAirportId)
            || ($destinationAirportId !== null && $existing->destination_airport_id !== $destinationAirportId)
        );

        if ($existing && !$airportsChanged) {
            if ($flightNumber !== '') {
                if ($existing->flight_number === $flightNumber) {
                    return false;
                }
            } else {
                $sameSchedule = $scheduledAt === null
                    || $existing->scheduled_at?->format('Y-m-d H:i:s') === $scheduledAt;
                $samePax = $partySize === null || $existing->party_size_total === $partySize;
                if ($sameSchedule && $samePax) {
                    return false;
                }
            }
        }

        $attrs = array_filter([
            'flight_number' => $flightNumber !== '' ? $flightNumber : null,
            'scheduled_at' => $scheduledAt,
            'party_size_total' => $partySize,
        ], fn ($value) => $value !== null);

        if ($originAirportId !== null) {
            $attrs['origin_airport_id'] = $originAirportId;
        }
        if ($destinationAirportId !== null) {
            $attrs['destination_airport_id'] = $destinationAirportId;
        }

        if ($existing) {
            $existing->update($attrs);
        } else {
            $team->flights()->create([...$attrs, 'event_id' => $eventId, 'direction' => $direction]);
        }

        return true;
    }

    private function upsertStay(Team $team, int $eventId, string $hotelName, ?int $roomCount): bool
    {
        if ($hotelName === '' && $roomCount === null) {
            return false;
        }

        $attrs = [];
        if ($hotelName !== '') {
            $attrs['hotel_name'] = $hotelName;
        }
        if ($roomCount !== null) {
            $attrs['room_count'] = $roomCount;
        }

        $existing = $team->stays()->where('event_id', $eventId)->first();
        if (!$existing) {
            $team->stays()->create([...$attrs, 'event_id' => $eventId]);
            return true;
        }

        $changed = false;
        foreach ($attrs as $key => $value) {
            if ($existing->{$key} !== $value) {
                $changed = true;
            }
        }
        if ($changed) {
            $existing->update($attrs);
        }

        return $changed;
    }

    /**
     * Resolves a country by code first (strict - fails the row if given but unknown,
     * since it was explicitly specified). Falls back to a best-effort match on the
     * team's display name (some source sheets only ever list a country name, e.g.
     * "Egypt"), then on the trigram itself (national-team trigrams are very often
     * also the country's ISO code, e.g. "GRE", "KOR") - both fallbacks are silently
     * skipped if nothing matches, since they're a convenience, not a validated field.
     */
    private function resolveCountry(mixed $codeValue, ?string $nameHint, ?string $trigram): ?string
    {
        $code = strtoupper(trim((string) $codeValue));
        if ($code !== '') {
            $country = Country::where('country_code', $code)->first();
            if (!$country) {
                throw new RuntimeException("Unknown country code '{$code}'.");
            }

            return $country->country_code;
        }

        if ($nameHint) {
            $country = Country::whereRaw('LOWER(country_name) = ?', [strtolower(trim($nameHint))])->first();
            if ($country) {
                return $country->country_code;
            }
        }

        if ($trigram) {
            $country = Country::where('country_code', strtoupper($trigram))->first();
            if ($country) {
                return $country->country_code;
            }
        }

        return null;
    }

    /**
     * Resolves an explicit airport code strictly (fails the row if given but
     * unknown). Falls back to a soft hint (e.g. derived from an "Itinerary"
     * column like "BRU-DOH-BRU") when no explicit code is given - silently
     * skipped if that airport isn't in the system, since it's a convenience,
     * not a validated field.
     */
    private function resolveAirport(mixed $value, mixed $hint = null): ?int
    {
        $code = strtoupper(trim((string) $value));
        if ($code !== '') {
            $airport = Airport::where('code', $code)->first();
            if (!$airport) {
                throw new RuntimeException("Unknown airport code '{$code}'.");
            }

            return $airport->id;
        }

        $hintCode = strtoupper(trim((string) $hint));
        if ($hintCode !== '') {
            $airport = Airport::where('code', $hintCode)->first();
            if ($airport) {
                return $airport->id;
            }
        }

        return null;
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (int) round((float) $value);
    }

    /**
     * Room counts come either as a clean number (our own template) or free text
     * from real-world sheets (e.g. "2nd floor 7 rooms", "33rd Floor 4 Rooms") -
     * best-effort extraction, left blank rather than guessed wrong when unclear.
     */
    private function parseRoomCount(mixed $value): ?int
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if (is_numeric($text)) {
            return (int) round((float) $text);
        }
        if (preg_match('/(\d+)\s*(?:rooms?|suites?)\b/i', $text, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Parses the date and time separately rather than as one combined string -
     * some real-world date formats (e.g. "15-Nov", no year) aren't reliably
     * parsed by Carbon once a time is appended to the same string.
     */
    private function combineDateTime(mixed $date, mixed $time): ?string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }

        try {
            $carbon = Carbon::parse($date);
        } catch (\Throwable $e) {
            throw new RuntimeException("Could not parse date '{$date}'.");
        }

        $time = trim((string) $time);
        if ($time !== '') {
            if (!preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches)) {
                throw new RuntimeException("Could not parse time '{$time}'.");
            }
            $carbon->setTime((int) $matches[1], (int) $matches[2]);
        }

        return $carbon->format('Y-m-d H:i:s');
    }
}
