<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\AuditLog;
use App\Models\BaseCampHotel;
use App\Models\Country;
use App\Models\Team;
use App\Models\TeamClassification;
use App\Models\TeamFlight;
use App\Models\TeamStay;
use App\Models\TeamTraining;
use App\Services\FlightMovementSync;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The Event Teams "Add a team" / "Edit team" dialog: the team, its arrival and departure, hotel and training
 * sessions, saved together. A flight whose time or number changes re-times its planned movements through
 * FlightMovementSync, like an edit in the flight form. Flights are never deleted here (the flight form does).
 */
class TeamProfileController extends Controller
{
    public function __construct(private FlightMovementSync $movementSync) {}

    /** The team and pick lists for opening the dialog outside Event Teams (Planning's Edit movement). */
    public function show(int $eventId, int $teamId): JsonResponse
    {
        $team = Team::where('event_id', $eventId)->withProfile($eventId)->findOrFail($teamId);

        return response()->json([
            'team' => $team,
            'airports' => Airport::orderBy('name')->get(),
            'countries' => Country::active()->orderBy('country_name')->get(),
            'classifications' => TeamClassification::active()->orderBy('name')->get(),
            'baseCampHotels' => BaseCampHotel::active()->orderBy('name')->get(['id', 'name']),
            'groupOptions' => Team::where('event_id', $eventId)->whereNotNull('group_pool')->where('group_pool', '!=', '')
                ->distinct()->orderBy('group_pool')->pluck('group_pool'),
        ]);
    }

    public function store(Request $request, int $eventId): RedirectResponse
    {
        return $this->save($request, $eventId, null);
    }

    public function update(Request $request, int $eventId, string $teamCode): RedirectResponse
    {
        return $this->save($request, $eventId, Team::where('event_id', $eventId)->where('code', $teamCode)->firstOrFail());
    }

    private function save(Request $request, int $eventId, ?Team $team): RedirectResponse
    {
        $leg = fn (string $side) => [
            "{$side}.id" => ['nullable', 'integer', Rule::exists('team_flights', 'id')->where('event_id', $eventId)->where('direction', $side)->where('team_id', $team?->id ?? 0)],
            "{$side}.date" => 'nullable|date_format:Y-m-d',
            "{$side}.time" => "nullable|required_with:{$side}.date|date_format:H:i",
            "{$side}.flight_number" => 'nullable|string|max:20',
            "{$side}.origin_airport_id" => 'nullable|integer|exists:airports,id',
            "{$side}.destination_airport_id" => 'nullable|integer|exists:airports,id',
            "{$side}.remove" => 'nullable|boolean',
            "{$side}.status" => 'nullable|in:auto,scheduled,delayed,landed,departed,cancelled',
            "{$side}.actual_time" => 'nullable|date_format:H:i',
            "{$side}.gate" => 'nullable|string|max:50',
            "{$side}.notes" => 'nullable|string|max:2000',
        ];

        $data = $request->validate([
            'team.code' => ['required', 'string', 'max:10', Rule::unique('teams', 'code')->where('event_id', $eventId)->ignore($team?->id)],
            'team.team_name' => 'required|string|max:255',
            'team.country_id' => 'required|string|max:10|exists:countries,country_code',
            'team.flag' => 'nullable|string|max:10',
            'team.group_pool' => 'nullable|string|max:50',
            'team.role' => ['required', Rule::in(Team::ROLES)],
            'team.classification_type_id' => 'nullable|integer|exists:team_classifications,id',
            'team.head_of_delegation' => 'nullable|string|max:255',
            'team.bib_accent_color' => 'nullable|string|max:20',
            'team.notes' => 'nullable|string|max:2000',
            'arrival' => 'nullable|array',
            'departure' => 'nullable|array',
            ...$leg('arrival'),
            ...$leg('departure'),
            // Travelling party, applied to both flights; sent only when changed in the dialog.
            'party' => 'nullable|array',
            'party.players' => 'nullable|integer|min:0|max:999',
            'party.staff' => 'nullable|integer|min:0|max:999',
            'party.bags' => 'nullable|integer|min:0|max:9999',
            'hotel_name' => 'nullable|string|max:255',
            'trainings' => 'nullable|array|max:30',
            'trainings.*.id' => ['nullable', 'integer', Rule::exists('team_trainings', 'id')->where('event_id', $eventId)->where('team_id', $team?->id ?? 0)],
            'trainings.*.training_ground' => 'nullable|string|max:255',
            'trainings.*.start_at' => 'required|date_format:Y-m-d H:i',
            'trainings.*.notes' => 'nullable|string|max:500',
        ], [
            'arrival.time.required_with' => 'Give the arrival a time.',
            'departure.time.required_with' => 'Give the departure a time.',
            'trainings.*.start_at.required' => 'Each training session needs a date and time.',
        ]);

        $original = $team?->getOriginal() ?? [];
        $changes = [];

        DB::transaction(function () use ($data, $eventId, &$team, &$changes) {
            $team ??= new Team(['event_id' => $eventId]);
            $team->fill($data['team'] + ['event_id' => $eventId]);
            $team->save();

            foreach (['arrival', 'departure'] as $side) {
                if ($change = $this->saveFlight($team, $side, $data[$side] ?? null, $data['party'] ?? null)) {
                    $changes[] = $change;
                }
            }
            $this->saveHotel($team, $data['hotel_name'] ?? null, $data);
            $this->saveTrainings($team, $data['trainings'] ?? []);
        });

        AuditLog::change($original ? 'Team updated' : 'Team added', "{$team->code} · {$team->team_name}",
            ($original ? AuditLog::changes($team, $original) : ['role' => $team->role]) + array_filter(['flights' => array_column($changes, 'flight') ?: null]),
            $team, $eventId);

        $movements = collect($changes)->flatMap(fn ($c) => $c['change']['movements'] ?? []);
        $review = $movements->where('status', 'needs_review')->count();
        $synced = $movements->where('status', 'updated')->count();
        $retimed = $movements->where('status', 'job_retimed')->count();
        $cleared = array_sum(array_column($changes, 'cleared'));
        $message = trim(($original ? 'Team updated.' : 'Team added.')
            .($synced ? " {$synced} planned ".($synced === 1 ? 'movement' : 'movements').' updated.' : '')
            .($retimed ? " {$retimed} ".($retimed === 1 ? 'job' : 'jobs').' re-timed and supervisors notified.' : '')
            .($review ? " {$review} ".($review === 1 ? 'movement already has a job' : 'movements already have jobs').' and must be updated by hand.' : '')
            .($cleared ? " {$cleared} planned ".($cleared === 1 ? 'movement' : 'movements').' lost '.($cleared === 1 ? 'its' : 'their').' flight and times: no job can be generated until the flight is added again.' : ''));

        return redirect()->back()->with('success', $message)
            ->with('flight_change', $movements->isNotEmpty() ? ['movements' => $movements->values()->all()] : null);
    }

