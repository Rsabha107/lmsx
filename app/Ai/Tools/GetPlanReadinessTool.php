<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesRequestContext;
use App\Services\Ai\PlanReadiness;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetPlanReadinessTool implements Tool
{
    use ResolvesRequestContext;

    public function __construct(private PlanReadiness $readiness) {}

    public function description(): string
    {
        return 'Plan readiness: which plans can or cannot generate jobs and why (no time, no vehicle, no supervisor, no driver, no checkpoint template, flight or match without a time, open high conflicts), with the next step for each plan. Give a plan (code, name or id) for one plan, or a date range; with neither it covers today and the next 6 days. Use it for "which plans aren\'t ready", "why can\'t this plan generate jobs", "what is blocking plan X".';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();
        $eventId = $this->currentEventId();

        if (! $user || ! $eventId) {
            return json_encode(['error' => 'No active event selected.']);
        }

        $plan = trim((string) ($request['plan'] ?? '')) ?: null;
        [$from, $to] = DateRange::from($request['from'] ?? null, $request['to'] ?? null, 7, 31);
        if (is_string($from)) {
            return json_encode(['error' => $from]);
        }

        return json_encode($this->readiness->build($eventId, $user, $from, $to, $plan));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan' => $schema->string()->description('A plan code, name (or part of it) or id, to check one plan.'),
            'from' => $schema->string()->description('First day, YYYY-MM-DD (default today).'),
            'to' => $schema->string()->description('Last day, YYYY-MM-DD (default 6 days after from).'),
        ];
    }
}
