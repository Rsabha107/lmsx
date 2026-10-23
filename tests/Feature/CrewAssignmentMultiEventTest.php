<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class CrewAssignmentMultiEventTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function movementAt(Event $event, string $start, array $attrs = [])
    {
        $at = Carbon::parse($start);

        return $this->createMovement($event, $this->createPlan($event), $this->createTeam($event), $attrs + [
            'window_start' => $at, 'window_end' => $at->copy()->addHour(),
        ]);
    }

    /** Agency user (crew assignment) on event A, also assigned to event B. */
    private function agencyOn(Event ...$events): User
    {
        $user = $this->createProviderUser('agency');
        $user->events()->attach(collect($events)->pluck('id'));

        return $user;
    }

    public function test_a_second_event_shows_its_movements_and_pool_beside_the_active_one(): void
    {
        [$a, $b] = [$this->createEvent(), $this->createEvent()];
        $mine = $this->movementAt($a, '2026-11-10 10:00');
        $theirs = $this->movementAt($b, '2026-11-10 10:30');
        $busA = $this->createVehicle($a, ['code' => 'BUS-A']);
        $busB = $this->createVehicle($b, ['code' => 'BUS-B']);
        $user = $this->agencyOn($a, $b);

        $this->actingAs($user)->withSession(['active_event_id' => $a->id])
            ->get("/crew-assignment?date=2026-11-10&events[]={$b->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('eventIds', [$a->id, $b->id])
                ->where('movements', fn ($list) => collect($list)->pluck('id')->sort()->values()->all() === collect([$mine->id, $theirs->id])->sort()->values()->all()
                    && collect($list)->firstWhere('id', $theirs->id)['event'] === $b->name)
                ->where('vehicles', fn ($list) => collect($list)->firstWhere('id', $busA->id)['event_ids'] === [$a->id]
                    && collect($list)->firstWhere('id', $busB->id)['event_ids'] === [$b->id]));

        // The choice is remembered, and can be cleared.
        $this->get('/crew-assignment?date=2026-11-10')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('eventIds', [$a->id, $b->id]));
        $this->get('/crew-assignment?date=2026-11-10&events=')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('eventIds', [$a->id])
                ->where('movements', fn ($list) => collect($list)->pluck('id')->all() === [$mine->id]));
    }

    public function test_the_picker_offers_the_events_the_user_can_open(): void
    {
        [$a, $b, $c] = [$this->createEvent(), $this->createEvent(), $this->createEvent()];
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->withSession(['active_event_id' => $a->id])->get('/crew-assignment')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('events', fn ($list) => collect($list)->pluck('id')->sort()->values()->all() === [$a->id, $b->id, $c->id]));

        $this->actingAs($this->agencyOn($a, $b))->withSession(['active_event_id' => $a->id])->get('/crew-assignment')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('events', fn ($list) => collect($list)->pluck('id')->sort()->values()->all() === [$a->id, $b->id]));
    }

    public function test_an_event_the_user_cannot_open_is_ignored_and_its_movements_cannot_be_crewed(): void
    {
        [$a, $b] = [$this->createEvent(), $this->createEvent()];
        $theirs = $this->movementAt($b, '2026-11-10 10:30');
        $user = $this->agencyOn($a);

        $as = $this->actingAs($user)->withSession(['active_event_id' => $a->id]);
        $as->get("/crew-assignment?date=2026-11-10&events[]={$b->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('eventIds', [$a->id])->where('movements', []));

        $as->patch("/movements/{$theirs->id}/crew", ['vehicle_id' => null, 'driver_id' => null, 'field_supervisor_id' => null])
            ->assertForbidden();
    }

    public function test_crew_can_be_saved_on_the_other_event_and_a_cross_event_double_booking_is_flagged(): void
    {
        [$a, $b] = [$this->createEvent(), $this->createEvent()];
        $mine = $this->movementAt($a, '2026-11-10 10:00');
        $theirs = $this->movementAt($b, '2026-11-10 10:30');
        $driver = $this->createDriver($a, ['name' => 'Shared Driver']);
        $driver->events()->attach($b->id);
        $user = $this->agencyOn($a, $b);

        $as = $this->actingAs($user)->withSession(['active_event_id' => $a->id]);
        $as->patch("/movements/{$mine->id}/crew", ['vehicle_id' => null, 'driver_id' => $driver->id, 'field_supervisor_id' => null])
            ->assertSessionHasNoErrors();
        $as->patch("/movements/{$theirs->id}/crew", ['vehicle_id' => null, 'driver_id' => $driver->id, 'field_supervisor_id' => null])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning');

        $this->assertSame($driver->id, $theirs->refresh()->driver_id);

        $as->get("/crew-assignment?date=2026-11-10&events[]={$b->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('movements', fn ($list) => collect($list)
                ->every(fn ($m) => ! empty($m['clashes']['driver'] ?? []))));
    }

    public function test_a_vehicle_from_the_other_events_pool_is_refused_for_this_events_movement(): void
    {
        [$a, $b] = [$this->createEvent(), $this->createEvent()];
        $mine = $this->movementAt($a, '2026-11-10 10:00');
        $busB = $this->createVehicle($b, ['code' => 'BUS-B']);
        $user = $this->agencyOn($a, $b);

        $this->actingAs($user)->withSession(['active_event_id' => $a->id])
            ->patch("/movements/{$mine->id}/crew", ['vehicle_id' => $busB->id, 'driver_id' => null, 'field_supervisor_id' => null])
            ->assertSessionHasErrors('vehicle_id');
    }
}
