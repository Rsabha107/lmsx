<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesRequestContext;
use App\Services\OperationsQueryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetUpcomingMovementsTool implements Tool
{
    use ResolvesRequestContext;

    public function __construct(private OperationsQueryService $movements) {}

    public function description(): string
    {
        return 'Get movements scheduled to depart within the next N minutes (default 120), for the active event.';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();
        $eventId = $this->currentEventId();

        if (! $user || ! $eventId) {
            return json_encode(['error' => 'No active event selected.']);
        }

        $withinMinutes = (int) ($request['within_minutes'] ?? 120);

        return json_encode($this->movements->getUpcomingMovements($eventId, $user, $withinMinutes));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'within_minutes' => $schema->integer()
                ->description('How many minutes ahead to look. Defaults to 120.')
                ->min(1)
                ->max(1440),
        ];
    }
}
