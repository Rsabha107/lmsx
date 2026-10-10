<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class CheckpointCompleteTimeTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_completing_with_a_time_and_reason_stores_it_on_the_planned_day(): void
    {
        $scheduled = now()->addDays(5)->setTime(10, 0);
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team, [
            'window_start' => $scheduled->copy()->subHour(),
            'window_end' => $scheduled->copy()->addHour(),
        ]);
        $job = $this->createJob($event, $movement, $team);
        $checkpoint = $this->createCheckpoint($event, $job, ['scheduled_at' => $scheduled]);

        $user = $this->createUserWithRole('admin');

        $this->actingAs($user)->withSession(['active_event_id' => $event->id])
            ->postJson("/jobs/checkpoint/{$checkpoint->id}/complete", [
                'actual_time' => '10:25',
                'exclude_date' => true,
                'notes' => 'Bus held at the gate',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $checkpoint->refresh();
        $this->assertSame('done', $checkpoint->state);
        $this->assertSame($scheduled->copy()->setTime(10, 25)->format('Y-m-d H:i'), $checkpoint->completed_at->format('Y-m-d H:i'));
        $this->assertSame('Bus held at the gate', $checkpoint->notes);
    }
}
