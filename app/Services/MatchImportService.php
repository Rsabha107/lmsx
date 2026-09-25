<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Movement;
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

    /** Match change made by the row being processed; kept only if the row commits. */
    private ?array $rowMatchChange = null;

    public function __construct(private JobGenerationService $jobs)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{total: int, created: int, updated: int, unchanged: int, incomplete: array, failed: array, match_changes: array}
     */
    public function import(array $rows, int $eventId): array
    {
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $incomplete = [];
        $failed = [];
        $matchChanges = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // header occupies row 1
            $this->rowMatchChange = null;

            try {
                $result = DB::transaction(fn () => $this->processRow($row, $eventId));

                if ($this->rowMatchChange !== null) {
                    $matchChanges[] = $this->rowMatchChange;
                }

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
            'match_changes' => $matchChanges,
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

        // A new date with no kick-off given keeps the known kick-off time, rather
        // than leaving kick_off on the old day while match_date moves.
        if ($matchDate !== null && $kickOff === null && $match?->kick_off
            && $match->kick_off->format('Y-m-d') !== $matchDate) {
            $kickOff = $matchDate . ' ' . $match->kick_off->format('H:i:s');
        }

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
                $before = [
                    'kick_off' => $match->kick_off?->copy(),
                    'team_ids' => [$match->team1_id, $match->team2_id],
                    'venue_id' => $match->venue_id,
                ];
                $match->update($attrs);

                if (isset($attrs['kick_off']) || isset($attrs['team1_id']) || isset($attrs['team2_id']) || isset($attrs['venue_id'])) {
                    $this->rowMatchChange = $this->syncMovements($match->fresh(), $before, $matchNumber);
                }
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
     * Carries a kick-off, line-up or venue change through to the movements
     * planned for the match. Movements not yet turned into a job are
     * rescheduled; anything a person has to fix - an issued job, a team no
     * longer playing, route text naming the old venue - is reported instead.
     *
     * @param array{kick_off: ?Carbon, team_ids: array<int, ?int>, venue_id: ?int} $before
     * @return array<string, mixed>
     */
    private function syncMovements(GameMatch $match, array $before, string $matchNumber): array
    {
        $timeMoved = $before['kick_off']?->format('Y-m-d H:i') !== $match->kick_off?->format('Y-m-d H:i');
        $venueMoved = $before['venue_id'] !== $match->venue_id;
        $venueNames = Venue::whereIn('id', array_filter([$before['venue_id'], $match->venue_id]))->pluck('name', 'id');
        $oldVenue = $venueNames[$before['venue_id']] ?? null;
        $newVenue = $venueNames[$match->venue_id] ?? null;
        $teamIds = array_filter([$match->team1_id, $match->team2_id]);
        $teamNames = Team::whereIn('id', array_filter([...$before['team_ids'], ...$teamIds]))->pluck('code', 'id');
        $lineup = fn (array $ids) => implode(' v ', array_map(fn ($id) => $teamNames[$id] ?? 'TBD', $ids));

        $movements = Movement::where('match_id', $match->id)
            ->with(['match', 'plan', 'team', 'checkpointTemplate.checkpoints'])
            ->orderBy('window_start')
            ->get();

        $results = [];

        foreach ($movements as $movement) {
            $entry = [
                'id' => $movement->id,
                'code' => $movement->code,
                'team' => $movement->team?->code,
                'plan' => $movement->plan?->name,
                'from' => $movement->window_start?->format('D j M H:i'),
                'to' => null,
                'status' => 'unchanged',
                'notes' => [],
            ];

            // Moving a movement to the new team would be guesswork: it may carry
            // that team's hotel, vehicle and passengers.
            if ($movement->team_id && !in_array($movement->team_id, $teamIds, true)) {
                $entry['status'] = 'needs_review';
                $entry['notes'][] = "{$movement->team?->code} is no longer in this match - reassign or cancel this movement.";
                $results[] = $entry;
                continue;
            }

            if ($movement->hasJob()) {
                if ($timeMoved || $venueMoved) {
                    $entry['status'] = 'needs_review';
                    $entry['notes'][] = "Job {$movement->job_id} was already issued - update it by hand.";
                    if ($venueMoved) {
                        $entry['notes'][] = 'The crew may still be heading to ' . ($oldVenue ?? 'the old venue') . '.';
                    }
                    $results[] = $entry;
                }
                continue;
            }

            if ($timeMoved && $this->jobs->recomputeMovementWindow($movement)) {
                $movement->refresh();
                $entry['status'] = 'updated';
                $entry['to'] = $movement->window_start?->format('D j M H:i');

                if ($movement->vehicle_id || $movement->driver_id) {
                    $entry['notes'][] = 'Vehicle/driver kept - check they are still free at the new time.';
                }
                if ($movement->plan?->date && $movement->window_start
                    && !$movement->plan->date->isSameDay($movement->window_start)) {
                    $entry['notes'][] = "Plan is dated {$movement->plan->date->format('D j M')}; this movement is now on {$movement->window_start->format('D j M')}.";
                }
            }

            // Route text is free text copied from the plan template, so it can't be rewritten safely.
            if ($venueMoved && $oldVenue
                && stripos("{$movement->from_location} {$movement->to_location}", $oldVenue) !== false) {
                $entry['status'] = 'needs_review';
                $entry['notes'][] = "Route still says \"{$movement->from_location} → {$movement->to_location}\" - update it to "
                    . ($newVenue ?? 'the new venue') . '.';
            }

            if ($entry['status'] !== 'unchanged') {
                $results[] = $entry;
            }
        }

        return [
            'code' => $matchNumber,
            'time_before' => $before['kick_off']?->format('D j M H:i'),
            'time_after' => $match->kick_off?->format('D j M H:i'),
            'teams_before' => $lineup($before['team_ids']),
            'teams_after' => $lineup([$match->team1_id, $match->team2_id]),
            'venue_before' => $oldVenue,
            'venue_after' => $newVenue,
            'movements' => $results,
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
