<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\FleetProvider;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GwcLeadSupervisorSeeder extends Seeder
{
    /**
     * GWC lead supervisors assign vehicles, drivers and supervisors to jobs via
     * the agency role. The random password is never shared; they set their own via "Forgot password".
     */
    public function run(): void
    {
        $supervisors = [
            'neddy.francis@gwclogistics.com'      => 'Neddy Francis',
            'mohammed.yousuff@gwclogistics.com'   => 'Mohammed Yousuff',
            'andrey.zvyagintsev@gwclogistics.com' => 'Andrey Zvyagintsev',
            'nithin.george@gwclogistics.com'      => 'Nithin George',
        ];

        // The agency role lacks events.access-all, so without assignments they would see nothing.
        $eventIds = Event::active()->pluck('id');

        // Agency users see only their provider's movements, so they must be tied to GWC.
        $gwc = FleetProvider::withoutGlobalScopes()->where('name', 'like', '%GWC%')->first()
            ?? FleetProvider::withoutGlobalScopes()->create(['code' => 'GWC', 'name' => 'GWC']);

        foreach ($supervisors as $email => $name) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Str::random(40)],
            );

            $user->update(['fleet_provider_id' => $gwc->id]);
            $user->assignRole('agency');
            $user->events()->syncWithoutDetaching($eventIds);
        }
    }
}
