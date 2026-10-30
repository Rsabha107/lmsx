<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesRequestContext;
use App\Services\Ai\OperationsAttention;
use App\Services\ConflictDetectionService;
use App\Services\OperationsQueryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetCrewOptionsTool implements Tool
{
    use ResolvesRequestContext;

    public function __construct(private OperationsQueryService $movements, private ConflictDetectionService $conflicts) {}

    public function description(): string
    {
        return 'For one movement: which vehicles, drivers and supervisors are free to take it and why each other one is not (busy on another movement, too short a turnaround, too little rest, marked off, in maintenance, too few seats). These are the same rules Crew Assignment uses to grey out options. Use it for "why can\'t I pick driver X for TRP-...", "who is free for this movement", "which vehicle can take it". Give a movement code, job id or id, and optionally a role and a name to focus on.';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();
        $eventId = $this->currentEventId();
        $reference = trim((string) ($request['reference'] ?? ''));

        if (! $user || ! $eventId || $reference === '') {
            return json_encode(['error' => 'A movement code, job id or id is required.']);
        }
        if (! $user->can('movements.view') && ! $user->can('movements.assign-crew')) {
            return json_encode(['error' => 'Crew options are only available to users who can view or crew movements.']);
        }

        $id = $this->movements->resolveMovementId($reference, $eventId);
        $movement = $id ? OperationsAttention::visibleMovements($eventId, $user)->find($id) : null;
        if (! $movement) {
            return json_encode(['error' => "No movement matching \"{$reference}\" was found, or it is not visible to you."]);
        }

        $options = $this->conflicts->crewOptions($movement);
        $role = $request['role'] ?? null;
        $name = mb_strtolower(trim((string) ($request['name'] ?? '')));
        $roles = in_array($role, ['vehicle', 'driver', 'supervisor'], true) ? [$role] : ['vehicle', 'driver', 'supervisor'];

        $out = [
            'movement_code' => $movement->code,
            'window' => $options['movement']['span'] ?? null,
            'url' => '/crew-assignment?date='.$movement->window_start?->toDateString(),
        ];
        foreach ($roles as $r) {
            $list = collect($options[$r.'s'] ?? [])
                ->when($name !== '', fn ($c) => $c->filter(fn ($o) => str_contains(mb_strtolower($o['label']), $name)));
            $out[$r] = [
                'free' => $list->where('free', true)->count(),
                'unavailable' => $list->where('free', false)->count(),
                'free_names' => $list->where('free', true)->take(15)->pluck('label')->values()->all(),
                'unavailable_with_reason' => $list->where('free', false)->take(15)
                    ->map(fn ($o) => ['name' => $o['label'], 'reason' => $o['reason']])->values()->all(),
            ];
        }
        $out['note'] = 'Only the movement\'s own event pool (and its provider) is offered, as on Crew Assignment. Lists are capped at 15; counts are complete.';

        return json_encode($out);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()->description('Movement code (TRP-...), job id (JOB-...) or id.')->required(),
            'role' => $schema->string()->enum(['vehicle', 'driver', 'supervisor'])->description('Only this role (default all three).'),
            'name' => $schema->string()->description('Part of a driver, supervisor or vehicle name to focus on.'),
        ];
    }
}
