<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class FleetSupervisorTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function payload(array $override = []): array
    {
        return $override + ['name' => 'New Sup', 'email' => 'new.sup@example.com', 'password' => 'secret-pass-1'];
    }

    public function test_an_admin_adds_a_field_supervisor_with_a_provider_and_the_active_event(): void
    {
        $event = $this->createEvent();
        $admin = $this->actingAs($this->createUserWithRole('admin'))->withSession(['active_event_id' => $event->id]);

        $admin->post('/fleet/supervisors', $this->payload())->assertSessionHasErrors('provider_id');

        $admin->post('/fleet/supervisors', $this->payload(['provider_id' => $this->fixtureProvider()->id]))
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'new.sup@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('ground_control'));
        $this->assertSame($this->fixtureProvider()->id, $user->fleet_provider_id);
        $this->assertTrue($user->events()->whereKey($event->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Supervisor created']);
        $this->assertStringNotContainsString('secret-pass-1', json_encode(\App\Models\AuditLog::all()->toArray()));
    }

    public function test_the_new_supervisor_is_offered_for_crew_assignment(): void
    {
        $event = $this->createEvent();
        $this->actingAs($this->createUserWithRole('admin'))->withSession(['active_event_id' => $event->id])
            ->post('/fleet/supervisors', $this->payload(['provider_id' => $this->fixtureProvider()->id]));

        $this->actingAs($this->createProviderUser('agency'))->withSession(['active_event_id' => $event->id])
            ->get('/crew-assignment')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('supervisors', fn ($list) => collect($list)->pluck('name')->contains('New Sup')));
    }

    public function test_an_agency_can_only_add_supervisors_to_its_own_provider(): void
    {
        $event = $this->createEvent();
        $other = $this->createProvider('Other');

        $this->actingAs($this->createProviderUser('agency'))->withSession(['active_event_id' => $event->id])
            ->post('/fleet/supervisors', $this->payload(['provider_id' => $other->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->fixtureProvider()->id, User::where('email', 'new.sup@example.com')->value('fleet_provider_id'));
    }

    public function test_the_email_must_be_unique_and_the_password_long_enough(): void
    {
        $event = $this->createEvent();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->createProviderUser('agency'))->withSession(['active_event_id' => $event->id])
            ->post('/fleet/supervisors', $this->payload(['email' => 'taken@example.com', 'password' => 'short']))
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_a_supervisor_keeps_a_phone_and_role_and_can_start_from_a_contact(): void
    {
        $event = $this->createEvent();
        $contact = Contact::create(['name' => 'Airport Person', 'role' => 'Airport Lead', 'phone' => '+974 5000 1111']);
        $admin = $this->actingAs($this->createUserWithRole('admin'))->withSession(['active_event_id' => $event->id]);

        $admin->post('/fleet/supervisors', $this->payload([
            'name' => 'Airport Person', 'phone' => '+974 5000 1111', 'job_title' => 'Airport Lead',
            'contact_id' => $contact->id, 'provider_id' => $this->fixtureProvider()->id,
        ]))->assertSessionHasNoErrors();

        $user = User::where('email', 'new.sup@example.com')->firstOrFail();
        $this->assertSame(['+974 5000 1111', 'Airport Lead'], [$user->phone, $user->job_title]);
        $this->assertSame($user->id, $contact->refresh()->user_id);

        // A contact can only be taken by one login.
        $admin->post('/fleet/supervisors', $this->payload(['email' => 'second@example.com', 'contact_id' => $contact->id, 'provider_id' => $this->fixtureProvider()->id]))
            ->assertSessionHasErrors('contact_id');

        $admin->get('/fleet')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('contacts', fn ($list) => collect($list)->isEmpty())
            ->where('supervisors.0.job_title', 'Airport Lead'));
    }

    public function test_editing_a_supervisor_updates_the_login_and_its_contact(): void
    {
        $event = $this->createEvent();
        $supervisor = $this->createProviderUser('ground_control');
        $contact = Contact::create(['name' => 'Old', 'role' => 'Old role', 'phone' => '1']);
        $contact->forceFill(['user_id' => $supervisor->id])->save();

        $this->actingAs($this->createProviderUser('agency'))->withSession(['active_event_id' => $event->id])
            ->put("/fleet/supervisors/{$supervisor->id}", ['name' => 'Renamed', 'phone' => '+974 777', 'job_title' => 'VSA Lead'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Renamed', '+974 777', 'VSA Lead'], [$supervisor->refresh()->name, $supervisor->phone, $supervisor->job_title]);
        $this->assertSame(['Renamed', '+974 777', 'VSA Lead'], [$contact->refresh()->name, $contact->phone, $contact->role]);
    }

    public function test_an_agency_cannot_edit_another_providers_supervisor_or_a_non_supervisor(): void
    {
        $event = $this->createEvent();
        $theirs = $this->createProviderUser('ground_control', $this->createProvider('Other'));
        $admin = User::factory()->create(['fleet_provider_id' => $this->fixtureProvider()->id]);
        $agency = $this->actingAs($this->createProviderUser('agency'))->withSession(['active_event_id' => $event->id]);

        $agency->put("/fleet/supervisors/{$theirs->id}", ['name' => 'Hijacked'])->assertNotFound();
        $agency->put("/fleet/supervisors/{$admin->id}", ['name' => 'Hijacked'])->assertNotFound();
        $this->assertNotSame('Hijacked', $theirs->refresh()->name);
    }

    public function test_the_one_off_command_gives_supervisors_a_contact_once(): void
    {
        $linkedByName = $this->createProviderUser('ground_control');
        $linkedByName->update(['name' => 'Same Name']);
        $existing = Contact::create(['name' => 'same name', 'role' => 'Hotel Lead', 'phone' => '+974 1']);
        $fresh = $this->createProviderUser('ground_control');
        $fresh->update(['name' => 'Fresh Face', 'phone' => '+974 2']);

        $this->artisan('fleet:supervisors-to-contacts')->assertSuccessful();
        $this->assertDatabaseMissing('pma_contacts', ['user_id' => $fresh->id]);

        $this->artisan('fleet:supervisors-to-contacts', ['--apply' => true])->assertSuccessful();
        $this->artisan('fleet:supervisors-to-contacts', ['--apply' => true])->assertSuccessful();

        $this->assertSame($linkedByName->id, $existing->refresh()->user_id);
        $this->assertSame('Hotel Lead', $linkedByName->refresh()->job_title);
        $this->assertDatabaseHas('pma_contacts', ['user_id' => $fresh->id, 'name' => 'Fresh Face', 'role' => 'Field Supervisor', 'phone' => '+974 2', 'org' => 'GWC']);
        $this->assertSame(2, Contact::whereNotNull('user_id')->count());
        $this->assertSame(2, Contact::count());
    }

    public function test_roles_are_limited_to_the_list_but_an_existing_one_can_stay(): void
    {
        $event = $this->createEvent();
        $admin = $this->actingAs($this->createUserWithRole('admin'))->withSession(['active_event_id' => $event->id]);

        $admin->post('/fleet/supervisors', $this->payload(['job_title' => 'Chief Wizard', 'provider_id' => $this->fixtureProvider()->id]))
            ->assertSessionHasErrors('job_title');
        $admin->post('/contacts', ['name' => 'C', 'role' => 'Chief Wizard'])->assertSessionHasErrors('role');
        $admin->post('/contacts', ['name' => 'C', 'role' => 'VSA Lead'])->assertSessionHasNoErrors();

        $legacy = Contact::create(['name' => 'Old', 'role' => 'Legacy Role']);
        $admin->put("/contacts/{$legacy->id}", ['name' => 'Old Renamed', 'role' => 'Legacy Role'])->assertSessionHasNoErrors();
        $this->assertSame('Old Renamed', $legacy->refresh()->name);
        $admin->put("/contacts/{$legacy->id}", ['name' => 'Old Renamed', 'role' => 'Another Odd One'])->assertSessionHasErrors('role');
    }

    public function test_someone_without_fleet_rights_cannot_add_a_supervisor(): void
    {
        $event = $this->createEvent();

        $this->actingAs($this->createProviderUser('ground_control'))->withSession(['active_event_id' => $event->id])
            ->post('/fleet/supervisors', $this->payload())
            ->assertForbidden();
    }
}
