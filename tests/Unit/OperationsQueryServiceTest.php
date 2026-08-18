<?php

namespace Tests\Unit;

use App\Services\OperationsQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class OperationsQueryServiceTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_delayed_movements_are_scoped_to_the_active_event(): void
    {
        $event = $this->createEvent();
        $otherEvent = $this->createEvent();
        $plan = $this->createPlan($event);
        $otherPlan = $this->createPlan($otherEvent);
        $team = $this->createTeam($event);
        $otherTeam = $this->createTeam($otherEvent);

        $delayed = $this->createMovement($event, $plan, $team, ['delay_minutes' => 20]);
        $this->createJob($event, $delayed, $team);

        $delayedOtherEvent = $this->createMovement($otherEvent, $otherPlan, $otherTeam, ['delay_minutes' => 30]);
        $this->createJob($otherEvent, $delayedOtherEvent, $otherTeam);

        $user = $this->createUserWithRole('admin');

        $service = app(OperationsQueryService::class);
        $results = $service->getDelayedMovements($event->id, $user);

        $this->assertCount(1, $results);
        $this->assertSame(20, $results[0]['delay_minutes']);
    }

    public function test_delayed_movements_excludes_movements_with_no_delay(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);

        $onTime = $this->createMovement($event, $plan, $team, ['delay_minutes' => 0]);
        $this->createJob($event, $onTime, $team);

        $user = $this->createUserWithRole('admin');

        $results = app(OperationsQueryService::class)->getDelayedMovements($event->id, $user);

        $this->assertCount(0, $results);
    }

    public function test_functional_area_scoped_user_only_sees_their_area(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);

        $logMovement = $this->createMovement($event, $plan, $team, ['functional_area' => 'LOG', 'delay_minutes' => 10]);
        $this->createJob($event, $logMovement, $team, ['functional_area' => 'LOG']);

        $andMovement = $this->createMovement($event, $plan, $team, ['functional_area' => 'AND', 'delay_minutes' => 15]);
        $this->createJob($event, $andMovement, $team, ['functional_area' => 'AND']);

        $transportUser = $this->createUserWithRole('transport', 'LOG');

        $results = app(OperationsQueryService::class)->getDelayedMovements($event->id, $transportUser);

        $this->assertCount(1, $results);
        $this->assertSame('LOG', $results[0]['functional_area']);
    }

    public function test_ground_control_role_with_all_areas_permission_sees_everything(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);

        $logMovement = $this->createMovement($event, $plan, $team, ['functional_area' => 'LOG', 'delay_minutes' => 10]);
        $this->createJob($event, $logMovement, $team, ['functional_area' => 'LOG']);

        $andMovement = $this->createMovement($event, $plan, $team, ['functional_area' => 'AND', 'delay_minutes' => 15]);
        $this->createJob($event, $andMovement, $team, ['functional_area' => 'AND']);

        $groundControl = $this->createUserWithRole('ground_control');
        // ground_control gets the "-all-functional-areas" permission explicitly.
        $groundControl->givePermissionTo('jobs.view-all-functional-areas');

        $results = app(OperationsQueryService::class)->getDelayedMovements($event->id, $groundControl);

        $this->assertCount(2, $results);
    }

    public function test_movement_details_denies_access_outside_the_active_event_session(): void
    {
        $event = $this->createEvent();
        $otherEvent = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $plan, $team);
        $this->createJob($event, $movement, $team);

        $user = $this->createUserWithRole('admin');

        session(['active_event_id' => $otherEvent->id]);

        $details = app(OperationsQueryService::class)->getMovementDetails($movement->id, $user);

        $this->assertNull($details);
    }

    public function test_movement_details_returns_data_when_scoped_correctly(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $plan, $team, ['delay_minutes' => 5]);
        $this->createJob($event, $movement, $team);

        $user = $this->createUserWithRole('admin');
        session(['active_event_id' => $event->id]);

        $details = app(OperationsQueryService::class)->getMovementDetails($movement->id, $user);

        $this->assertNotNull($details);
        $this->assertSame(5, $details['delay_minutes']);
    }

    public function test_missing_updates_flags_an_active_job_with_an_overdue_checkpoint(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $plan, $team);
        $job = $this->createJob($event, $movement, $team, ['status' => 'in-progress']);

        $this->createCheckpoint($event, $job, [
            'name' => 'Bags loaded',
            'state' => 'pending',
            'scheduled_at' => now()->subMinutes(30),
        ]);

        $user = $this->createUserWithRole('admin');

        $results = app(OperationsQueryService::class)->getMissingUpdates($event->id, $user);

        $this->assertCount(1, $results);
        $this->assertSame('Bags loaded', $results[0]['overdue_checkpoint']);
        $this->assertGreaterThanOrEqual(29, $results[0]['overdue_minutes']);
    }

    public function test_missing_updates_ignores_checkpoints_not_yet_due(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $plan, $team);
        $job = $this->createJob($event, $movement, $team, ['status' => 'in-progress']);

        $this->createCheckpoint($event, $job, [
            'name' => 'Bags loaded',
            'state' => 'pending',
            'scheduled_at' => now()->addMinutes(30),
        ]);

        $user = $this->createUserWithRole('admin');

        $results = app(OperationsQueryService::class)->getMissingUpdates($event->id, $user);

        $this->assertCount(0, $results);
    }

    public function test_missing_updates_ignores_jobs_not_yet_dispatched(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $plan, $team);
        $job = $this->createJob($event, $movement, $team, ['status' => 'pending']);

        $this->createCheckpoint($event, $job, [
            'state' => 'pending',
            'scheduled_at' => now()->subMinutes(30),
        ]);

        $user = $this->createUserWithRole('admin');

        $results = app(OperationsQueryService::class)->getMissingUpdates($event->id, $user);

        $this->assertCount(0, $results);
    }

    public function test_explain_delay_denies_access_outside_the_active_event_session(): void
    {
        $event = $this->createEvent();
        $otherEvent = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $plan, $team);
        $job = $this->createJob($event, $movement, $team);

        $user = $this->createUserWithRole('admin');
        session(['active_event_id' => $otherEvent->id]);

        $result = app(OperationsQueryService::class)->explainDelay($job->id, $user);

        $this->assertNull($result);
    }

    public function test_explain_delay_identifies_the_largest_contributor_and_recovery(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $plan, $team, ['delay_minutes' => 12]);
        $job = $this->createJob($event, $movement, $team);

        $this->createCheckpoint($event, $job, [
            'order' => 1,
            'name' => 'Baggage loading',
            'state' => 'done',
            'scheduled_at' => now()->setTime(10, 0),
            'completed_at' => now()->setTime(10, 20),
        ]);
        $this->createCheckpoint($event, $job, [
            'order' => 2,
            'name' => 'Airport departure',
            'state' => 'done',
            'scheduled_at' => now()->setTime(11, 0),
            'completed_at' => now()->setTime(11, 5),
        ]);

        $user = $this->createUserWithRole('admin');
        session(['active_event_id' => $event->id]);

        $result = app(OperationsQueryService::class)->explainDelay($job->id, $user);

        $this->assertNotNull($result);
        $this->assertSame('Baggage loading', $result['largest_contributor']['name']);
        $this->assertSame(20, $result['largest_contributor']['variance_minutes']);
        $this->assertSame(12, $result['total_delay_minutes']);
        $this->assertSame(8, $result['recovered_minutes']);
    }

    public function test_checkpoint_performance_averages_by_name_and_is_scoped(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $plan, $team);
        $job = $this->createJob($event, $movement, $team);

        $this->createCheckpoint($event, $job, ['name' => 'Baggage loading', 'state' => 'done', 'delay_minutes' => 10]);
        $this->createCheckpoint($event, $job, ['name' => 'Baggage loading', 'state' => 'done', 'delay_minutes' => 20]);
        $this->createCheckpoint($event, $job, ['name' => 'Team boarded', 'state' => 'done', 'delay_minutes' => 0]);
        // Not 'done' — should be excluded from the average.
        $this->createCheckpoint($event, $job, ['name' => 'Baggage loading', 'state' => 'pending', 'delay_minutes' => null]);

        $user = $this->createUserWithRole('admin');

        $results = app(OperationsQueryService::class)->getCheckpointPerformance($event->id, $user);
        $byName = collect($results)->keyBy('name');

        $this->assertSame(15.0, $byName['Baggage loading']['avg_delay_minutes']);
        $this->assertSame(2, $byName['Baggage loading']['sample_count']);
        $this->assertSame(0.0, $byName['Team boarded']['avg_delay_minutes']);
    }
}
