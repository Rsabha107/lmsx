<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesRequestContext;
use App\Services\Ai\DataQualityWatchdog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetDataQualityTool implements Tool
{
    use ResolvesRequestContext;

    public function __construct(private DataQualityWatchdog $watchdog) {}

    public function description(): string
    {
        return 'Data-quality watchdog for the coming days: flight data that has not been refreshed (or never matched the flight source), flights past their time with no landing or departure recorded, movements with no job close to their start, assigned crew that no longer fits (other provider, not in the event fleet, deactivated, in maintenance, marked off), missing or empty checkpoint templates, jobs with no checkpoints, movements missing a time, route or team, and possible duplicate movements. Each finding has a severity and the fact behind it. Use it for "is our data OK", "anything wrong with the data", "check the flights", "data quality".';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();
        $eventId = $this->currentEventId();

        if (! $user || ! $eventId) {
            return json_encode(['error' => 'No active event selected.']);
        }

        $days = max(1, min(31, (int) ($request['days'] ?? 7) ?: 7));

        return json_encode($this->watchdog->build($eventId, $user, $days));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('How many days ahead to check movements (1-31, default 7). Flights are always checked up to 72 h ahead.'),
        ];
    }
}
