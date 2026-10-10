<?php

namespace App\Console\Commands;

use App\Models\FleetProvider;
use App\Models\User;
use App\Support\EventFleetPool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Agency and provider users only see their own provider's data, so rows with no provider
 * (or a provider that was deleted) are invisible to them. This hands those rows to one provider.
 */
class AssignOrphansToProvider extends Command
{
    protected $signature = 'fleet:assign-orphans {provider=GWC : Provider code} {--dry-run : Show the counts without changing anything}';

    protected $description = 'Give agency users and unowned movements, events, vehicles and drivers to one provider';

    public function handle(): int
    {
        $provider = FleetProvider::withoutGlobalScopes()->where('code', $this->argument('provider'))->first();

        if (! $provider) {
            $this->error("No provider with code {$this->argument('provider')}.");

            return self::FAILURE;
        }

        $known = FleetProvider::withoutGlobalScopes()->pluck('id')->all();
        $orphaned = fn ($query, string $column) => $query->where(fn ($q) => $q->whereNull($column)->orWhereNotIn($column, $known));

        $agencyIds = User::role(['agency', 'ground_control'])->pluck('users.id');

        $work = [
            'agency and field supervisor users' => DB::table('users')->whereIn('id', $agencyIds)->whereNull('fleet_provider_id'),
            'movements' => $orphaned(DB::table('movements'), 'fleet_provider_id'),
            'events' => DB::table('events')->whereNull('fleet_provider_id'),
            'vehicles' => $orphaned(DB::table('vehicles'), 'provider_id'),
            'drivers' => $orphaned(DB::table('drivers'), 'provider_id'),
        ];

        foreach ($work as $label => $query) {
            $count = $query->count();

            if (! $this->option('dry-run') && $count > 0) {
                $column = in_array($label, ['vehicles', 'drivers'], true) ? 'provider_id' : 'fleet_provider_id';
                $query->update([$column => $provider->id]);
            }

            $this->line(sprintf('  %-13s %d%s', $label, $count, $this->option('dry-run') ? ' (would change)' : ' changed'));
        }

        if (! $this->option('dry-run')) {
            $pooled = EventFleetPool::fill();
            $this->line("  event fleet pools: +{$pooled['vehicles']} vehicles, +{$pooled['drivers']} drivers");
        }

        $this->info("Target provider: {$provider->name} ({$provider->code}).");

        return self::SUCCESS;
    }
}
