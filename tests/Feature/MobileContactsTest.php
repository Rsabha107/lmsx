<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class MobileContactsTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private function fieldSupervisor(?Event $event, array $attributes = []): User
    {
        foreach (['jobs.view', 'jobs.view-all-functional-areas'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'ground_control', 'guard_name' => 'web'])
            ->syncPermissions(['jobs.view', 'jobs.view-all-functional-areas']);

        $user = User::factory()->create($attributes);
        $user->assignRole('ground_control');
        if ($event) {
            $user->events()->attach($event->id);
        }

        return $user;
    }

    public function test_contacts_come_from_crew_directory_supervisors_and_providers(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $me = $this->fieldSupervisor($event);
        $partner = $this->fieldSupervisor($event, ['name' => 'Partner Sup', 'phone' => '+974 1111']);
        $colleague = $this->fieldSupervisor($event, ['name' => 'Colleague Sup', 'phone' => '+974 2222', 'job_title' => 'Shift Lead']);
        $elsewhere = $this->fieldSupervisor(null, ['name' => 'Other Event Sup']);
        $driver = $this->createDriver($event, ['name' => 'Crew Driver', 'phone' => '+974 3333', 'status' => 'on_shift']);

        $movement = $this->createMovement($event, $this->createPlan($event), $team, ['driver_id' => $driver->id]);
        $movement->extraSupervisors()->attach($partner->id);
        $this->createJob($event, $movement, $team, ['supervisor_id' => $me->id]);

        $desk = Contact::create(['name' => 'Ops Desk', 'role' => 'Control', 'org' => 'NAQLA', 'phone' => '+974 4444', 'on_shift' => true]);
        $gone = Contact::create(['name' => 'Disabled Person', 'role' => 'Control', 'org' => 'NAQLA', 'phone' => '+974 5555', 'disabled' => true]);
        $this->fixtureProvider()->update(['contact_person' => 'Provider Lead', 'phone' => '+974 6666']);

        Sanctum::actingAs($me);
        $contacts = collect($this->getJson("/api/mobile/profile?event_id={$event->id}")->assertOk()->json('contacts'))->keyBy('id');

        $crewDriver = $contacts["driver-{$driver->id}"];
        $this->assertSame(['My crew', 'Driver · '.$team->code, '+974 3333', true], [$crewDriver['org'], $crewDriver['role'], $crewDriver['phone'], $crewDriver['online']]);
        $this->assertSame('My crew', $contacts["user-{$partner->id}"]['org']);
        $this->assertSame('Supervisor · '.$team->code, $contacts["user-{$partner->id}"]['role']);
        $this->assertSame('NAQLA', $contacts["contact-{$desk->id}"]['org']);
        $this->assertSame('Field supervisors', $contacts["user-{$colleague->id}"]['org']);
        $this->assertSame('Shift Lead', $contacts["user-{$colleague->id}"]['role']);
        $this->assertSame(['Provider Lead', 'GWC', '+974 6666'], [
            $contacts["provider-{$this->fixtureProvider()->id}"]['name'],
            $contacts["provider-{$this->fixtureProvider()->id}"]['org'],
            $contacts["provider-{$this->fixtureProvider()->id}"]['phone'],
        ]);

        $this->assertArrayNotHasKey("user-{$me->id}", $contacts->all());
        $this->assertArrayNotHasKey("user-{$elsewhere->id}", $contacts->all());
        $this->assertArrayNotHasKey("contact-{$gone->id}", $contacts->all());
        // The crew partner isn't listed twice.
        $this->assertSame(1, $contacts->where('name', 'Partner Sup')->count());
    }
}
