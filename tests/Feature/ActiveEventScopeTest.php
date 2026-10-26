<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\GameMatch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Pages beyond the Jobs Queue (SEC-001 follow-up): no other event's data, and no stale active event. */
class ActiveEventScopeTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    private Event $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->event = $this->createEvent();
        $this->other = $this->createEvent();
    }

    private function user(string $role, array $areas = [], array $events = []): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        foreach ($areas as $area) {
            $user->functionalAreas()->create(['functional_area' => $area]);
        }
        $user->events()->attach(array_map(fn (Event $e) => $e->id, $events));

        return $user;
    }

    private function job(Event $event, string $area = 'LOG', array $movement = []): int
    {
        $team = $this->createTeam($event);
        $m = $this->createMovement($event, $this->createPlan($event), $team, ['functional_area' => $area] + $movement);

        return $this->createJob($event, $m, $team, ['functional_area' => $area])->id;
    }

    public function test_an_active_event_the_user_lost_access_to_is_dropped(): void
    {
        $jobId = $this->job($this->event);
        $transport = $this->user('transport', ['LOG'], [$this->other]);

        $this->actingAs($transport)->withSession(['active_event_id' => $this->event->id])
            ->get('/day-board')
            ->assertSessionMissing('active_event_id')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('activeEventId', null)->where('jobs', []));

        $this->actingAs($transport)->withSession(['active_event_id' => $this->event->id])
            ->get("/job/{$jobId}")
            ->assertForbidden();
    }

    public function test_movement_tracking_shows_only_accessible_events_and_own_functional_areas(): void
    {
        $mine = $this->job($this->event);
        $this->job($this->event, 'MOB');
        $foreign = $this->job($this->other);
        $transport = $this->user('transport', ['LOG'], [$this->event]);

        $rows = function (array $query) use ($transport) {
            $ids = [];
            $this->actingAs($transport)->get('/kit-truck?'.http_build_query(['kind' => 'transfer'] + $query))
                ->assertOk()
                ->assertInertia(function (AssertableInertia $page) use (&$ids) {
                    $props = $page->toArray()['props'];
                    $ids = collect($props['rows'])->pluck('job_id')->all();
                    $this->assertSame([$this->event->id], collect($props['events'])->pluck('id')->all());
                    $this->assertSame(['LOG'], collect($props['functionalAreas'])->pluck('value')->all());
                });

            return $ids;
        };

        $this->assertSame([$mine], $rows(['event' => '']));
        $this->assertSame([], $rows(['event' => $this->other->id]));
        $this->assertNotContains($foreign, $rows(['event' => '', 'functional_area' => 'LOG']));
    }

    public function test_matches_without_an_active_event_lists_nothing_and_only_accessible_events(): void
    {
        GameMatch::create(['event_id' => $this->other->id, 'match_number' => 'M-OTHER', 'team1_id' => $this->createTeam($this->other)->id, 'kick_off' => '2026-11-19 18:00']);
        $transport = $this->user('transport', ['LOG'], [$this->event]);

        $this->actingAs($transport)->get('/matches')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('matches', [])
                ->where('events', fn ($events) => collect($events)->pluck('id')->all() === [$this->event->id]));
    }

    public function test_notifications_without_an_active_event_cover_every_event_only_for_admins(): void
    {
        $team = $this->createTeam($this->other);
        $this->createMovement($this->other, $this->createPlan($this->other), $team, ['delay_minutes' => 20]);

        $count = function (User $user) {
            $n = null;
            $this->actingAs($user)->get('/notifications')
                ->assertInertia(function (AssertableInertia $page) use (&$n) {
                    $n = count($page->toArray()['props']['notifications']);
                });

            return $n;
        };

        $this->assertSame(0, $count($this->user('transport', ['LOG'], [$this->event])));
        $this->assertSame(1, $count($this->user('admin')));
    }

    public function test_library_templates_need_an_active_event(): void
    {
        \App\Models\MovementTemplate::create(['event_id' => $this->other->id, 'code' => 'MVT-O', 'name' => 'Other event']);
        \App\Models\CheckpointTemplate::create(['event_id' => $this->other->id, 'code' => 'CT-O', 'name' => 'Other event', 'movement_type' => 'arrival']);

        $this->actingAs($this->user('admin'))->get('/library')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('checkpointTemplates', [])->where('movementTemplates', []));
    }
}
