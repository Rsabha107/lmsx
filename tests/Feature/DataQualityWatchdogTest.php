<?php

namespace Tests\Feature;

use App\Models\CheckpointTemplate;
use App\Models\Event;
use App\Models\Setting;
use App\Models\TeamFlight;
use App\Models\User;
use App\Services\Ai\DataQualityWatchdog;
use App\Services\Ai\OperationsAttention;
use App\Services\SettingsService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Daleel's data-quality watchdog: each check at its limits (Settings → Conflict rules). */
class DataQualityWatchdogTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->event = $this->createEvent();
        session(['active_event_id' => $this->event->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function check(): array
    {
        return app(DataQualityWatchdog::class)->build($this->event->id, $this->admin());
    }

    private function category(array $result, string $key): array
    {
        return collect($result['categories'])->firstWhere('key', $key) ?? ['count' => 0, 'items' => []];
    }

    private function flight(string $number, string $scheduled, ?string $synced, array $extra = []): TeamFlight
    {
        return TeamFlight::forceCreate($extra + [
            'event_id' => $this->event->id,
            'team_id' => $this->createTeam($this->event)->id,
            'direction' => 'arrival',
            'flight_number' => $number,
            'scheduled_at' => $scheduled,
            'flight_synced_at' => $synced,
        ]);
    }

    private function at(string $start, string $end, array $extra = []): array
    {
        return $extra + ['window_start' => "2026-11-20 {$start}", 'window_end' => "2026-11-20 {$end}"];
    }

    public function test_stale_and_unconfirmed_flights_follow_the_sync_schedule(): void
    {
        $this->flight('QR1', '2026-11-20 11:30', '2026-11-20 08:30');  // within 3 h, 90 min old → high
        $this->flight('QR2', '2026-11-20 11:00', '2026-11-20 09:45');  // within 3 h, 15 min old → fine
        $this->flight('QR3', '2026-11-20 17:00', '2026-11-20 02:00');  // 7 h out, 8 h old → medium
        $this->flight('QR4', '2026-11-20 18:00', null);                 // 8 h out, never → high
        $this->flight('QR5', '2026-11-21 16:00', null);                 // 30 h out, never → low
        $this->flight('QR6', '2026-11-20 11:00', null, ['flight_status' => 'landed']);  // landed → fine
        $this->flight('BUS', '2026-11-20 11:00', null);                 // road leg → fine
        $this->flight('QR7', '2026-11-20 07:30', '2026-11-20 09:40');  // 2.5 h ago, refreshed, but nothing recorded → unconfirmed

        $result = $this->check();
        $stale = collect($this->category($result, 'stale_flights')['items'])->pluck('severity', 'flight')->all();
        $this->assertSame(['QR1' => 'high', 'QR4' => 'high', 'QR3' => 'medium', 'QR5' => 'low'], $stale);
        $this->assertStringContainsString('last refreshed 90 min ago (limit 60 min)', collect($this->category($result, 'stale_flights')['items'])->firstWhere('flight', 'QR1')['fact']);
        $this->assertSame(['QR7'], collect($this->category($result, 'unconfirmed_flights')['items'])->pluck('flight')->all());
        $this->assertSame('/event-teams', $this->category($result, 'stale_flights')['items'][0]['url']);
    }

    public function test_no_job_uses_the_warn_and_urgent_limits_from_settings(): void
    {
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $soon = $this->createMovement($this->event, $plan, $team, $this->at('13:00', '14:00'));
        $this->createMovement($this->event, $plan, $team, ['window_start' => '2026-11-21 16:00', 'window_end' => '2026-11-21 17:00']);
        $this->createMovement($this->event, $plan, $team, ['window_start' => '2026-11-23 09:00', 'window_end' => '2026-11-23 10:00']);

        $items = collect($this->category($this->check(), 'no_job')['items']);
        $this->assertSame(['high', 'medium'], $items->pluck('severity')->all());
        $this->assertSame("/plans?plan={$plan->id}&movement={$soon->id}", $items[0]['url']);

        // Tighter urgent limit: 3 h away is no longer urgent; the briefing follows the same rule.
        app(SettingsService::class)->setSetting('conflict.no_job_urgent_hours', 2, Setting::SCOPE_GLOBAL);
        $this->assertSame(['medium', 'medium'], collect($this->category($this->check(), 'no_job')['items'])->pluck('severity')->all());
        $briefing = app(OperationsAttention::class)->build($this->event->id, $this->admin(), now(), now()->addHours(4));
        $this->assertSame('medium', collect($briefing['categories'])->firstWhere('key', 'no_job')['items'][0]['severity']);
    }

    public function test_crew_that_no_longer_fits_is_flagged_with_the_reason(): void
    {
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $other = $this->createProvider('Other Co');
        $foreignBus = $this->createVehicle($this->event, ['code' => 'BUS-X', 'provider_id' => $other->id]);
        $offDriver = $this->createDriver($this->event, ['name' => 'Sam Off', 'status' => 'off']);
        $gone = User::factory()->create(['name' => 'Ex Supervisor', 'is_active' => false]);
        $gone->assignRole('ground_control');
        $this->createMovement($this->event, $plan, $team, $this->at('12:00', '13:00', [
            'vehicle_id' => $foreignBus->id, 'driver_id' => $offDriver->id, 'field_supervisor_id' => $gone->id,
        ]));
        $fine = User::factory()->create();
        $fine->assignRole('ground_control');
        $this->createMovement($this->event, $plan, $team, $this->at('15:00', '16:00', [
            'vehicle_id' => $this->createVehicle($this->event)->id, 'driver_id' => $this->createDriver($this->event)->id, 'field_supervisor_id' => $fine->id,
        ]));

        $items = $this->category($this->check(), 'invalid_crew')['items'];
        $this->assertCount(1, $items);
        $this->assertStringContainsString('BUS-X belongs to another provider', $items[0]['fact']);
        $this->assertStringContainsString('Sam Off is marked off', $items[0]['fact']);
        $this->assertStringContainsString('Ex Supervisor is deactivated', $items[0]['fact']);
    }

    public function test_missing_checkpoints_incomplete_routes_and_duplicates(): void
    {
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $empty = CheckpointTemplate::create(['event_id' => $this->event->id, 'code' => 'CT-E', 'name' => 'Empty', 'movement_type' => 'transfer']);
        $this->createMovement($this->event, $plan, $team, $this->at('12:00', '13:00', ['checkpoint_template_id' => $empty->id]));
        $withJob = $this->createMovement($this->event, $plan, $this->createTeam($this->event), $this->at('14:00', '15:00'));
        $this->createJob($this->event, $withJob, $withJob->team);
        $this->createMovement($this->event, $plan, $this->createTeam($this->event), $this->at('16:00', '17:00', ['to_location' => '']));
        // Same team, kind and route, 20 minutes apart.
        $dupTeam = $this->createTeam($this->event);
        $a = $this->createMovement($this->event, $plan, $dupTeam, $this->at('18:00', '19:00'));
        $b = $this->createMovement($this->event, $plan, $dupTeam, $this->at('18:20', '19:20'));

        $result = $this->check();
        $missing = collect($this->category($result, 'missing_checkpoints')['items']);
        $this->assertTrue($missing->contains(fn ($i) => str_contains($i['fact'], 'CT-E has no steps')));
        $this->assertTrue($missing->contains(fn ($i) => $i['severity'] === 'high' && str_contains($i['fact'], 'Its job has no checkpoints')));
        $this->assertStringContainsString('Missing to.', $this->category($result, 'incomplete_routes')['items'][0]['fact']);
        $dup = $this->category($result, 'duplicates')['items'];
        $this->assertCount(1, $dup);
        $this->assertSame([$a->code, $b->code], [$dup[0]['movement_code'], $dup[0]['other_movement_code']]);
        $this->assertStringContainsString('20 min apart', $dup[0]['fact']);
        $this->assertGreaterThan(0, $result['high']);
    }

    public function test_users_who_cannot_view_movements_get_nothing(): void
    {
        $viewer = $this->createUserWithRole('viewer');
        $viewer->syncPermissions([]);
        $viewer->syncRoles([]);

        $this->assertArrayHasKey('error', app(DataQualityWatchdog::class)->build($this->event->id, $viewer));
    }
}
