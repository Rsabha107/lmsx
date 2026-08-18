<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesRequestContext;
use App\Services\OperationsQueryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetMissingUpdatesTool implements Tool
{
    use ResolvesRequestContext;

    public function __construct(private OperationsQueryService $movements) {}

    public function description(): string
    {
        return 'Get active/dispatched jobs that have an overdue checkpoint — i.e. a step that should have happened by now but has no update recorded. Use this to find operations that need a status check.';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();
        $eventId = $this->currentEventId();

        if (! $user || ! $eventId) {
            return json_encode(['error' => 'No active event selected.']);
        }

        return json_encode($this->movements->getMissingUpdates($eventId, $user));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
