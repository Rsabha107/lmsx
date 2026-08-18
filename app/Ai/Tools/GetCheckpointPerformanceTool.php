<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesRequestContext;
use App\Services\OperationsQueryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetCheckpointPerformanceTool implements Tool
{
    use ResolvesRequestContext;

    public function __construct(private OperationsQueryService $movements) {}

    public function description(): string
    {
        return 'Get the average delay (in minutes) per checkpoint name across the active event, ordered worst first — use this to find which step in the process tends to run late.';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();
        $eventId = $this->currentEventId();

        if (! $user || ! $eventId) {
            return json_encode(['error' => 'No active event selected.']);
        }

        return json_encode($this->movements->getCheckpointPerformance($eventId, $user));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
