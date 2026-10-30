<?php

namespace Tests\Feature;

use App\Ai\Agents\OperationsCopilotAgent;
use App\Ai\Tools\GetPlanReadinessTool;
use App\Ai\Tools\GetResourceBottlenecksTool;
use App\Ai\Tools\GetShiftBriefingTool;
use App\Models\CheckpointTemplate;
use App\Models\Event;
use App\Models\User;
use App\Services\Ai\OperationsAttention;
use App\Services\Ai\PlanReadiness;
use App\Services\Ai\ResourceBottlenecks;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Daleel's shift briefing, plan readiness, resource bottlenecks and links. */
class DaleelOperationsTest extends TestCase
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

    private function at(string $from, string $to, string $day = '2026-11-20'): array
    {
        return ['window_start' => "{$day} {$from}", 'window_end' => "{$day} {$to}"];
    }

    private function crew(): array
    {
        return [
            'vehicle_id' => $this->createVehicle($this->event)->id,
            'driver_id' => $this->createDriver($this->event)->id,
            'field_supervisor_id' => User::factory()->create()->id,
        ];
    }

    private function briefing(User $user, int $hours = 4): array
    {
        return app(OperationsAttention::class)->build($this->event->id, $user, now(), now()->addHours($hours));
    }

    public function test_the_briefing_groups_what_needs_attention_in_the_window_for_what_the_user_sees(): void
    {
        $plan = $this->createPlan($this->event);

        // Running job with a step that was due at 09:45 and has no update.
        $running = $this->createMovement($this->event, $plan, $this->createTeam($this->event), $this->crew() + $this->at('09:30', '11:30'));
        $runningJob = $this->createJob($this->event, $running, $running->team, ['status' => 'dispatched']);
        $this->createCheckpoint($this->event, $runningJob, ['name' => 'Hotel pickup', 'scheduled_at' => '2026-11-20 09:45:00']);
        // Late, fully crewed.
        $late = $this->createMovement($this->event, $plan, $this->createTeam($this->event), $this->crew() + ['delay_minutes' => 20] + $this->at('11:00', '12:00'));
        $lateJob = $this->createJob($this->event, $late, $late->team);
        // No crew and no job, soon.
        $bare = $this->createMovement($this->event, $plan, $this->createTeam($this->event), $this->at('12:00', '13:00'));
        // Outside the 4 hours, and another functional area.
        $this->createMovement($this->event, $plan, $this->createTeam($this->event), $this->at('18:00', '19:00'));
        $this->createMovement($this->event, $plan, $this->createTeam($this->event), ['functional_area' => 'MOB'] + $this->at('11:00', '12:00'));

        $log = $this->briefing($this->createUserWithRole('planner_log', 'LOG'));
        $byKey = collect($log['categories'])->keyBy('key');

        $this->assertSame(['overdue', 'delayed', 'crew', 'no_job'], $byKey->keys()->all());
        $this->assertSame([1, 1, 1, 1], $byKey->pluck('count')->all());
        $this->assertSame(4, $log['needs_attention']);
        $this->assertStringContainsString('"Hotel pickup" was due 09:45', $byKey['overdue']['items'][0]['reason']);
        $this->assertSame("/job/{$runningJob->id}", $byKey['overdue']['items'][0]['url']);
        $this->assertSame('no vehicle, driver, supervisor', $byKey['crew']['items'][0]['reason']);
        $this->assertSame("/plans?plan={$plan->id}&movement={$bare->id}", $byKey['no_job']['items'][0]['url']);

        // Every functional area: the MOB movement is missing crew too.
        $this->assertSame(2, collect($this->briefing($this->admin())['categories'])->firstWhere('key', 'crew')['count']);

        // Without "view unassigned", only the jobs the user supervises.
        Role::create(['name' => 'desk', 'guard_name' => 'web'])->givePermissionTo(['jobs.view', 'jobs.view-all-functional-areas']);
        $desk = User::factory()->create();
        $desk->assignRole('desk');
        $lateJob->update(['supervisor_id' => $desk->id]);
        $this->assertSame(['delayed'], collect($this->briefing($desk)['categories'])->pluck('key')->all());
    }

    public function test_the_briefing_lists_unresolved_high_conflicts(): void
    {
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $crew = $this->crew();
        $this->createJob($this->event, $this->createMovement($this->event, $plan, $team, $crew + $this->at('11:00', '12:00')), $team);
        $this->createJob($this->event, $this->createMovement($this->event, $plan, $team, $crew + $this->at('11:30', '12:30')), $team);

        $conflicts = collect($this->briefing($this->admin())['categories'])->firstWhere('key', 'conflicts');

        $this->assertNotNull($conflicts);
        $this->assertContains('Vehicle Double-Booked', collect($conflicts['items'])->pluck('type')->all());
        $this->assertSame('/plans?tab=conflicts', $conflicts['items'][0]['url']);
    }

    public function test_plan_readiness_says_what_stops_each_plan_and_the_next_step(): void
    {
        $template = CheckpointTemplate::create(['event_id' => $this->event->id, 'code' => 'CT-R', 'name' => 'Route', 'movement_type' => 'transfer']);
        $template->checkpoints()->attach(\App\Models\Checkpoint::create(['code' => 'CP-R1', 'name' => 'Depart', 'is_active' => true])->id, ['order' => 1]);
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $done = $this->createMovement($this->event, $plan, $team, $this->crew() + ['checkpoint_template_id' => $template->id] + $this->at('09:00', '10:00', '2026-11-21'));
        $this->createJob($this->event, $done, $team);
        $this->createMovement($this->event, $plan, $team, $this->crew() + ['checkpoint_template_id' => $template->id] + $this->at('11:00', '12:00', '2026-11-21'));
        $blocked = $this->createMovement($this->event, $plan, $team, $this->at('13:00', '14:00', '2026-11-21'));
        // A plan outside the range.
        $later = $this->createPlan($this->event);
        $later->update(['date' => '2026-12-05']);
        $this->createMovement($this->event, $later, $team, $this->at('09:00', '10:00', '2026-12-05'));

        $result = app(PlanReadiness::class)->build($this->event->id, $this->admin(), Carbon::parse('2026-11-20'), Carbon::parse('2026-11-27'));

        $this->assertSame(1, $result['plans']);
        $row = $result['items'][0];
        $this->assertSame([$plan->code, 'partly ready', 3, 1, 1, 1], [$row['code'], $row['status'], $row['movements'], $row['with_jobs'], $row['ready_for_jobs'], $row['blocked_movements']]);
        $this->assertSame('Assign crew (Crew Assignment)', $row['next_step']);
        $this->assertSame("/plans?plan={$plan->id}", $row['url']);
        $this->assertSame($blocked->code, $row['blockers'][0]['movement_code']);
        $this->assertContains('no vehicle', $row['blockers'][0]['problems']);
        $this->assertContains('no checkpoint template (the job would have no steps)', $row['blockers'][0]['problems']);

        // By plan code, whatever the dates.
        $one = app(PlanReadiness::class)->build($this->event->id, $this->admin(), null, null, $later->code);
        $this->assertSame([$later->code], array_column($one['items'], 'code'));

        // Users who can't see plans get nothing.
        $this->assertArrayHasKey('error', app(PlanReadiness::class)->build($this->event->id, $this->createUserWithRole('viewer_only'), null, null, $plan->code));
    }

    public function test_bottlenecks_count_demand_against_the_pool_per_day_with_clashes(): void
    {
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $busA = $this->createVehicle($this->event, ['code' => 'BUS-A']);
        $busB = $this->createVehicle($this->event, ['code' => 'BUS-B']);

        // Day 1: 08:00-10:00 on BUS-A plus a unit on BUS-B, 09:00-11:00 with no vehicle, 12:00 BUS-A again.
        $two = $this->createMovement($this->event, $plan, $team, ['vehicle_id' => $busA->id] + $this->at('08:00', '10:00', '2026-11-17'));
        $two->units()->create(['vehicle_id' => $busB->id, 'position' => 1]);
        $this->createMovement($this->event, $plan, $team, $this->at('09:00', '11:00', '2026-11-17'));
        $this->createMovement($this->event, $plan, $team, ['vehicle_id' => $busA->id] + $this->at('12:00', '13:00', '2026-11-17'));
        $this->createMovement($this->event, $plan, $team, ['status' => 'cancelled'] + $this->at('09:30', '10:30', '2026-11-17'));
        // Day 2: BUS-A booked twice at once.
        $this->createMovement($this->event, $plan, $team, ['vehicle_id' => $busA->id] + $this->at('09:00', '10:00', '2026-11-18'));
        $this->createMovement($this->event, $plan, $team, ['vehicle_id' => $busA->id] + $this->at('09:30', '10:30', '2026-11-18'));

        $result = app(ResourceBottlenecks::class)->build($this->event->id, $this->admin(), Carbon::parse('2026-11-17'), Carbon::parse('2026-11-19'), 'vehicle');

        $this->assertSame(['2026-11-17', '2026-11-18'], array_column($result['days'], 'date'));
        $this->assertSame(['vehicle' => 2], $result['pools']);
        $day1 = $result['days'][0]['vehicle'];
        $this->assertSame([4, 2, 1, 3, '09:00', 1], [$day1['required'], $day1['assigned'], $day1['movements_without'], $day1['peak_at_once'], $day1['peak_time'], $day1['short_at_peak']]);
        $this->assertSame('09:00–11:00', $day1['without'][0]['window']);
        $this->assertSame(0, $day1['clashes']);
        $this->assertGreaterThanOrEqual(1, $result['days'][1]['vehicle']['clashes']);
        $this->assertArrayNotHasKey('driver', $result['days'][0]);
    }

    public function test_a_plan_link_opens_that_plan_of_the_active_event_only(): void
    {
        $plan = $this->createPlan($this->event);
        $foreign = $this->createPlan($this->createEvent());
        $admin = $this->admin();
        $as = $this->actingAs($admin)->withSession(['active_event_id' => $this->event->id]);

        $as->get("/plans?plan={$foreign->id}")->assertInertia(fn (AssertableInertia $page) => $page->where('activePlan', null));
        $as->get("/plans?plan={$plan->id}")->assertInertia(fn (AssertableInertia $page) => $page->where('activePlan', $plan->id));
    }

    public function test_daleel_has_the_new_tools(): void
    {
        $classes = collect((new OperationsCopilotAgent)->tools())->map(fn ($t) => $t::class)->all();

        foreach ([GetShiftBriefingTool::class, GetPlanReadinessTool::class, GetResourceBottlenecksTool::class, \App\Ai\Tools\GetCrewOptionsTool::class, \App\Ai\Tools\GetDataQualityTool::class] as $tool) {
            $this->assertContains($tool, $classes);
        }
    }

    public function test_page_context_reaches_daleel_only_for_records_the_user_can_see(): void
    {
        OperationsCopilotAgent::fake(['ok']);
        $plan = $this->createPlan($this->event);
        $other = $this->createEvent();
        $team = $this->createTeam($other);
        $foreignJob = $this->createJob($other, $this->createMovement($other, $this->createPlan($other), $team), $team, ['job_id' => 'JOB-FOREIGN']);
        $as = $this->actingAs($this->admin())->withSession(['active_event_id' => $this->event->id]);

        $as->postJson('/ai/query', [
            'question' => 'Why can\'t this plan generate jobs?',
            'context' => ['page' => 'planning', 'plan_id' => $plan->id, 'job_id' => $foreignJob->id, 'date' => '2026-11-20'],
        ])->assertOk()->assertJson(['ok' => true]);

        OperationsCopilotAgent::assertPrompted(fn ($prompt) => str_contains($prompt->agent->instructions(), "Planning page, date 2026-11-20, plan {$plan->code}")
            && ! str_contains($prompt->agent->instructions(), 'JOB-FOREIGN'));

        $as->postJson('/ai/query', ['question' => 'x', 'context' => ['page' => 'settings']])->assertUnprocessable();
    }

    public function test_the_next_step_is_overdue_at_risk_or_on_track_with_its_basis(): void
    {
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $job = $this->createJob($this->event, $this->createMovement($this->event, $plan, $team, $this->at('09:00', '12:00')), $team, ['status' => 'in-progress']);
        $done = $this->createCheckpoint($this->event, $job, ['order' => 1, 'name' => 'Pickup', 'scheduled_at' => '2026-11-20 09:00:00']);
        $done->forceFill(['state' => 'done', 'completed_at' => '2026-11-20 09:20:00', 'delay_minutes' => 20])->save();
        $next = $this->createCheckpoint($this->event, $job, ['order' => 2, 'name' => 'Arrive stadium', 'scheduled_at' => '2026-11-20 10:30:00']);
        $explain = fn () => app(\App\Services\OperationsQueryService::class)->explainDelay($job->id, $this->admin())['next_checkpoint'];

        // 20 min late at the last step, due in 30 min: at risk, expected 10:50.
        $this->assertSame(['Arrive stadium', 'at risk', 30, '10:50'], [$explain()['name'], $explain()['state'], $explain()['due_in_minutes'], $explain()['expected_at']]);
        $this->assertStringContainsString('"Pickup", was 20 min late', $explain()['basis']);

        Carbon::setTestNow('2026-11-20 10:45:00');
        $this->assertSame(['overdue', -15], [$explain()['state'], $explain()['due_in_minutes']]);

        $done->forceFill(['delay_minutes' => 0, 'completed_at' => '2026-11-20 09:00:00'])->save();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->assertSame('on track', $explain()['state']);
    }

    public function test_crew_options_say_why_a_resource_is_unavailable(): void
    {
        $plan = $this->createPlan($this->event);
        $bus = $this->createVehicle($this->event, ['code' => 'BUS-1']);
        $this->createVehicle($this->event, ['code' => 'BUS-2']);
        $this->createMovement($this->event, $plan, $this->createTeam($this->event), ['vehicle_id' => $bus->id] + $this->at('09:00', '11:00'));
        $target = $this->createMovement($this->event, $plan, $this->createTeam($this->event), $this->at('10:00', '12:00'));
        $this->actingAs($this->admin());

        $result = json_decode(app(\App\Ai\Tools\GetCrewOptionsTool::class)->handle(new \Laravel\Ai\Tools\Request(['reference' => $target->code, 'role' => 'vehicle'])), true);

        $this->assertSame($target->code, $result['movement_code']);
        $this->assertSame(['BUS-2'], $result['vehicle']['free_names']);
        $this->assertSame('BUS-1', $result['vehicle']['unavailable_with_reason'][0]['name']);
        $this->assertStringStartsWith('Busy on', $result['vehicle']['unavailable_with_reason'][0]['reason']);
        $this->assertArrayNotHasKey('driver', $result);

        // A movement of another event is not found.
        $other = $this->createEvent();
        $foreign = $this->createMovement($other, $this->createPlan($other), $this->createTeam($other));
        $this->assertArrayHasKey('error', json_decode(app(\App\Ai\Tools\GetCrewOptionsTool::class)->handle(new \Laravel\Ai\Tools\Request(['reference' => $foreign->code])), true));
    }

    public function test_bottlenecks_break_down_by_provider_and_functional_area_and_readiness_flags_empty_templates(): void
    {
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $other = $this->createProvider('Other Co');
        $this->createMovement($this->event, $plan, $team, $this->at('09:00', '10:00', '2026-11-21'));
        $this->createMovement($this->event, $plan, $team, ['fleet_provider_id' => $other->id, 'functional_area' => 'MOB'] + $this->at('09:30', '10:30', '2026-11-21'));
        $this->createVehicle($this->event);

        $result = app(ResourceBottlenecks::class)->build($this->event->id, $this->admin(), Carbon::parse('2026-11-21'), Carbon::parse('2026-11-22'), 'vehicle');
        $byProvider = collect($result['by_provider'])->keyBy('name');
        $this->assertSame([1, 1], [$byProvider['GWC']['vehicle']['worst_day_peak'], $byProvider['GWC']['vehicle']['pool']]);
        $this->assertSame([1, 0], [$byProvider['Other Co']['vehicle']['movements_without'], $byProvider['Other Co']['vehicle']['short_on_worst_day'] ?? 0]);
        $this->assertSame(['LOG', 'MOB'], collect($result['by_functional_area'])->pluck('name')->sort()->values()->all());

        $empty = CheckpointTemplate::create(['event_id' => $this->event->id, 'code' => 'CT-EMPTY', 'name' => 'Empty', 'movement_type' => 'transfer']);
        $this->createMovement($this->event, $plan, $team, ['checkpoint_template_id' => $empty->id] + $this->at('11:00', '12:00', '2026-11-21'));
        $readiness = app(PlanReadiness::class)->build($this->event->id, $this->admin(), null, null, $plan->code);
        $problems = collect($readiness['items'][0]['blockers'])->flatMap(fn ($b) => $b['problems'])->all();
        $this->assertContains('checkpoint template CT-EMPTY has no steps', $problems);
    }
}