    /** Creates or updates the team's arrival or departure; returns what the change did to plans, if anything. */
    private function saveFlight(Team $team, string $side, ?array $leg, ?array $party): ?array
    {
        if (! $leg) {
            return null;
        }
        $flight = ! empty($leg['id']) ? TeamFlight::find($leg['id']) : null;
        if (! empty($leg['remove'])) {
            return $flight ? $this->removeFlight($flight, $side) : null;
        }
        $hasData = ! empty($leg['date']) || ! empty($leg['flight_number']);
        if (! $flight && ! $hasData) {
            return null;
        }

        $scheduled = ! empty($leg['date']) ? Carbon::parse("{$leg['date']} {$leg['time']}") : $flight?->scheduled_at;
        $values = [
            'flight_number' => $leg['flight_number'] ? strtoupper(trim($leg['flight_number'])) : null,
            'scheduled_at' => $scheduled,
        ] + array_intersect_key($leg, array_flip(['origin_airport_id', 'destination_airport_id', 'gate', 'notes']))
          + $this->partyValues($party)
          + $this->statusValues($leg, $scheduled, $side);

        if (! $flight) {
            // The home end left on "Auto": the airport the event's other flights in this direction use most.
            $home = $side === 'arrival' ? 'destination_airport_id' : 'origin_airport_id';
            $values[$home] ??= $this->homeAirport($team->event_id, $side);
            $flight = TeamFlight::create($values + [
                'event_id' => $team->event_id,
                'team_id' => $team->id,
                'direction' => $side,
            ]);
            // Picks up this team's movements left without a flight (e.g. after the old one was removed) and times them.
            $change = $this->movementSync->sync($flight, ['flight_number' => null, 'scheduled_at' => null, 'party_size_total' => null], $team->code);

            return ['flight' => "{$side} {$flight->flight_number} added", 'change' => $change];
        }

        $before = FlightMovementSync::before($flight);
        $flight->fill($values);
        if (! $flight->isDirty()) {
            return null;
        }
        $flight->fill(FlightMovementSync::staleTracking($flight, $before));
        $flight->save();
        $change = FlightMovementSync::changed($flight, $before)
            ? $this->movementSync->sync($flight, $before, $team->code)
            : null;

        return ['flight' => "{$side} {$flight->flight_number} updated", 'change' => $change];
    }

