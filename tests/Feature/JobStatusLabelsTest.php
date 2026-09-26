<?php

namespace Tests\Feature;

use App\Models\JobOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class JobStatusLabelsTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_every_stored_status_has_a_label(): void
    {
        foreach (JobOperation::STATUSES as $status) {
            $this->assertArrayHasKey($status, JobOperation::STATUS_LABELS, "No label for '{$status}'");
        }
    }

    public function test_every_page_receives_the_shared_wording(): void
    {
        $this->actingAs($this->createUserWithRole('admin'))
            ->get('/setups/settings')
            ->assertInertia(fn ($page) => $page
                ->where('jobStatusLabels.pending', 'Scheduled')
                ->where('jobStatusLabels.completed', 'Done')
                ->where('jobStatusLabels.delayed', 'Delayed'));
    }
}
