<?php

namespace App\Services;

use App\Models\Airport;
use App\Models\Country;
use App\Models\Movement;
use App\Models\Team;
use App\Models\TeamFlight;
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

    /** Flight changes made by the row being processed; kept only if the row commits. */
    private array $rowFlightChanges = [];

    public function __construct(private JobGenerationService $jobs)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{total: int, created: int, updated: int, unchanged: int, incomplete: array, failed: array, flight_changes: array, not_in_file: array<int, string>}
     */
    public function import(array $rows, int $eventId): array
    {
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $incomplete = [];
        $failed = [];
        $flightChanges = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // header occupies row 1
            $this->rowFlightChanges = [];

            try {
                $result = DB::transaction(fn () => $this->processRow($row, $eventId));
                array_push($flightChanges, ...$this->rowFlightChanges);

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
            'flight_changes' => $flightChanges,
            'not_in_file' => $this->teamsNotInFile($rows, $eventId),
        ];
    }

    /**
     * Active teams the file doesn't mention. Reported, never deleted: a file may
     * legitimately cover only some teams.
     *
     * @return array<int, string>
     */
    private function teamsNotInFile(array $rows, int $eventId): array
    {
        $codes = array_filter(array_map(
            fn (array $row) => strtoupper(trim((string) ($row['trigram'] ?? ''))),
            $rows,
        ));

        if ($codes === []) {
            return [];
        }

        return Team::where('event_id', $eventId)
            ->where('is_active', true)
            ->whereNotIn('code', $codes)
            ->orderBy('code')
            ->pluck('code')
            ->all();
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

        // "Venue" airport (where the team lands, e.g. Doha) and "home" airport (the
        // team's own city) - arrival flies home -> venue, departure flies venue ->
        // home. The "Airport Code" column holds the venue/arrival airport; an
        // itinerary like "TAS-DOH-TAS" supplies both legs as soft hints.
        $venueAirportId = $this->resolveAirport($row['airport_code'] ?? null, $row['venue_airport_code_hint'] ?? null);
        $airportId = $this->resolveAirport(null, $row['airport_code_hint'] ?? null);
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
            $team, $eventId, 'arrival', $row, $code,
            'arrival_flight_number', 'arrival_date', 'arrival_time', 'arrival_passengers',
            originAirportId: $airportId, destinationAirportId: $venueAirportId,
        );
        $departureChanged = $this->upsertFlight(
            $team, $eventId, 'departure', $row, $code,
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
     * Creates/updates the flight record for one leg, writing only the values the
     * file gives that differ from what is saved - so a re-upload picks up a
     * retimed or rebooked flight, and a blank cell never wipes known data. Live
     * tracking fields (estimated/actual times, delay, gate) are never written
     * here; they are only cleared when the file points the leg at a different
     * flight, because they then describe the old one.
     */
    private function upsertFlight(
        Team $team,
        int $eventId,
        string $direction,
        array $row,
        string $code,
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

        // A date with no time ("TBC") would otherwise land on midnight and
        // overwrite a time we already know.
        $timeGiven = trim((string) ($row[$timeKey] ?? '')) !== '';
        if ($scheduledAt !== null && !$timeGiven && $existing?->scheduled_at) {
            $scheduledAt = substr($scheduledAt, 0, 10) . ' ' . $existing->scheduled_at->format('H:i:s');
        }

        $attrs = array_filter([
            'flight_number' => $flightNumber !== '' ? $flightNumber : null,
            'scheduled_at' => $scheduledAt,
            'party_size_total' => $partySize,
            'origin_airport_id' => $originAirportId,
            'destination_airport_id' => $destinationAirportId,
        ], fn ($value) => $value !== null);

        if (!$existing) {
            $team->flights()->create([...$attrs, 'event_id' => $eventId, 'direction' => $direction]);

            return true;
        }

        $changes = array_filter(
            $attrs,
            fn ($value, $key) => $key === 'scheduled_at'
                ? $existing->scheduled_at?->format('Y-m-d H:i:s') !== $value
                : $existing->{$key} !== $value,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changes === []) {
            return false;
        }

        $before = [
            'flight_number' => $existing->flight_number,
            'scheduled_at' => $existing->scheduled_at?->copy(),
            'party_size_total' => $existing->party_size_total,
        ];

        $numberChanged = isset($changes['flight_number']) && $before['flight_number'] !== null;
        $dateChanged = isset($changes['scheduled_at'])
            && $before['scheduled_at']?->format('Y-m-d') !== substr($changes['scheduled_at'], 0, 10);

        if ($numberChanged || $dateChanged) {
            $changes += [
                'estimated_at' => null,
                'actual_at' => null,
                'delay_minutes' => null,
                'flight_status' => null,
                'flight_synced_at' => null,
            ];
        }

        $existing->update($changes);

        if (isset($changes['flight_number']) || isset($changes['scheduled_at']) || isset($changes['party_size_total'])) {
            $this->rowFlightChanges[] = $this->syncMovements($existing->fresh(), $before, $code);
        }

        return true;
    }

    /**
     * Carries a flight change through to the movements planned against it.
     * Movements not yet turned into a job are rescheduled; ones that already
     * have a job are left alone and reported, since the job's checkpoints were
     * snapshotted and a crew may already be working to them.
     *
     * @param array{flight_number: ?string, scheduled_at: ?Carbon, party_size_total: ?int} $before
     * @return array<string, mixed>
     */
    private function syncMovements(TeamFlight $flight, array $before, string $code): array
    {
        $timeMoved = $before['scheduled_at']?->format('Y-m-d H:i') !== $flight->scheduled_at?->format('Y-m-d H:i');
        $paxChanged = $flight->party_size_total !== null && $before['party_size_total'] !== $flight->party_size_total;
        $numberChanged = $before['flight_number'] !== null && $before['flight_number'] !== $flight->flight_number;

        $movements = Movement::where('event_id', $flight->event_id)
            ->where('team_id', $flight->team_id)
            ->where(function ($query) use ($flight) {
                $query->where('flight_id', $flight->id)
                    // Planned before this team had a flight on record.
                    ->orWhere(fn ($q) => $q->where('kind', $flight->direction)->whereNull('flight_id'));
                // Transfers are timed off the team's arrival flight without linking it.
                if ($flight->direction === 'arrival') {
                    $query->orWhere('kind', 'transfer');
                }
            })
            ->with(['flight', 'plan', 'checkpointTemplate.checkpoints'])
            ->orderBy('window_start')
            ->get();

        $results = [];

        foreach ($movements as $movement) {
            $entry = [
                'id' => $movement->id,
                'code' => $movement->code,
                'kind' => $movement->kind,
                'plan' => $movement->plan?->name,
                'from' => $movement->window_start?->format('D j M H:i'),
                'to' => null,
                'status' => 'unchanged',
                'notes' => [],
            ];

            if ($movement->hasJob()) {
                if ($timeMoved || $paxChanged || $numberChanged) {
                    $entry['status'] = 'needs_review';
                    $entry['notes'][] = "Job {$movement->job_id} was already issued - update it by hand.";
                    $results[] = $entry;
                }
                continue;
            }

            $attrs = [];
            if ($movement->kind === $flight->direction && $movement->flight_id === null) {
                $attrs['flight_id'] = $flight->id;
            }
            // Only follow the flight when the movement's count came from it, not a manual edit.
            if ($paxChanged && in_array($movement->passengers, [null, 0, $before['party_size_total']], true)) {
                $attrs['passengers'] = $flight->party_size_total;
            }
            if ($numberChanged && $movement->flight_number === $before['flight_number']) {
                $attrs['flight_number'] = $flight->flight_number;
            }
            if ($attrs !== []) {
                $movement->update($attrs);
                $movement->load('flight');
            }

            $rescheduled = $timeMoved && $this->jobs->recomputeMovementWindow($movement);

            if (!$rescheduled && $attrs === []) {
                continue;
            }

            $movement->refresh();
            $entry['status'] = 'updated';
            $entry['to'] = $movement->window_start?->format('D j M H:i');

            if (isset($attrs['passengers'])) {
                $entry['notes'][] = "Passengers {$before['party_size_total']} → {$flight->party_size_total}.";
            }
            if ($rescheduled && ($movement->vehicle_id || $movement->driver_id)) {
                $entry['notes'][] = 'Vehicle/driver kept - check they are still free at the new time.';
            }
            if ($rescheduled && $movement->plan?->date && $movement->window_start
                && !$movement->plan->date->isSameDay($movement->window_start)) {
                $entry['notes'][] = "Plan is dated {$movement->plan->date->format('D j M')}; this movement is now on {$movement->window_start->format('D j M')}.";
            }

            $results[] = $entry;
        }

        return [
            'code' => $code,
            'direction' => $flight->direction,
            'flight_before' => $before['flight_number'],
            'flight_after' => $flight->flight_number,
            'time_before' => $before['scheduled_at']?->format('D j M H:i'),
            'time_after' => $flight->scheduled_at?->format('D j M H:i'),
            'pax_before' => $before['party_size_total'],
            'pax_after' => $flight->party_size_total,
            'movements' => $results,
        ];
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
