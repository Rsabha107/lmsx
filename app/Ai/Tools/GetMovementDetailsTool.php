<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesRequestContext;
use App\Services\OperationsQueryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetMovementDetailsTool implements Tool
{
    use ResolvesRequestContext;

    public function __construct(private OperationsQueryService $movements) {}

    public function description(): string
    {
        return 'Get full details (route, timing, delay, checkpoints) for one movement or job. Pass whatever identifier you have: a job id like JOB-20260927-0001, a movement code like TRP-00003, or a numeric id. No need to ask the user for a numeric id.';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();
        $eventId = $this->currentEventId();

        $reference = trim((string) ($request['reference'] ?? $request['movement_id'] ?? ''));

        if (! $user || ! $eventId || $reference === '') {
            return json_encode(['error' => 'A job id, movement code or id is required.']);
        }

        $movementId = $this->movements->resolveMovementId($reference, $eventId);
        $details = $movementId ? $this->movements->getMovementDetails($movementId, $user) : null;

        return json_encode($details ?? ['error' => "No movement or job matching \"{$reference}\" was found, or it is not visible to you."]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()
                ->description('Job id (JOB-...), movement code (TRP-...) or numeric movement id.')
                ->required(),
        ];
    }
}
