<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Services\ConflictDetectionService;
use App\Services\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class ConflictThresholdTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function thresholds(array $override = []): array
    {
        return $override + array_map(fn ($t) => $t['default'], SettingsService::CONFLICT_THRESHOLDS);
    }

    private function restClashes($event): array
    {
        return array_values(array_filter(
            app(ConflictDetectionService::class)->forEvent($event->id),
            fn ($c) => $c['type'] === 'Insufficient Rest',
        ));
    }

    /** Ends 23:00, starts again 08:30 next day: 9.5 hours of rest, outside the 14-hour duty window. */
    private function shortRest($event): void
    {
        $plan = $this->createPlan($event);
        $driver = Driver::create(['name' => 'Mia', 'status' => 'available'])->id;
        foreach ([['2026-11-27 18:00', '2026-11-27 23:00'], ['2026-11-28 08:30', '2026-11-28 09:30']] as [$start, $end]) {
            $team = $this->createTeam($event);
            $movement = $this->createMovement($event, $plan, $team, ['driver_id' => $driver, 'window_start' => $start, 'window_end' => date('Y-m-d H:i', strtotime($start.' +30 minutes'))]);
            $job = $this->createJob($event, $movement, $team);
            $this->createCheckpoint($event, $job, ['order' => 1, 'scheduled_at' => $start]);
            $this->createCheckpoint($event, $job, ['order' => 2, 'scheduled_at' => $end]);
        }
    }

    public function test_the_defaults_apply_until_changed_and_a_saved_limit_takes_over(): void
    {
        $event = $this->createEvent();
        $this->shortRest($event);
        $this->assertCount(1, $this->restClashes($event));

        $this->actingAs($this->createUserWithRole('admin'))
            ->post('/setups/settings/thresholds', $this->thresholds(['driver_rest_hours' => 9]))
            ->assertSessionHasNoErrors();

        $this->assertSame(9, app(SettingsService::class)->getThreshold('driver_rest_hours'));
        $this->assertSame([], $this->restClashes($event));
        $this->assertDatabaseHas('audit_logs', ['action' => 'Conflict thresholds changed']);
    }

    public function test_values_outside_the_allowed_range_are_refused(): void
    {
        $this->actingAs($this->createUserWithRole('admin'))
            ->post('/setups/settings/thresholds', $this->thresholds(['driver_span_hours' => 0, 'turnaround_minutes' => 'soon']))
            ->assertSessionHasErrors(['driver_span_hours', 'turnaround_minutes']);

        $this->assertSame(14, app(SettingsService::class)->getThreshold('driver_span_hours'));
    }

    public function test_only_an_admin_can_change_them(): void
    {
        $this->actingAs($this->createProviderUser('agency'))
            ->post('/setups/settings/thresholds', $this->thresholds())
            ->assertForbidden();
    }
}
