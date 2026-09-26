<?php

namespace Tests\Feature;

use App\Http\Resources\JobResource;
use App\Models\Team;
use App\Support\CountryFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class MobileJobFlagTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_known_codes_map_to_iso_and_unknown_ones_do_not(): void
    {
        $this->assertSame('dz', CountryFlags::iso('ALG'));
        $this->assertSame('gb-eng', CountryFlags::iso(' eng '));
        $this->assertNull(CountryFlags::iso('XYZ'));
        $this->assertNull(CountryFlags::iso(null));
    }

    public function test_the_job_payload_carries_the_teams_iso_country(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $plan = $this->createPlan($event);
        $job = $this->createJob($event, $this->createMovement($event, $plan, $team), $team);
        \App\Models\Country::create(['country_code' => 'MAR', 'country_name' => 'Morocco']);
        \App\Models\Country::create(['country_code' => 'XXX', 'country_name' => 'No known flag']);

        $isoFor = function (array $teamAttributes) use ($team, $job) {
            $team->forceFill($teamAttributes)->save();

            return (new JobResource($job->fresh()->load('team')))->toArray(Request::create('/'))['team_country_iso'];
        };

        $this->assertSame('ma', $isoFor(['country_id' => 'MAR', 'code' => 'XXX-17']));
        // A country with no known flag: the squad code's stem supplies it.
        $this->assertSame('dz', $isoFor(['country_id' => 'XXX', 'code' => 'ALG-17']));
        $this->assertNull($isoFor(['country_id' => 'XXX', 'code' => 'ZZZ-17']));
    }
}
