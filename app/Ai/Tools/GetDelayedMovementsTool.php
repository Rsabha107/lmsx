<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesRequestContext;
use App\Services\OperationsQueryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetDelayedMovementsTool implements Tool
{
    use ResolvesRequestContext;

    public function __construct(private OperationsQueryService $movements) {}

    public function description(): string
    {
        return 'Get movements that currently have a recorded delay (delay_minutes > 0), for the active event, ordered by the largest delay first.';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();
        $eventId = $this->currentEventId();

        if (! $user || ! $eventId) {
            return json_encode(['error' => 'No active event selected.']);
        }

        return json_encode($this->movements->getDelayedMovements($eventId, $user));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
