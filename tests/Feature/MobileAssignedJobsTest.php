<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class MobileAssignedJobsTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    /** A ground_control user as seeded: every functional area, but no jobs.view-unassigned. */
    private function fieldSupervisor(Event $event): User
    {
        foreach (['jobs.view', 'jobs.view-all-functional-areas'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'ground_control', 'guard_name' => 'web'])
            ->syncPermissions(['jobs.view', 'jobs.view-all-functional-areas']);

        $user = User::factory()->create();
        $user->assignRole('ground_control');
        $user->events()->attach($event->id);

        return $user;
    }

    public function test_a_field_supervisor_only_sees_and_opens_their_own_jobs(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $me = $this->fieldSupervisor($event);
        $colleague = $this->fieldSupervisor($event);

        $mine = $this->createJob($event, $this->createMovement($event, $plan, $team), $team, ['supervisor_id' => $me->id]);
        $theirs = $this->createJob($event, $this->createMovement($event, $plan, $team), $team, ['supervisor_id' => $colleague->id]);
        $unassigned = $this->createJob($event, $this->createMovement($event, $plan, $team), $team);

        Sanctum::actingAs($me);

        $ids = collect($this->getJson("/api/mobile/jobs?event_id={$event->id}")->assertOk()->json('data'))->pluck('id');
        $this->assertEquals([$mine->id], $ids->all());

        $this->getJson("/api/mobile/jobs?event_id={$event->id}")
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.status_label', 'Scheduled');

        $this->getJson("/api/mobile/jobs/{$mine->id}?event_id={$event->id}")->assertOk();
        $this->getJson("/api/mobile/jobs/{$theirs->id}?event_id={$event->id}")
            ->assertForbidden()
            ->assertJson(['message' => 'This job is assigned to another supervisor.']);
        $this->getJson("/api/mobile/jobs/{$unassigned->id}?event_id={$event->id}")->assertForbidden();

        $this->getJson("/api/mobile/profile?event_id={$event->id}")->assertOk()->assertJsonPath('stats.jobs', 1);
        $this->getJson('/api/mobile/events')->assertOk()->assertJsonPath('data.0.jobs', 1);
    }

    public function test_the_unassigned_grant_restores_the_full_event_view(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $desk = $this->fieldSupervisor($event);
        Permission::firstOrCreate(['name' => 'jobs.view-unassigned', 'guard_name' => 'web']);
        $desk->givePermissionTo('jobs.view-unassigned');

        $this->createJob($event, $this->createMovement($event, $plan, $team), $team, ['supervisor_id' => $this->fieldSupervisor($event)->id]);
        $this->createJob($event, $this->createMovement($event, $plan, $team), $team);

        Sanctum::actingAs($desk);

        $this->getJson("/api/mobile/jobs?event_id={$event->id}")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_match_job_carries_its_fixture(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $home = $this->createTeam($event);
        \App\Models\Country::create(['country_code' => 'XXX', 'country_name' => 'No known flag']);
        $home->forceFill(['code' => 'ALG-17', 'country_id' => 'XXX'])->save();
        $away = $this->createTeam($event);
        $me = $this->fieldSupervisor($event);
        $venue = \App\Models\Venue::create(['name' => 'Aspire Zone (Pitch 4)']);

        $match = \App\Models\GameMatch::create([
            'event_id' => $event->id, 'match_number' => 'FU17-004', 'stage' => 'Group J',
            'team1_id' => $home->id, 'team2_id' => $away->id, 'venue_id' => $venue->id,
            'match_date' => '2026-11-19', 'kick_off' => '2026-11-19 14:00:00',
        ]);
        $movement = $this->createMovement($event, $plan, $home, ['kind' => 'match', 'match_id' => $match->id]);
        $this->createJob($event, $movement, $home, ['supervisor_id' => $me->id]);
        $this->createJob($event, $this->createMovement($event, $plan, $home), $home, ['supervisor_id' => $me->id]);

        Sanctum::actingAs($me);

        $jobs = collect($this->getJson("/api/mobile/jobs?event_id={$event->id}")->assertOk()->json('data'));
        $fixture = $jobs->firstWhere('match')['match'];

        $this->assertSame('FU17-004', $fixture['number']);
        $this->assertSame('14:00', $fixture['kick_off_time']);
        $this->assertSame('Aspire Zone (Pitch 4)', $fixture['venue']);
        $this->assertSame('ALG-17', $fixture['team1']['code']);
        $this->assertSame('dz', $fixture['team1']['country_iso']);
        $this->assertSame($away->code, $fixture['team2']['code']);
        $this->assertCount(1, $jobs->whereNull('match'));
    }
}
