<?php

use App\Models\Event;
use App\Models\FleetProvider;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Usage: php artisan tinker scripts/create-agency-users.php
$eventIds = Event::active()->pluck('id');
$provider = FleetProvider::withoutGlobalScopes()->first();

foreach (range(1, 4) as $i) {
    $user = User::updateOrCreate(
        ['email' => "agency{$i}@example.com"],
        ['name' => "Agency User {$i}", 'password' => Hash::make('password'), 'fleet_provider_id' => $provider?->id],
    );
    $user->syncRoles(['agency']);
    $user->events()->syncWithoutDetaching($eventIds);

    echo "{$user->id} {$user->email}\n";
}
