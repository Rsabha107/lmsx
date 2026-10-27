<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\JobOperation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Jobs Queue and Day Board can show other accessible events beside the active one. */
class MultiEventQueueTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function jobIn(Event $event): JobOperation
    {
        $team = $this->createTeam($event);

        return $this->createJob($event, $this->createMovement($event, $this->createPlan($event), $team, ['window_start' => '2026-11-24 08:00']), $team);
    }

    private function ids($list, string $key = 'id'): array
    {
        return collect($list)->pluck($key)->sort()->values()->all();
    }

    public function test_the_jobs_queue_shows_picked_accessible_events_only(): void
    {
        [$a, $b, $c] = [$this->createEvent(), $this->createEvent(), $this->createEvent()];
        [$ja, $jb] = [$this->jobIn($a), $this->jobIn($b)];
        $this->jobIn($c);
        $user = User::factory()->create();
        $user->assignRole('transport');
        $user->functionalAreas()->create(['functional_area' => 'LOG']);
        $user->events()->attach([$a->id, $b->id]);

        $as = $this->actingAs($user)->withSession(['active_event_id' => $a->id]);
        $as->get("/jobs?events[]={$b->id}&events[]={$c->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('eventIds', [$a->id, $b->id])
                ->where('schedule', fn ($list) => $this->ids($list, 'db_id') === [$ja->id, $jb->id]));

        // A job of the other event opens; one of an event the user can't open does not.
        $as->get("/job/{$jb->id}")->assertOk();

        // Cleared: back to the active event only.
        $as->get('/jobs?events=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('eventIds', [$a->id])
                ->where('schedule', fn ($list) => $this->ids($list, 'db_id') === [$ja->id]));
    }

    public function test_the_day_board_shows_picked_events_with_an_event_tag(): void
    {
        [$a, $b, $c] = [$this->createEvent(), $this->createEvent(), $this->createEvent()];
        [$ja, $jb] = [$this->jobIn($a), $this->jobIn($b)];
        $this->jobIn($c);
        $agency = $this->createProviderUser('agency');
        $agency->events()->attach([$a->id, $b->id]);

        $this->actingAs($agency)->withSession(['active_event_id' => $a->id])
            ->get("/day-board?date=2026-11-24&events[]={$b->id}&events[]={$c->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('eventIds', [$a->id, $b->id])
                ->where('jobs', fn ($list) => $this->ids($list) === [$ja->id, $jb->id]
                    && collect($list)->firstWhere('id', $jb->id)['event'] === ($b->short_name ?: $b->name))
                ->where('dayCounts', ['2026-11-24' => 2]));
    }
}
