<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesRequestContext;
use App\Services\OperationsQueryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ExplainDelayTool implements Tool
{
    use ResolvesRequestContext;

    public function __construct(private OperationsQueryService $movements) {}

    public function description(): string
    {
        return 'Get a checkpoint-by-checkpoint delay breakdown for one job — which checkpoint contributed the most delay, and how much time (if any) was recovered afterward. Pass a job id like JOB-20260927-0001, a movement code like TRP-00003, or a numeric id; no need to ask the user for a numeric id.';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();
        $eventId = $this->currentEventId();

        $reference = trim((string) ($request['reference'] ?? $request['job_id'] ?? ''));

        if (! $user || ! $eventId || $reference === '') {
            return json_encode(['error' => 'A job id, movement code or id is required.']);
        }

        $jobId = $this->movements->resolveJobId($reference, $eventId);
        $result = $jobId ? $this->movements->explainDelay($jobId, $user) : null;

        return json_encode($result ?? ['error' => "No job matching \"{$reference}\" was found, or it is not visible to you."]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()
                ->description('Job id (JOB-...), movement code (TRP-...) or numeric job id.')
                ->required(),
        ];
    }
}
