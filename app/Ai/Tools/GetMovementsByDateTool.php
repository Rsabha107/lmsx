<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesRequestContext;
use App\Services\OperationsQueryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetMovementsByDateTool implements Tool
{
    use ResolvesRequestContext;

    public function __construct(private OperationsQueryService $movements) {}

    public function description(): string
    {
        return 'List the movements starting on a specific calendar date (past, today or future) for the active event, in time order, including planned movements whose job has not been generated yet (job_generated = false). Use this whenever the user asks about a particular day, e.g. "what movements do I have on 27 Nov 2026". Optionally narrow to one team.';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();
        $eventId = $this->currentEventId();

        if (! $user || ! $eventId) {
            return json_encode(['error' => 'No active event selected.']);
        }

        $date = (string) ($request['date'] ?? '');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            return json_encode(['error' => 'date must be a valid YYYY-MM-DD date.']);
        }

        $team = trim((string) ($request['team'] ?? '')) ?: null;

        return json_encode($this->movements->getMovementsByDate($eventId, $user, $date, $team));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()
                ->description('The day to look up, as YYYY-MM-DD (convert "27 Nov 2026" to 2026-11-27).')
                ->required(),
            'team' => $schema->string()
                ->description('Optional team name (or part of it) to narrow the list to.'),
        ];
    }
}