    /** Deletes the flight through FlightMovementSync::remove (planned movements lose their times). */
    private function removeFlight(TeamFlight $flight, string $side): array
    {
        $label = trim("{$side} {$flight->flight_number}");
        $result = FlightMovementSync::remove($flight, "{$side}.remove");

        return ['flight' => "{$label} removed", 'change' => null] + $result;
    }

    /** The travelling party on a flight; the total is players + staff (a total-only import is left alone when both are blank). */
    private function partyValues(?array $party): array
    {
        if ($party === null) {
            return [];
        }
        $players = $party['players'] ?? null;
        $staff = $party['staff'] ?? null;

        return [
            'party_size_players' => $players,
            'party_size_staff' => $staff,
            'planned_bags' => $party['bags'] ?? null,
        ] + ($players !== null || $staff !== null ? ['party_size_total' => (int) $players + (int) $staff] : []);
    }

    /**
     * The status picked in the dialog. Null = not touched (the flight keeps what it has, e.g. OAG's live status);
     * "auto" = hand it back to the flight source by clearing the hand-set status, times and delay; anything
     * else is set by hand (the next refresh may update it).
     */
    private function statusValues(array $leg, ?Carbon $scheduled, string $side): array
    {
        $status = $leg['status'] ?? null;
        $at = function () use ($leg, $scheduled) {
            if (empty($leg['actual_time']) || ! $scheduled) {
                return null;
            }
            $t = Carbon::parse($scheduled->toDateString().' '.$leg['actual_time']);
            // Past midnight: 00:20 for a 23:50 flight is the next day (and the reverse).
            if ($t->diffInMinutes($scheduled, false) > 12 * 60) {
                $t->addDay();
            } elseif ($scheduled->diffInMinutes($t, false) > 12 * 60) {
                $t->subDay();
            }

            return $t;
        };
        $delay = fn (?Carbon $t) => $t && $scheduled ? max(0, (int) round($scheduled->diffInMinutes($t, false))) : null;

        return match ($status) {
            'scheduled' => ['flight_status' => 'scheduled', 'actual_at' => null, 'estimated_at' => null, 'delay_minutes' => null],
            'delayed' => ['flight_status' => 'scheduled', 'actual_at' => null, 'estimated_at' => $at(), 'delay_minutes' => $delay($at())],
            'landed', 'departed' => ['flight_status' => $side === 'arrival' ? 'landed' : 'active', 'actual_at' => $at() ?? $scheduled, 'delay_minutes' => $delay($at() ?? $scheduled)],
            'cancelled' => ['flight_status' => 'cancelled'],
            'auto' => ['flight_status' => null, 'actual_at' => null, 'estimated_at' => null, 'delay_minutes' => null],
            default => [],
        };
    }

    private function homeAirport(int $eventId, string $side): ?int
    {
        $column = $side === 'arrival' ? 'destination_airport_id' : 'origin_airport_id';

        return TeamFlight::where('event_id', $eventId)->where('direction', $side)->whereNotNull($column)
            ->selectRaw("{$column} as airport, COUNT(*) as n")->groupBy($column)->orderByDesc('n')->value('airport');
    }

    /** Sets the hotel on the team's latest stay (dates from its flights when the stay is new). An empty name leaves it. */
    private function saveHotel(Team $team, ?string $hotel, array $data): void
    {
        $hotel = trim((string) $hotel);
        if ($hotel === '') {
            return;
        }
        $stay = TeamStay::where('event_id', $team->event_id)->where('team_id', $team->id)->latest('id')->first();
        if ($stay) {
            $stay->update(['hotel_name' => $hotel]);

            return;
        }
        TeamStay::create([
            'event_id' => $team->event_id,
            'team_id' => $team->id,
            'hotel_name' => $hotel,
            'check_in' => $data['arrival']['date'] ?? null,
            'check_out' => $data['departure']['date'] ?? null,
        ]);
    }

    /** The dialog shows every session, so what it sends replaces the team's sessions. */
    private function saveTrainings(Team $team, array $sessions): void
    {
        $keep = [];
        foreach ($sessions as $s) {
            $values = [
                'training_ground' => $s['training_ground'] ?? null,
                'training_start_at' => Carbon::parse($s['start_at']),
                'notes' => $s['notes'] ?? null,
            ];
            $training = ! empty($s['id'])
                ? tap(TeamTraining::find($s['id']))->update($values)
                : TeamTraining::create($values + ['event_id' => $team->event_id, 'team_id' => $team->id]);
            $keep[] = $training->id;
        }
        TeamTraining::where('event_id', $team->event_id)->where('team_id', $team->id)->whereNotIn('id', $keep)->delete();
    }
}
