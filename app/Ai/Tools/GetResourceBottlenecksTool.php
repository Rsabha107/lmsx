<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ResolvesRequestContext;
use App\Services\Ai\ResourceBottlenecks;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetResourceBottlenecksTool implements Tool
{
    use ResolvesRequestContext;

    public function __construct(private ResourceBottlenecks $bottlenecks) {}

    public function description(): string
    {
        return 'Vehicle (truck/bus), driver and supervisor needs per day for a date range: how many are required, assigned and still missing, the peak needed at one time, the event pool size and any shortfall at the peak, plus double-bookings and driver duty problems. Use it for "how many trucks do we need today/tomorrow", "are we short of drivers this week", "where are the crew bottlenecks". Default: today only.';
    }

    public function handle(Request $request): string
    {
        $user = $this->currentUser();
        $eventId = $this->currentEventId();

        if (! $user || ! $eventId) {
            return json_encode(['error' => 'No active event selected.']);
        }

        [$from, $to] = DateRange::from($request['from'] ?? null, $request['to'] ?? null, 1, ResourceBottlenecks::MAX_DAYS);
        if (is_string($from)) {
            return json_encode(['error' => $from]);
        }

        $type = in_array($request['type'] ?? 'all', ['vehicle', 'driver', 'supervisor', 'all'], true) ? ($request['type'] ?? 'all') : 'all';

        return json_encode($this->bottlenecks->build($eventId, $user, $from, $to, $type));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('First day, YYYY-MM-DD (default today).'),
            'to' => $schema->string()->description('Last day, YYYY-MM-DD (default same as from). At most 14 days.'),
            'type' => $schema->string()->enum(['vehicle', 'driver', 'supervisor', 'all'])
                ->description('Which resource to report (default all). Trucks and buses are vehicles.'),
        ];
    }
}
