<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\TeamFlight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class AirportCrudTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_the_list_shows_how_often_each_airport_is_used(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $doh = Airport::create(['code' => 'DOH', 'name' => 'Hamad International']);
        Airport::create(['code' => 'BRU', 'name' => 'Brussels']);
        TeamFlight::create([
            'event_id' => $event->id, 'team_id' => $team->id, 'direction' => 'arrival',
            'destination_airport_id' => $doh->id,
        ]);

        $this->actingAs($this->createUserWithRole('admin'))->get('/airports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Airports')
                ->where('airports.0.code', 'BRU')
                ->where('airports.0.flights_count', 0)
                ->where('airports.1.code', 'DOH')
                ->where('airports.1.flights_count', 1));
    }

    public function test_an_airport_can_be_added_edited_and_deleted(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->post('/airports', ['code' => ' doh ', 'name' => 'Hamad International', 'city' => 'Doha', 'country' => 'Qatar'])
            ->assertRedirect('/airports');

        $airport = Airport::where('code', 'DOH')->firstOrFail();
        $this->assertSame('Doha', $airport->city);

        $this->actingAs($admin)
            ->put("/airports/{$airport->id}", ['code' => 'DOH', 'name' => 'Hamad International Airport'])
            ->assertRedirect('/airports');
        $this->assertSame('Hamad International Airport', $airport->fresh()->name);
        $this->assertNull($airport->fresh()->city);

        $this->actingAs($admin)->delete("/airports/{$airport->id}")->assertRedirect('/airports');
        $this->assertModelMissing($airport);
    }

    public function test_codes_must_be_unique(): void
    {
        $admin = $this->createUserWithRole('admin');
        Airport::create(['code' => 'DOH', 'name' => 'Hamad International']);
        $other = Airport::create(['code' => 'BRU', 'name' => 'Brussels']);

        $this->actingAs($admin)->post('/airports', ['code' => 'doh', 'name' => 'Duplicate'])
            ->assertSessionHasErrors('code');
        $this->actingAs($admin)->put("/airports/{$other->id}", ['code' => 'DOH', 'name' => 'Brussels'])
            ->assertSessionHasErrors('code');
    }

    public function test_an_airport_in_use_is_not_deleted(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $doh = Airport::create(['code' => 'DOH', 'name' => 'Hamad International']);
        $flight = TeamFlight::create([
            'event_id' => $event->id, 'team_id' => $team->id, 'direction' => 'arrival',
            'destination_airport_id' => $doh->id,
        ]);

        $this->actingAs($this->createUserWithRole('admin'))
            ->delete("/airports/{$doh->id}")
            ->assertSessionHasErrors('airport');

        $this->assertModelExists($doh);
        $this->assertSame($doh->id, $flight->fresh()->destination_airport_id);
    }

    public function test_changes_need_the_fleet_manage_permission(): void
    {
        $this->actingAs(\App\Models\User::factory()->create())
            ->post('/airports', ['code' => 'DOH', 'name' => 'Hamad International'])
            ->assertForbidden();
    }
}
