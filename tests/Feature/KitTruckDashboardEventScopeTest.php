<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class KitTruckDashboardEventScopeTest extends TestCase
{
    use CreatesOperationsFixtures;
    use RefreshDatabase;

    public function test_it_shows_the_active_event_unless_all_events_are_asked_for(): void
    {
        $active = $this->createEvent();
        $other = $this->createEvent();
        foreach ([$active, $other] as $event) {
            $team = $this->createTeam($event);
            $movement = $this->createMovement($event, $this->createPlan($event), $team, ['kind' => 'arrival']);
            $this->createJob($event, $movement, $team);
        }

        $admin = $this->createUserWithRole('admin');
        $session = ['active_event_id' => $active->id];

        $this->actingAs($admin)->withSession($session)->get('/kit-truck?kind=arrival')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 1)
                ->where('filters.event', $active->id));

        $this->actingAs($admin)->withSession($session)->get('/kit-truck?kind=arrival&event=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 2)
                ->where('filters.event', ''));

        $this->actingAs($admin)->withSession($session)->get("/kit-truck?kind=arrival&event={$other->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 1)
                ->where('filters.event', $other->id));
    }
}
