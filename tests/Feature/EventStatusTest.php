<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class EventStatusTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-26 12:00:00');

        foreach (['events.view', 'events.manage'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function event(string $start, string $end, bool $active = true): Event
    {
        return Event::create([
            'name' => "Event {$start}",
            'start_date' => $start,
            'end_date' => $end,
            'active_flag' => $active,
        ]);
    }

    public function test_status_follows_the_dates(): void
    {
        $this->assertSame('upcoming', $this->event('2026-09-27', '2026-10-05')->status);
        $this->assertSame('current', $this->event('2026-09-26', '2026-09-26')->status);
        $this->assertSame('current', $this->event('2026-09-01', '2026-09-30')->status);
        $this->assertSame('past', $this->event('2026-09-01', '2026-09-25')->status);
    }

    public function test_a_cancelled_event_is_cancelled_whatever_its_dates(): void
    {
        $this->assertSame('cancelled', $this->event('2026-09-01', '2026-09-30', active: false)->status);
    }

    public function test_the_mobile_picker_hides_cancelled_events_and_defaults_to_the_current_one(): void
    {
        $past = $this->event('2026-08-01', '2026-08-10');
        $current = $this->event('2026-09-20', '2026-10-02');
        $cancelled = $this->event('2026-09-25', '2026-09-28', active: false);

        Sanctum::actingAs($this->createUserWithRole('admin'));

        $response = $this->getJson('/api/mobile/events')->assertOk();

        $statuses = collect($response->json('data'))->pluck('status', 'id');
        $this->assertSame([$current->id => 'current', $past->id => 'past'], $statuses->all());
        $this->assertArrayNotHasKey($cancelled->id, $statuses->all());
        $response->assertJsonPath('default_id', $current->id);
    }

    public function test_the_web_switcher_hides_and_refuses_cancelled_events(): void
    {
        $open = $this->event('2026-09-20', '2026-10-02');
        $cancelled = $this->event('2026-09-25', '2026-09-28', active: false);
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)->get('/events')
            ->assertInertia(fn ($page) => $page->where('eventList', fn ($list) => collect($list)->pluck('id')->all() === [$open->id]));

        $this->actingAs($admin)->post('/session/active-event', ['event_id' => $cancelled->id])
            ->assertSessionHasErrors('event_id');

        // Switching events no longer flips anyone's active_flag.
        $this->actingAs($admin)->post('/session/active-event', ['event_id' => $open->id]);
        $this->assertTrue($cancelled->fresh()->active_flag === false && $open->fresh()->active_flag);
    }

    public function test_an_event_can_be_cancelled_and_brought_back(): void
    {
        $event = $this->event('2026-09-20', '2026-10-02');
        $admin = $this->createUserWithRole('admin');
        $payload = ['name' => $event->name, 'start_date' => '2026-09-20', 'end_date' => '2026-10-02'];

        $this->actingAs($admin)->put("/events/{$event->id}", $payload + ['active_flag' => '0'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $event->fresh()->status);

        $this->actingAs($admin)->put("/events/{$event->id}", $payload + ['active_flag' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('current', $event->fresh()->status);
    }

    public function test_start_and_end_dates_are_required(): void
    {
        $this->actingAs($this->createUserWithRole('admin'))
            ->post('/events', ['name' => 'No dates'])
            ->assertSessionHasErrors(['start_date', 'end_date']);
    }
}
