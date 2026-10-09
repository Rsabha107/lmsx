<?php

namespace App\Models\Scopes;

use App\Models\Driver;
use App\Models\FleetProvider;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A transport provider's users only ever see their own movements, jobs, fleet
 * and provider record. A restricted user with no provider assigned sees nothing.
 */
class ProviderScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isProviderRestricted()) {
            return;
        }

        $providerId = $user->fleet_provider_id;

        if (! $providerId) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $table = $model->getTable();

        match (true) {
            $model instanceof Movement => $builder->where("{$table}.fleet_provider_id", $providerId),
            $model instanceof JobOperation => $builder->whereIn(
                "{$table}.movement_id",
                DB::table('movements')->where('fleet_provider_id', $providerId)->select('id'),
            ),
            $model instanceof Driver, $model instanceof Vehicle => $builder->where("{$table}.provider_id", $providerId),
            $model instanceof FleetProvider => $builder->where("{$table}.id", $providerId),
            default => null,
        };
    }
}
