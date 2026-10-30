<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesRequestContext;
use App\Models\TeamFlight;
use App\Services\Ai\FlightDelayImpact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetFlightDelayImpactTool implements Tool
{
    use ResolvesRequestContext;

    public function __construct(private FlightDelayImpact $impact) {}

    public function description(): string
    {
        return 'What-if for a late flight: moves the flight\'s movements and their job steps by the delay (nothing is saved), runs the conflict rules again and reports what is newly at risk: the team\'s next movement, crew turnarounds and double-bookings, driver duty, hotel stay, kick-off margins, with the facts and links. Use it for "QR123 is 45 min late, what is at risk?", "what happens if this flight is delayed an hour?". Without delay_minutes it uses the delay the flight is running now (estimated vs scheduled), if there is one.';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();
        $eventId = $this->currentEventId();
        $number = trim((string) ($request['flight_number'] ?? ''));

        if (! $user || ! $eventId || $number === '') {
            return json_encode(['error' => 'A flight number is required.']);
        }

        $date = trim((string) ($request['date'] ?? '')) ?: null;
        if ($date && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return json_encode(['error' => 'date must be YYYY-MM-DD.']);
        }

        $flights = FlightDelayImpact::findFlight($eventId, $number, $date);
        if ($flights->isEmpty()) {
            return json_encode(['error' => "No flight {$number} in this event".($date ? " on {$date}" : '').'.']);
        }
        /** @var TeamFlight $flight */
        $flight = $flights->first();

        $delay = $request['delay_minutes'] ?? null;
        $delay = $delay !== null && $delay !== '' ? (int) $delay : FlightDelayImpact::liveDelay($flight);
        if (! $delay) {
            return json_encode(['error' => "{$flight->flight_number} has no known delay. Ask how many minutes late to assume.", 'flight' => $flight->flight_number]);
        }
        if (abs($delay) > 24 * 60) {
            return json_encode(['error' => 'The delay must be within 24 hours.']);
        }

        $result = $this->impact->build($eventId, $user, $flight, $delay);
        if ($flights->count() > 1) {
            $result['other_flights_with_this_number'] = $flights->slice(1)->take(5)
                ->map(fn (TeamFlight $f) => trim(($f->team?->team_name ?? '').' '.$f->scheduled_at?->format('D j M H:i')))->values()->all();
        }

        return json_encode($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'flight_number' => $schema->string()->description('The flight number, e.g. QR123.')->required(),
            'delay_minutes' => $schema->integer()->description('How many minutes late to assume (negative = early). Leave out to use the live delay.'),
            'date' => $schema->string()->description('The flight\'s date as YYYY-MM-DD, when the same number flies on several days.'),
        ];
    }
}
