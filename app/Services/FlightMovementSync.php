<?php

namespace App\Services;

use App\Models\Movement;
use App\Models\TeamFlight;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Carries a team flight change (number, scheduled time, party size) through to the movements
 * planned against it. Movements without a job are updated and re-timed from the settings offsets.
 * A new time also re-times a job not started yet, or flags a started one (FlightJobRetimer); other
 * changes to a movement with a job are reported for review. Used by the team import, the Event Teams
 * edits and the OAG refresh.
 */
class FlightMovementSync
{
    public function __construct(private JobGenerationService $jobs, private FlightJobRetimer $retimer)
    {
    }

    /**
     * Deletes a flight. Refused while a job was generated from it (the job's times come from the flight).
     * Its other movements stay (the FK sets flight_id null) but lose their window and flight number, since
     * both came from the flight, and can't generate a job until they get a flight again (Movement::jobBlocker).
     *
     * @return array{cleared: int}
     *
     * @throws ValidationException under $errorKey when a job depends on the flight
     */
    public static function remove(TeamFlight $flight, string $errorKey = 'flight'): array
    {
        if ($jobs = $flight->issuedJobIds()) {
            $label = trim(ucfirst($flight->direction).' '.($flight->flight_number ?? ''));
            throw ValidationException::withMessages([$errorKey => sprintf(
                '%s can\'t be removed: %s %s generated from it. Delete %s in the Jobs Queue first, or change the flight instead.',
                $label, count($jobs) === 1 ? 'job' : 'jobs', implode(', ', $jobs).(count($jobs) === 1 ? ' was' : ' were'),
                count($jobs) === 1 ? 'that job' : 'those jobs',
            )]);
        }
        $cleared = Movement::where('flight_id', $flight->id)
            ->update(['window_start' => null, 'window_end' => null, 'flight_number' => null, 'flight_id' => null]);
        $flight->delete();

        return ['cleared' => $cleared];
    }

    /**
     * The flight's values before an edit, to hand to sync() after saving.
     *
     * @return array{flight_number: ?string, scheduled_at: ?Carbon, party_size_total: ?int}
     */
    public static function before(TeamFlight $flight): array
    {
        return [
            'flight_number' => $flight->flight_number,
            'scheduled_at' => $flight->scheduled_at?->copy(),
            'party_size_total' => $flight->party_size_total,
        ];
    }

    /** Whether the saved flight differs from $before in anything its movements use. */
    public static function changed(TeamFlight $flight, array $before): bool
    {
        return $before['flight_number'] !== $flight->flight_number
            || $before['scheduled_at']?->format('Y-m-d H:i') !== $flight->scheduled_at?->format('Y-m-d H:i')
            || $before['party_size_total'] !== $flight->party_size_total;
    }

    /**
     * Live tracking belongs to the flight that was tracked: a new number or another day makes it stale.
     * Call with the new values filled in but not yet saved.
     *
     * @return array<string, null>
     */
    public static function staleTracking(TeamFlight $flight, array $before): array
    {
        $numberChanged = $before['flight_number'] !== null && $before['flight_number'] !== $flight->flight_number;
        $dateChanged = $flight->scheduled_at !== null
            && $before['scheduled_at']?->format('Y-m-d') !== $flight->scheduled_at->format('Y-m-d');

        return $numberChanged || $dateChanged
            ? ['estimated_at' => null, 'actual_at' => null, 'delay_minutes' => null, 'flight_status' => null, 'flight_synced_at' => null]
            : [];
    }

    /**
     * @param  array{flight_number: ?string, scheduled_at: ?Carbon, party_size_total: ?int}  $before
     * @return array<string, mixed>
     */
    public function sync(TeamFlight $flight, array $before, string $code): array
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
        $movedPlans = [];

        foreach ($movements as $movement) {
            $entry = [
                'id' => $movement->id,
                'code' => $movement->code,
                'kind' => $movement->kind,
                'plan' => $movement->plan?->name,
                'plan_id' => $movement->plan_id,
                'from' => $movement->window_start?->format('D j M H:i'),
                'to' => null,
                'status' => 'unchanged',
                'notes' => [],
            ];

            if ($movement->hasJob()) {
                // A new time re-times a job not started yet, or flags a started one (FlightJobRetimer), when the
                // job is timed from this flight: linked to it, or a transfer (timed off the team's arrival).
                $timedByFlight = $movement->flight_id === $flight->id || $movement->kind === 'transfer';
                if ($timeMoved && $timedByFlight) {
                    if ($outcome = $this->retimer->handle($movement, $flight, $before)) {
                        $movement->refresh();
                        $results[] = array_merge($entry, $outcome, ['to' => $movement->window_start?->format('D j M H:i')]);
                    }
                } elseif ($timeMoved || $paxChanged || $numberChanged) {
                    $entry['status'] = 'needs_review';
                    $entry['job_ref'] = $movement->job_id;
                    $entry['notes'][] = "Job {$movement->job_id} was already issued - update it by hand.";
                    $results[] = $entry;
                }
                continue;
            }

            $attrs = [];
            if ($movement->kind === $flight->direction && $movement->flight_id === null) {
                $attrs['flight_id'] = $flight->id;
                // A movement whose flight was removed lost its number too; it takes this flight's.
                if ($movement->flight_number === null && $flight->flight_number !== null) {
                    $attrs['flight_number'] = $flight->flight_number;
                }
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
            if (isset($attrs['flight_number'])) {
                $entry['notes'][] = "Flight {$before['flight_number']} → {$flight->flight_number}.";
            }
            if ($rescheduled && ($movement->vehicle_id || $movement->driver_id)) {
                $entry['notes'][] = 'Vehicle/driver kept - check they are still free at the new time.';
            }
            if ($rescheduled && ($note = $this->followPlanDate($movement, $flight, $before, $movedPlans))) {
                $entry['notes'][] = $note;
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

    /**
     * Plans are dated by the flight's day. When the flight moves to another day, a plan that is only
     * this team's and has no job moves with it. A plan shared with other teams (bulk plans group a
     * day's arrivals) keeps its date and the planner is told.
     *
     * @param  array{scheduled_at: ?Carbon}  $before
     * @param  array<int, true>  $movedPlans
     */
    private function followPlanDate(Movement $movement, TeamFlight $flight, array $before, array &$movedPlans): ?string
    {
        $plan = $movement->plan;
        $oldDay = $before['scheduled_at'];
        $newDay = $flight->scheduled_at;

        if (!$plan || !$oldDay || !$newDay || $oldDay->isSameDay($newDay) || isset($movedPlans[$plan->id])) {
            return null;
        }
        if (!$plan->date?->isSameDay($oldDay)) {
            return null;
        }

        $ownedByTeam = !$plan->movements()
            ->where(fn ($q) => $q->where('team_id', '!=', $flight->team_id)->orWhereNull('team_id')->orWhereNotNull('job_id'))
            ->exists();

        if (!$ownedByTeam) {
            return "Plan {$plan->name} is shared with other teams, so it stays on {$plan->date->format('D j M')}; the flight is now on {$newDay->format('D j M')}.";
        }

        $plan->update(['date' => $newDay->toDateString()]);
        $movedPlans[$plan->id] = true;

        return "Plan date moved {$oldDay->format('D j M')} → {$newDay->format('D j M')}.";
    }
}
