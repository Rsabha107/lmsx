<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class DashboardClocksTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_admins_see_the_app_and_mysql_clocks(): void
    {
        $this->actingAs($this->createUserWithRole('admin'))
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('clocks.app.zone', 'Asia/Qatar')
                ->where('clocks.app.offset', 180)
                ->where('clocks.db.session_tz', '+00:00')
                // Pinned to +00:00, MySQL's NOW() on the app's connection is UTC.
                ->where('clocks.db.session_now', fn ($now) => $this->secondsFromUtcNow($now) <= 5)
                ->where('clocks.db.utc_now', fn ($now) => $this->secondsFromUtcNow($now) <= 5)
                ->where('clocks.app.utc', fn ($now) => $this->secondsFromUtcNow($now) <= 5)
                ->has('clocks.db.global_tz')
                ->has('clocks.db.server_now'));
    }

    public function test_other_console_users_do_not(): void
    {
        $user = $this->createUserWithRole('transport');
        $user->givePermissionTo('console.view');

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')->where('clocks', null));
    }

    private function secondsFromUtcNow(string $wallClock): int
    {
        return (int) abs(Carbon::parse($wallClock, 'UTC')->diffInSeconds(Carbon::now('UTC')));
    }
}
