<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\JobOperation;
use App\Services\JobGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class JobNumberTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->event = $this->createEvent();
    }

    private function todays(int $number): string
    {
        return sprintf('JOB-%s-%04d', now()->format('Ymd'), $number);
    }

    private function existingJob(int $number, array $movement = []): JobOperation
    {
        $team = $this->createTeam($this->event);
        $mv = $this->createMovement($this->event, $this->createPlan($this->event), $team, $movement);

        return $this->createJob($this->event, $mv, $team, ['job_id' => $this->todays($number)]);
    }

    private function generate(): JobOperation
    {
        $team = $this->createTeam($this->event);
        $movement = $this->createMovement($this->event, $this->createPlan($this->event), $team);

        return app(JobGenerationService::class)->generateJobFromMovement($movement);
    }

    public function test_a_deleted_job_does_not_make_the_next_number_collide(): void
    {
        foreach ([1, 2, 3, 4] as $n) {
            $this->existingJob($n);
        }
        JobOperation::whereIn('job_id', [$this->todays(1), $this->todays(2)])->delete();

        $this->assertSame($this->todays(5), $this->generate()->job_id);
    }

    public function test_the_number_counts_jobs_a_provider_user_cannot_see(): void
    {
        $other = $this->createProvider('Other Co');
        $this->existingJob(1, ['fleet_provider_id' => $other->id]);

        $this->actingAs($this->createProviderUser('agency'));

        $this->assertSame($this->todays(2), $this->generate()->job_id);
    }
}
