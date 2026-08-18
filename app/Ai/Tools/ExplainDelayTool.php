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
        return 'Get a checkpoint-by-checkpoint delay breakdown for one specific job by its numeric ID — which checkpoint contributed the most delay, and how much time (if any) was recovered afterward.';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();

        if (! $user || ! $request['job_id']) {
            return json_encode(['error' => 'job_id is required.']);
        }

        $result = $this->movements->explainDelay((int) $request['job_id'], $user);

        return json_encode($result ?? ['error' => 'Job not found, or not visible to you.']);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'job_id' => $schema->integer()
                ->description('The numeric ID of the job to explain (jobs_operations.id, not the JOB-xxxx string).')
                ->required(),
        ];
    }
}
