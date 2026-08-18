<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesRequestContext;
use App\Services\OperationsQueryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetJobStatusSummaryTool implements Tool
{
    use ResolvesRequestContext;

    public function __construct(private OperationsQueryService $movements) {}

    public function description(): string
    {
        return 'Get a count of movements grouped by status (pending, dispatched, in-progress, completed, cancelled) for the active event.';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();
        $eventId = $this->currentEventId();

        if (! $user || ! $eventId) {
            return json_encode(['error' => 'No active event selected.']);
        }

        return json_encode($this->movements->getJobStatusSummary($eventId, $user));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
