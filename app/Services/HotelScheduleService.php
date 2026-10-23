<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\HotelCrewAssignment;
use App\Models\HotelDutyDay;
use App\Models\HotelVsaDay;
use App\Models\RosterMember;
use App\Models\Setting;
use App\Models\TeamStay;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * The hotel team schedule: for each team hotel (from team stays) and match day (from kick-offs),
 * the teams playing with their report time (KO minus an offset) and shift end, plus what the
 * planner assigned: supervisor, crew team per team, kit runs to/from the venue, VSA crews.
 */
class HotelScheduleService
{
    public const REPORT_KEY = 'hotel_schedule.report_before_ko';
    public const END_KEY = 'hotel_schedule.end_after_ko';
    public const DEFAULT_MINUTES = 270;

    private const STAGES = [
        'MD-R1' => 'GS', 'MD-R2' => 'GS', 'MD-R3' => 'GS',
        'R32' => 'R32', 'R16' => 'R16', 'QF' => 'QF', 'SF' => 'SF', '3PL' => '3PL', 'F' => 'F',
    ];
    public const STAGE_NAMES = [
        'GS' => 'Group stage', 'R32' => 'Round of 32', 'R16' => 'Round of 16', 'QF' => 'Quarter-finals',
        'SF' => 'Semi-finals', '3PL' => 'Third place', 'F' => 'Final',
    ];

    public function __construct(private readonly SettingsService $settings)
    {
    }

    /** One hotel however its name was typed on the team stays. */
    public static function hotelKey(?string $name): ?string
    {
        $name = preg_replace('/\s+/', ' ', trim((string) $name));

        return $name === '' ? null : mb_strtoupper($name);
    }

    /** @return array{report_before: int, end_after: int} minutes before / after kick-off */
    public function offsets(int $eventId): array
    {
        $get = fn (string $key) => $this->settings->getEventValue($key, $eventId);

        return [
            'report_before' => (int) ($get(self::REPORT_KEY) ?? self::DEFAULT_MINUTES),
            'end_after' => (int) ($get(self::END_KEY) ?? self::DEFAULT_MINUTES),
        ];
    }

    public function setOffsets(int $eventId, int $reportBefore, int $endAfter): void
    {
        $this->settings->setSetting(self::REPORT_KEY, $reportBefore, Setting::SCOPE_EVENT, $eventId, 'Hotel schedule: report time, minutes before kick-off');
        $this->settings->setSetting(self::END_KEY, $endAfter, Setting::SCOPE_EVENT, $eventId, 'Hotel schedule: shift end, minutes after kick-off');
    }

    /** The hotel keys of the event's team stays. */
    public function hotelKeys(int $eventId): array
    {
        return TeamStay::where('event_id', $eventId)->pluck('hotel_name')
            ->map(fn ($n) => self::hotelKey($n))->filter()->unique()->values()->all();
    }

