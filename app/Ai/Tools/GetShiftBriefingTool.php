<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesRequestContext;
use App\Models\User;
use App\Services\Ai\OperationsAttention;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetShiftBriefingTool implements Tool
{
    use ResolvesRequestContext;

    public function __construct(private OperationsAttention $attention) {}

    public function description(): string
    {
        return 'Shift briefing / handover: everything that needs attention in a time window, in priority order: overdue checkpoint updates, delayed movements, unresolved high conflicts, missing crew (vehicle, driver, supervisor), open issues, and movements with no job yet. Use it for "what needs attention", "brief me", "handover", "what should I worry about in the next N hours", or "brief me on tomorrow". By default it covers the next 4 hours from now; give hours (1-24) for another span, or a date for a whole day.';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();
        $eventId = $this->currentEventId();

        if (! $user instanceof User || ! $eventId) {
            return json_encode(['error' => 'No active event selected.']);
        }

        $date = trim((string) ($request['date'] ?? ''));
        if ($date !== '') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                return json_encode(['error' => 'date must be a valid YYYY-MM-DD date.']);
            }
            $from = Carbon::parse($date)->startOfDay();
            $to = $from->copy()->addDay();
        } else {
            $hours = max(1, min(24, (int) ($request['hours'] ?? 4) ?: 4));
            $from = now();
            $to = now()->addHours($hours);
        }

        return json_encode($this->attention->build($eventId, $user, $from, $to));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'hours' => $schema->integer()
                ->description('How many hours ahead from now to cover (1-24, default 4). Ignored when date is given.'),
            'date' => $schema->string()
                ->description('A whole day to brief on, as YYYY-MM-DD (e.g. tomorrow).'),
        ];
    }
}
