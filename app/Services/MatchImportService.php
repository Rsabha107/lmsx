<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Team;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates/updates matches from rows parsed out of an uploaded spreadsheet.
 */
class MatchImportService
{
    /**
     * Column headers, in order, for the import template and the parsed rows.
     * Row arrays passed to import() must be keyed by the snake_case version
     * of these (spaces -> underscores, lowercased).
     */
    public const HEADERS = [
        'Match Number',
        'Match Date',
        'Kick Off',
        'Team1 Code',
        'Team2 Code',
        'Venue',
        'Stage',
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
                        'code' => $result['matchNumber'],
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
     * @return array{status: string, matchNumber: string, missing: array<int, string>}
     */
    private function processRow(array $row, int $eventId): array
    {
        $matchNumber = trim((string) ($row['match_number'] ?? ''));
        if ($matchNumber === '') {
            throw new RuntimeException('Match Number is required.');
        }
        if (strlen($matchNumber) > 50) {
            throw new RuntimeException('Match Number must be 50 characters or fewer.');
        }

        // Unique per event (like a team's code), not globally - the same match
        // number can exist independently in two different events.
        $match = GameMatch::where('event_id', $eventId)->where('match_number', $matchNumber)->first();
        $isNew = !$match;

        $team1Id = $this->resolveTeam($row['team1_code'] ?? null, $eventId);
        $team2Id = $this->resolveTeam($row['team2_code'] ?? null, $eventId);
        $venueId = $this->resolveVenue($row['venue'] ?? null);
        $stage = trim((string) ($row['stage'] ?? ''));

        $matchDate = $this->parseDate($row['match_date'] ?? null);
        $kickOff = $matchDate !== null ? $this->combineDateTime($matchDate, $row['kick_off'] ?? null) : null;

        $attrs = [];
        if ($team1Id !== null && (!$match || $match->team1_id !== $team1Id)) {
            $attrs['team1_id'] = $team1Id;
        }
        if ($team2Id !== null && (!$match || $match->team2_id !== $team2Id)) {
            $attrs['team2_id'] = $team2Id;
        }
        if ($venueId !== null && (!$match || $match->venue_id !== $venueId)) {
            $attrs['venue_id'] = $venueId;
        }
        if ($stage !== '' && (!$match || $match->stage !== $stage)) {
            $attrs['stage'] = $stage;
        }
        if ($matchDate !== null && (!$match || $match->match_date?->format('Y-m-d') !== $matchDate)) {
            $attrs['match_date'] = $matchDate;
        }
        if ($kickOff !== null && (!$match || $match->kick_off?->format('Y-m-d H:i:s') !== $kickOff)) {
            $attrs['kick_off'] = $kickOff;
        }

        if ($isNew) {
            $match = GameMatch::create([
                'match_number' => $matchNumber,
                'event_id' => $eventId,
                ...$attrs,
            ]);
            $changed = true;
        } else {
            $changed = !empty($attrs);
            if ($changed) {
                $match->update($attrs);
            }
        }

        $missing = [];
        if (!$match->team1_id) {
            $missing[] = 'team 1';
        }
        if (!$match->team2_id) {
            $missing[] = 'team 2';
        }
        if (!$match->venue_id) {
            $missing[] = 'venue';
        }
        if (!$match->match_date) {
            $missing[] = 'date';
        }

        return [
            'status' => $isNew ? 'created' : ($changed ? 'updated' : 'unchanged'),
            'matchNumber' => $matchNumber,
            'missing' => $missing,
        ];
    }

    /**
     * Resolves a team by its code within the active event. Some source sheets
     * suffix codes with an age-group marker that isn't part of the team's own
     * code here (e.g. "QAT-17" vs "QAT") - retried without it. Unresolved codes
     * are not an error: future knockout-round rows routinely reference a not-
     * yet-decided team (or even another match's number as a placeholder), so
     * this just leaves team1/team2 blank rather than failing the row.
     */
    private function resolveTeam(mixed $value, int $eventId): ?int
    {
        $code = strtoupper(trim((string) $value));
        if ($code === '') {
            return null;
        }

        $team = Team::where('event_id', $eventId)->where('code', $code)->first();
        if ($team) {
            return $team->id;
        }

        if (preg_match('/^([A-Z]{2,4})-\d+$/', $code, $matches)) {
            $team = Team::where('event_id', $eventId)->where('code', $matches[1])->first();
            if ($team) {
                return $team->id;
            }
        }

        return null;
    }

    /**
     * Resolves a venue by name - best-effort, not a validated field (matches
     * the manual form, where venue is optional), so an unresolved name just
     * leaves this row's venue blank.
     */
    private function resolveVenue(mixed $value): ?int
    {
        $name = trim((string) $value);
        if ($name === '') {
            return null;
        }

        return Venue::whereRaw('LOWER(name) = ?', [strtolower($name)])->value('id');
    }

    private function parseDate(mixed $value): ?string
    {
        $date = trim((string) $value);
        if ($date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable $e) {
            throw new RuntimeException("Could not parse date '{$date}'.");
        }
    }

    /**
     * Mirrors MatchesController's own date+time combination (kick_off is stored
     * as a full datetime: the match's date, plus this HH:MM time).
     */
    private function combineDateTime(string $date, mixed $time): ?string
    {
        $time = trim((string) $time);
        if ($time === '' || !preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches)) {
            return null;
        }

        return sprintf('%s %02d:%02d:00', $date, (int) $matches[1], (int) $matches[2]);
    }
}
