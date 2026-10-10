<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\FleetProvider;
use App\Models\User;
use App\Support\EventFleetPool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Agency users only see their provider's data, so anything with no (or a deleted) provider is
 * invisible to them. This hands that unowned data to one provider; dry-run unless --apply.
 */
class AdoptUnownedFleetData extends Command
{
    protected $signature = 'fleet:adopt-unowned {provider : Provider id or code} {--apply : Write the changes (default is a dry run)}';

    protected $description = 'Give unowned movements, events, vehicles, drivers and provider-less agency users to one provider';

    public function handle(): int
    {
        $key = $this->argument('provider');

        $provider = FleetProvider::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('id', ctype_digit($key) ? (int) $key : 0)->orWhere('code', $key))
            ->first();

        if (! $provider) {
            $this->error("No provider with id or code \"{$key}\".");

            return self::FAILURE;
        }

        $validIds = FleetProvider::withoutGlobalScopes()->pluck('id')->all();
        $unowned = fn ($query, string $column) => $query->where(fn ($q) => $q->whereNull($column)->orWhereNotIn($column, $validIds));

        $agencyIds = User::role('agency')->whereNull('fleet_provider_id')->pluck('users.id');

        $targets = [
            'agency users without a provider' => DB::table('users')->whereIn('id', $agencyIds)->whereNull('fleet_provider_id'),
            'movements' => $unowned(DB::table('movements'), 'fleet_provider_id'),
            'events (default provider)' => DB::table('events')->whereNull('fleet_provider_id'),
            'vehicles' => $unowned(DB::table('vehicles'), 'provider_id'),
            'drivers' => $unowned(DB::table('drivers'), 'provider_id'),
        ];

        $columns = ['agency users without a provider' => 'fleet_provider_id', 'movements' => 'fleet_provider_id', 'events (default provider)' => 'fleet_provider_id', 'vehicles' => 'provider_id', 'drivers' => 'provider_id'];

        $this->info("Provider: {$provider->code} - {$provider->name} (id {$provider->id})");

        $counts = [];
        foreach ($targets as $label => $query) {
            $counts[$label] = (clone $query)->count();
            $this->line(sprintf('  %-32s %d', $label, $counts[$label]));
        }

        if (! $this->option('apply')) {
            $this->warn('Dry run. Re-run with --apply to write these changes.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($targets, $columns, $provider) {
            foreach ($targets as $label => $query) {
                $query->update([$columns[$label] => $provider->id]);
            }
        });

        AuditLog::change('Unowned fleet data adopted', $provider->code, $counts + ['provider_id' => $provider->id]);

        $pooled = EventFleetPool::fill();
        $this->line("  event fleet pools: +{$pooled['vehicles']} vehicles, +{$pooled['drivers']} drivers");

        $this->info('Done.');

        return self::SUCCESS;
    }
}
