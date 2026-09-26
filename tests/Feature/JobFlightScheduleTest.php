<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\TeamFlight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class JobFlightScheduleTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_arrival_jobs_carry_the_flights_scheduled_time_on_web_and_mobile(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $bru = Airport::create(['code' => 'BRU', 'name' => 'Brussels']);
        $doh = Airport::create(['code' => 'DOH', 'name' => 'Hamad International']);

        $flight = TeamFlight::create([
            'event_id' => $event->id, 'team_id' => $team->id, 'direction' => 'arrival',
            'flight_number' => 'QR 194', 'origin_airport_id' => $bru->id, 'destination_airport_id' => $doh->id,
            'scheduled_at' => '2026-11-16 23:05:00', 'estimated_at' => '2026-11-16 23:20:00', 'delay_minutes' => 15,
        ]);

        $admin = $this->createUserWithRole('admin');
        $arrival = $this->createJob($event, $this->createMovement($event, $plan, $team, [
            'kind' => 'arrival', 'flight_id' => $flight->id,
            'window_start' => '2026-11-16 23:35:00', 'window_end' => '2026-11-17 00:20:00',
        ]), $team, ['supervisor_id' => $admin->id]);
        $transfer = $this->createJob($event, $this->createMovement($event, $plan, $team), $team, ['supervisor_id' => $admin->id]);

        // Mobile API
        $admin->events()->attach($event->id);
        Sanctum::actingAs($admin);
        $jobs = collect($this->getJson("/api/mobile/jobs?event_id={$event->id}")->assertOk()->json('data'))->keyBy('id');

        $this->assertSame([
            'direction' => 'arrival', 'flight_number' => 'QR 194', 'origin_airport' => 'BRU', 'destination_airport' => 'DOH',
            'scheduled_time' => '23:05', 'scheduled_date' => 'Mon 16 Nov', 'estimated_time' => '23:20', 'delay_minutes' => 15,
        ], array_intersect_key($jobs[$arrival->id]['flight'], array_flip([
            'direction', 'flight_number', 'origin_airport', 'destination_airport',
            'scheduled_time', 'scheduled_date', 'estimated_time', 'delay_minutes',
        ])));
        $this->assertNull($jobs[$transfer->id]['flight']);
        $this->assertSame('23:35', $jobs[$arrival->id]['pickup_time']);
        $this->assertSame('Mon 16 Nov', $jobs[$arrival->id]['pickup_date']);
        $this->assertStringStartsWith('2026-11-16T23:35:00', $jobs[$arrival->id]['pickup_at']);
        $this->assertArrayNotHasKey('dropoff_time', $jobs[$arrival->id]);

        // Once a job has checkpoints, the pickup is the first checkpoint's time.
        $this->createCheckpoint($event, $arrival, ['order' => 2, 'name' => 'Bags loaded', 'scheduled_at' => '2026-11-16 23:50:00']);
        $this->createCheckpoint($event, $arrival, ['order' => 1, 'name' => 'Meet at arrivals', 'scheduled_at' => '2026-11-16 23:40:00']);
        $pickup = collect($this->getJson("/api/mobile/jobs?event_id={$event->id}")->json('data'))->firstWhere('id', $arrival->id);
        $this->assertSame('23:40', $pickup['pickup_time']);
        $this->assertSame('Meet at arrivals', $pickup['pickup_checkpoint']);

        // Web Jobs Queue
        $this->actingAs($admin)->withSession(['active_event_id' => $event->id])
            ->get('/jobs')
            ->assertInertia(function ($page) use ($arrival) {
                $row = collect($page->toArray()['props']['schedule'])->firstWhere('jobId', $arrival->job_id);
                $this->assertSame('23:05', $row['flight']['scheduled_time']);
                $this->assertSame('BRU', $row['flight']['origin_airport']);
                // The first checkpoint's time, not the flight's.
                $this->assertSame('23:40', $row['pickup']);
                $this->assertSame('Meet at arrivals', $row['pickup_checkpoint']);
            });
    }
}