    public function build(int $eventId): array
    {
        $offsets = $this->offsets($eventId);
        $stays = TeamStay::where('event_id', $eventId)->with('team:id,code,team_name')->get()
            ->filter(fn (TeamStay $s) => self::hotelKey($s->hotel_name));

        // Display name: the spelling used most often for the hotel.
        $hotelNames = $stays->groupBy(fn (TeamStay $s) => self::hotelKey($s->hotel_name))
            ->map(fn (Collection $group) => trim($group->countBy(fn ($s) => preg_replace('/\s+/', ' ', trim($s->hotel_name)))->sortDesc()->keys()->first()));
        $staysByTeam = $stays->groupBy('team_id');

        $matches = GameMatch::where('event_id', $eventId)
            ->where(fn ($q) => $q->whereNotNull('kick_off')->orWhereNotNull('match_date'))
            ->with(['team1:id,code,team_name', 'team2:id,code,team_name', 'venue:id,name'])
            ->orderBy('kick_off')->orderBy('match_number')
            ->get();

        $crews = HotelCrewAssignment::where('event_id', $eventId)->get()
            ->mapWithKeys(fn ($c) => [$c->match_id . '|' . $c->team_id => $c->crew_team]);
        $dutyDays = HotelDutyDay::where('event_id', $eventId)->get()
            ->keyBy(fn ($d) => $d->hotel . '|' . $d->date->toDateString());

        $hotels = $hotelNames->map(fn ($name, $key) => [
            'key' => $key,
            'name' => $name,
            'teams' => $stays->filter(fn ($s) => self::hotelKey($s->hotel_name) === $key)
                ->map(fn ($s) => $this->shortCode($s->team?->code))->filter()->unique()->sort()->values()->all(),
            'days' => [],
        ])->all();

        $dayInfo = [];
        $unhoused = [];
        foreach ($matches as $match) {
            $date = ($match->kick_off ?? $match->match_date)->toDateString();
            $dayInfo[$date]['stages'][] = self::STAGES[$match->stage] ?? $match->stage;
            $dayInfo[$date]['matches'] = ($dayInfo[$date]['matches'] ?? 0) + 1;
            if (! $match->team1_id || ! $match->team2_id) {
                $dayInfo[$date]['tbd'] = ($dayInfo[$date]['tbd'] ?? 0) + 1;
            }

            foreach ([[$match->team1, $match->team2], [$match->team2, $match->team1]] as [$team, $opponent]) {
                if (! $team) {
                    continue;
                }
                $stay = $this->stayOn($staysByTeam->get($team->id), $date);
                $key = $stay ? self::hotelKey($stay->hotel_name) : null;
                if (! $key || ! isset($hotels[$key])) {
                    $unhoused[$team->id] = $this->shortCode($team->code);

                    continue;
                }

                $ko = $match->kick_off;
                $hotels[$key]['days'][$date]['teams'][] = [
                    'match_id' => $match->id,
                    'team_id' => $team->id,
                    'code' => $this->shortCode($team->code),
                    'name' => $team->team_name,
                    'opponent' => $opponent ? $this->shortCode($opponent->code) : 'TBD',
                    'match_number' => $match->match_number,
                    'venue' => $match->venue?->name,
                    'kick_off' => $ko?->format('H:i'),
                    'report' => $ko?->copy()->subMinutes($offsets['report_before'])->format('H:i'),
                    'end' => $ko?->copy()->addMinutes($offsets['end_after'])->format('H:i'),
                    'crew' => $crews[$match->id . '|' . $team->id] ?? null,
                ];
            }
        }

        // Saved days stay visible even if the match moved away from them.
        foreach ($dutyDays as $duty) {
            if (isset($hotels[$duty->hotel])) {
                $hotels[$duty->hotel]['days'][$duty->date->toDateString()] ??= ['teams' => []];
            }
        }

        foreach ($hotels as &$hotel) {
            foreach ($hotel['days'] as $date => &$day) {
                usort($day['teams'], fn ($a, $b) => [$a['report'] ?? '99', $a['code']] <=> [$b['report'] ?? '99', $b['code']]);
                $duty = $dutyDays[$hotel['key'] . '|' . $date] ?? null;
                $day['supervisor_id'] = $duty?->supervisor_id;
                $day['departure'] = ['crews' => $duty?->departure_crews ?? [], 'time' => $duty?->departure_time ? substr($duty->departure_time, 0, 5) : null];
                $day['arrival'] = ['crews' => $duty?->arrival_crews ?? [], 'time' => $duty?->arrival_time ? substr($duty->arrival_time, 0, 5) : null];
            }
            unset($day);
            ksort($hotel['days']);
        }
        unset($hotel);

        $dates = array_keys($dayInfo);
        sort($dates);
        $days = $dates
            ? collect(CarbonPeriod::create($dates[0], end($dates)))->map(function (Carbon $d) use ($dayInfo) {
                $key = $d->toDateString();
                $info = $dayInfo[$key] ?? null;
                $stage = $info ? collect($info['stages'])->countBy()->sortDesc()->keys()->first() : null;

                return [
                    'date' => $key,
                    'stage' => $stage,
                    'stage_name' => $stage ? (self::STAGE_NAMES[$stage] ?? $stage) : null,
                    'rest' => ! $info,
                    'matches' => $info['matches'] ?? 0,
                    'tbd' => $info['tbd'] ?? 0,
                ];
            })->values()->all()
            : [];

        return [
            'offsets' => $offsets,
            'days' => $days,
            'hotels' => collect($hotels)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
            'vsa' => (object) HotelVsaDay::where('event_id', $eventId)->get()
                ->mapWithKeys(fn ($v) => [$v->date->toDateString() => array_values($v->crews ?? [])])->all(),
            'supervisors' => RosterMember::where('event_id', $eventId)->with('user:id,name,job_title')
                ->orderBy('sort')->orderBy('id')->get()
                ->filter(fn ($m) => $m->user)
                ->map(fn ($m) => ['id' => $m->user_id, 'name' => $m->user->name, 'designation' => $m->designation ?? $m->user->job_title])
                ->values()->all(),
            'unhoused' => array_values(array_unique($unhoused)),
            'undated' => GameMatch::where('event_id', $eventId)->whereNull('kick_off')->whereNull('match_date')->count(),
        ];
    }

    /**
     * Fills what is still empty: crew teams numbered 1, 2, 3… per hotel and day in report-time
     * order (skipping numbers already used there), then the kit runs: departure with the first
     * team's crew at its report time, arrival with the last team's. Returns how many were filled.
     */
    public function autoFill(int $eventId): int
    {
        $filled = 0;
        $schedule = $this->build($eventId);

        foreach ($schedule['hotels'] as $hotel) {
            foreach ($hotel['days'] as $date => $day) {
                $teams = $day['teams'];
                if (! $teams) {
                    continue;
                }

                $used = array_filter(array_column($teams, 'crew'));
                $next = 1;
                foreach ($teams as $i => $team) {
                    if ($team['crew']) {
                        continue;
                    }
                    while (in_array($next, $used, true)) {
                        $next++;
                    }
                    HotelCrewAssignment::updateOrCreate(
                        ['match_id' => $team['match_id'], 'team_id' => $team['team_id']],
                        ['event_id' => $eventId, 'crew_team' => $next],
                    );
                    $teams[$i]['crew'] = $used[] = $next;
                    $filled++;
                }

                $first = $teams[0];
                $last = $teams[count($teams) - 1];
                $attrs = [];
                if (! $day['departure']['crews'] && ! $day['departure']['time']) {
                    $attrs += ['departure_crews' => [$first['crew']], 'departure_time' => $first['report']];
                }
                if (! $day['arrival']['crews'] && ! $day['arrival']['time']) {
                    $attrs += ['arrival_crews' => [$last['crew']], 'arrival_time' => $last['report']];
                }
                if ($attrs) {
                    HotelDutyDay::updateOrCreate(['event_id' => $eventId, 'hotel' => $hotel['key'], 'date' => $date], $attrs);
                    $filled++;
                }
            }
        }

        return $filled;
    }

    private function stayOn(?Collection $stays, string $date): ?TeamStay
    {
        return $stays?->first(fn (TeamStay $s) => (! $s->check_in || $s->check_in->toDateString() <= $date)
            && (! $s->check_out || $s->check_out->toDateString() >= $date));
    }

    /** "UGA-17" → "UGA", as the schedule shows it. */
    private function shortCode(?string $code): ?string
    {
        return $code ? preg_replace('/-\d+$/', '', $code) : null;
    }
}
