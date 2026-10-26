<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** SEC-001: the desktop Jobs Queue lists only the jobs the user may see, in an event they may open. */
class JobsQueueScopeTest extends TestCase
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

    private function job(Event $event, string $jobId, string $area = 'LOG', ?User $supervisor = null): void
    {
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team, ['functional_area' => $area]);
        $this->createJob($event, $movement, $team, ['job_id' => $jobId, 'functional_area' => $area, 'supervisor_id' => $supervisor?->id]);
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

    /** @return list<string> */
    private function queue(User $user, ?Event $active): array
    {
        $ids = [];
        $this->actingAs($user)->withSession(['active_event_id' => $active?->id])
            ->get('/jobs')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$ids) {
                $ids = collect($page->toArray()['props']['schedule'])->pluck('jobId')->sort()->values()->all();
            });

        return $ids;
    }

    public function test_a_scoped_user_sees_only_their_functional_area_in_the_active_event(): void
    {
        $this->job($this->event, 'JOB-LOG');
        $this->job($this->event, 'JOB-MOB', 'MOB');
        $this->job($this->other, 'JOB-OTHER');
        $transport = $this->user('transport', ['LOG'], [$this->event, $this->other]);

        $this->assertSame(['JOB-LOG'], $this->queue($transport, $this->event));
    }

    public function test_without_an_active_event_the_queue_is_empty(): void
    {
        $this->job($this->event, 'JOB-LOG');
        $this->job($this->other, 'JOB-OTHER');

        $this->assertSame([], $this->queue($this->user('admin'), null));
    }

    public function test_an_event_the_user_lost_access_to_is_dropped(): void
    {
        $this->job($this->event, 'JOB-LOG');
        $transport = $this->user('transport', ['LOG'], [$this->other]);

        $this->actingAs($transport)->withSession(['active_event_id' => $this->event->id])
            ->get('/jobs')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('schedule', []));
    }

    public function test_without_jobs_view_unassigned_only_own_jobs_are_listed(): void
    {
        Role::create(['name' => 'desk_supervisor', 'guard_name' => 'web'])
            ->givePermissionTo(['console.view', 'jobs.view', 'jobs.view-all-functional-areas']);
        $me = $this->user('desk_supervisor', [], [$this->event]);
        $this->job($this->event, 'JOB-MINE', 'LOG', $me);
        $this->job($this->event, 'JOB-NOT-MINE', 'LOG', $this->user('ground_control'));

        $this->assertSame(['JOB-MINE'], $this->queue($me, $this->event));
    }

    public function test_an_admin_sees_every_job_of_the_active_event_only(): void
    {
        $this->job($this->event, 'JOB-LOG');
        $this->job($this->event, 'JOB-MOB', 'MOB');
        $this->job($this->other, 'JOB-OTHER');

        $this->assertSame(['JOB-LOG', 'JOB-MOB'], $this->queue($this->user('admin'), $this->event));
    }

    public function test_the_agency_cannot_open_the_jobs_queue(): void
    {
        $agency = $this->createProviderUser('agency');

        $this->actingAs($agency)->withSession(['active_event_id' => $this->event->id])->get('/jobs')->assertForbidden();
    }
}
