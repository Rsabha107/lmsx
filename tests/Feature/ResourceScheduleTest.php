<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class ResourceScheduleTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function agency(int $eventId): static
    {
        return $this->actingAs($this->createProviderUser('agency'))->withSession(['active_event_id' => $eventId]);
    }

    public function test_a_vehicles_week_lists_lead_and_extra_bookings_planned_or_generated(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $truck = $this->createVehicle($event, ['code' => 'TRK-1']);

        $lead = $this->createMovement($event, $plan, $team = $this->createTeam($event), [
            'vehicle_id' => $truck->id, 'window_start' => '2026-11-24 09:00', 'window_end' => '2026-11-24 10:00',
        ]);
        $this->createJob($event, $lead, $team);
        $extra = $this->createMovement($event, $plan, $this->createTeam($event), [
            'window_start' => '2026-11-26 14:00', 'window_end' => '2026-11-26 15:00',
        ]);
        $extra->units()->create(['vehicle_id' => $truck->id]);
        // Next week: not shown.
        $this->createMovement($event, $plan, $this->createTeam($event), [
            'vehicle_id' => $truck->id, 'window_start' => '2026-12-02 09:00', 'window_end' => '2026-12-02 10:00',
        ]);

        $this->agency($event->id)
            ->get("/resource-schedule?type=vehicle&id={$truck->id}&week=2026-11-25")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ResourceSchedule')
                ->where('weekStart', '2026-11-23')
                ->has('items', 2)
                ->where('items.0.code', $lead->code)
                ->where('items.0.role', 'Lead vehicle')
                ->whereNot('items.0.job_code', null)
                ->where('items.1.code', $extra->code)
                ->where('items.1.role', 'Extra vehicle')
                ->where('items.1.job_code', null)
                // The calendar counts every booked day, not just this week's.
                ->where('bookedDates', ['2026-11-24' => 1, '2026-11-26' => 1, '2026-12-02' => 1]));
    }

    public function test_the_week_exports_to_excel(): void
    {
        $event = $this->createEvent();
        $driver = $this->createDriver($event, ['name' => 'Excel Driver']);
        $this->createMovement($event, $this->createPlan($event), $this->createTeam($event), [
            'driver_id' => $driver->id, 'window_start' => '2026-11-24 09:00', 'window_end' => '2026-11-24 10:00',
        ]);

        $response = $this->agency($event->id)->get("/resource-schedule/export?type=driver&id={$driver->id}&week=2026-11-24");

        $response->assertOk();
        $this->assertStringContainsString('Schedule_Excel-Driver_20261123.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_it_needs_the_movements_view_permission(): void
    {
        $event = $this->createEvent();
        $user = User::factory()->create();
        $user->assignRole('ground_control');

        $this->actingAs($user)->withSession(['active_event_id' => $event->id])
            ->get('/resource-schedule')
            ->assertForbidden();
    }
}
