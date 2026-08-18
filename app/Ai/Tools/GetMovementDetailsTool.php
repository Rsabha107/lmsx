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
        return 'Get full details (route, timing, delay, checkpoints) for one specific movement by its numeric ID.';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();

        if (! $user || ! $request['movement_id']) {
            return json_encode(['error' => 'movement_id is required.']);
        }

        $details = $this->movements->getMovementDetails((int) $request['movement_id'], $user);

        return json_encode($details ?? ['error' => 'Movement not found, or not visible to you.']);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'movement_id' => $schema->integer()
                ->description('The numeric ID of the movement to look up.')
                ->required(),
        ];
    }
}
