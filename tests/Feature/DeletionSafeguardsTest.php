<?php

namespace Tests\Feature;

use App\Mail\DeletionRequestMail;
use App\Models\Deletion;
use App\Models\Event;
use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\MovementTemplate;
use App\Models\Plan;
use App\Models\User;
use App\Services\DeletionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class DeletionSafeguardsTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Mail::fake();
        $this->event = $this->createEvent();
    }

    private function admin(string $name = 'Admin'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('admin');
        $user->events()->attach($this->event);

        return $user;
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['active_event_id' => $this->event->id]);
    }

    /** A plan from $template with $count movements, each with a job and one completed checkpoint. */
    private function planWithJobs(?MovementTemplate $template, int $count): array
    {
        $plan = $this->createPlan($this->event);
        $plan->update(['movement_template_id' => $template?->id]);
        $team = $this->createTeam($this->event);
        $jobs = [];
        for ($i = 0; $i < $count; $i++) {
            $movement = $this->createMovement($this->event, $plan, $team);
            $job = $this->createJob($this->event, $movement, $team, ['status' => 'completed']);
            $movement->update(['job_id' => $job->job_id, 'job_generated_at' => now()]);
            $this->createCheckpoint($this->event, $job, ['state' => 'done', 'photo_path' => "checkpoint-photos/{$job->job_id}.jpg"]);
            $jobs[] = $job;
        }

        return [$plan, collect($jobs)];
    }

    public function test_the_21_october_sequence_needs_approvals_and_is_fully_restorable(): void
    {
        $malik = $this->admin('Malik');
        $aida = $this->admin('Aida');
        $template = MovementTemplate::create(['event_id' => $this->event->id, 'code' => 'MVT-ARR', 'name' => 'Arrival']);
        $template->legs()->create(['order' => 1, 'leg_type' => 'arrival', 'checkpoint_template_id' => \App\Models\CheckpointTemplate::create(['event_id' => $this->event->id, 'code' => 'CT-A', 'name' => 'A', 'movement_type' => 'arrival'])->id]);
        [$planA, $jobsA] = $this->planWithJobs($template, 8);
        [$planB, $jobsB] = $this->planWithJobs($template, 6);
        $jobIds = $jobsA->merge($jobsB)->pluck('id')->all();

        // 1. All 14 jobs at once: more than 10, so it waits for Aida.
        $this->as($malik)->delete('/jobs', ['ids' => $jobIds, 'confirm' => 'DELETE'])
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Sent for approval: 14 jobs.'));
        $this->assertSame(14, JobOperation::count());
        Mail::assertSent(DeletionRequestMail::class, fn ($mail) => $mail->hasTo($aida->email) && ! $mail->hasTo($malik->email));

        $jobsRequest = Deletion::where('kind', 'jobs')->firstOrFail();
        // Malik can't approve his own request.
        $this->as($malik)->post("/recycle-bin/{$jobsRequest->id}/approve")->assertSessionHas('error');
        $this->as($aida)->post("/recycle-bin/{$jobsRequest->id}/approve")->assertSessionHas('success');
        $this->assertSame(0, JobOperation::count());
        $this->assertSame(14, JobOperation::onlyTrashed()->count());
        $this->assertSame(14, JobCheckpoint::count(), 'checkpoints stay with the deleted jobs');

        // 2. The plans no longer have jobs, so two plans go at once.
        $this->as($malik)->delete('/plans/bulk-delete', ['ids' => [$planA->id, $planB->id], 'confirm' => 'DELETE'])
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Deleted 2 plans.'));
        $this->assertSoftDeleted($planA);

        // 3. The template is still used by the (deleted) plans, so it waits for Aida too.
        $this->as($malik)->delete("/admin/movement-templates/{$template->id}")
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Sent for approval'));
        $this->assertNotSoftDeleted($template);
        $templateRequest = Deletion::where('kind', 'movement_templates')->firstOrFail();
        $this->as($aida)->post("/recycle-bin/{$templateRequest->id}/approve");
        $this->assertSoftDeleted($template);
        $this->assertSame($template->id, Plan::withTrashed()->find($planA->id)->movement_template_id, 'the plan keeps its template link');

        // Restore everything, newest first, as an admin undoing the incident would.
        foreach (Deletion::where('status', 'done')->orderByDesc('id')->get() as $batch) {
            $this->as($aida)->post("/recycle-bin/{$batch->id}/restore")->assertSessionHas('success');
            $this->assertSame('restored', $batch->refresh()->status);
        }

        $this->assertNotSoftDeleted($template);
        $this->assertSame(1, $template->legs()->count());
        $this->assertNotSoftDeleted($planA);
        $this->assertSame(14, Movement::whereIn('plan_id', [$planA->id, $planB->id])->count());
        $this->assertSame(14, JobOperation::count());
        foreach (JobOperation::with('movement')->get() as $job) {
            $this->assertSame($job->job_id, $job->movement->job_id, 'each job is linked back to its movement');
            $this->assertSame(1, $job->checkpoints()->where('state', 'done')->count());
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'Deletion restored']);
    }

    public function test_small_deletes_run_at_once_and_large_ones_wait(): void
    {
        $admin = $this->admin();
        [, $ten] = $this->planWithJobs(null, 10);
        [, $eleven] = $this->planWithJobs(null, 11);

        $this->as($admin)->delete('/jobs', ['ids' => $ten->pluck('id')->all(), 'confirm' => 'DELETE']);
        $this->assertSame(10, JobOperation::onlyTrashed()->count());

        $this->as($admin)->delete('/jobs', ['ids' => $eleven->pluck('id')->all(), 'confirm' => 'DELETE']);
        $this->assertSame(11, JobOperation::count());
        $this->assertDatabaseHas('deletions', ['kind' => 'jobs', 'status' => 'pending', 'trigger' => 'threshold', 'item_count' => 11]);
    }

    public function test_the_mass_delete_brake_trips_after_25_jobs_in_an_hour(): void
    {
        $admin = $this->admin();
        $batches = collect(range(1, 3))->map(fn () => $this->planWithJobs(null, 10)[1]);

        $this->as($admin)->delete('/jobs', ['ids' => $batches[0]->pluck('id')->all(), 'confirm' => 'DELETE']);
        $this->as($admin)->delete('/jobs', ['ids' => $batches[1]->pluck('id')->all(), 'confirm' => 'DELETE']);
        $this->assertSame(20, JobOperation::onlyTrashed()->count());

        // 20 + 10 is past 25: this one waits, and the other admins hear about it.
        $this->as($admin)->delete('/jobs', ['ids' => $batches[2]->pluck('id')->all(), 'confirm' => 'DELETE'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Over the limit of 25 jobs'));
        $this->assertSame(20, JobOperation::onlyTrashed()->count());
        $this->assertDatabaseHas('deletions', ['status' => 'pending', 'trigger' => 'brake']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Mass-delete brake: approval needed']);

        // An hour later the count starts again.
        $this->travel(61)->minutes();
        [, $more] = $this->planWithJobs(null, 5);
        $this->as($admin)->delete('/jobs', ['ids' => $more->pluck('id')->all(), 'confirm' => 'DELETE']);
        $this->assertSame(25, JobOperation::onlyTrashed()->count());
    }

    public function test_requests_can_be_rejected_withdrawn_or_expire(): void
    {
        $malik = $this->admin('Malik');
        $aida = $this->admin('Aida');
        $deletions = app(DeletionService::class);
        $request = fn () => $deletions->request('event', collect([$this->event]), $malik, $this->event->id);

        $rejected = $request();
        $this->as($aida)->post("/recycle-bin/{$rejected->id}/reject", ['note' => 'Not this one'])->assertSessionHas('success');
        $this->assertSame(['rejected', 'Not this one'], [$rejected->refresh()->status, $rejected->decision_note]);
        $this->assertNotSoftDeleted($this->event);

        $withdrawn = $request();
        $this->as($aida)->post("/recycle-bin/{$withdrawn->id}/withdraw")->assertSessionHas('error');
        $this->as($malik)->post("/recycle-bin/{$withdrawn->id}/withdraw")->assertSessionHas('success');
        $this->assertSame('withdrawn', $withdrawn->refresh()->status);

        $stale = $request();
        $this->travel(25)->hours();
        $this->as($aida)->post("/recycle-bin/{$stale->id}/approve")->assertSessionHas('error');
        $this->artisan('deletions:purge --apply')->assertSuccessful();
        $this->assertSame('expired', $stale->refresh()->status);
        $this->assertNotSoftDeleted($this->event);
    }

    public function test_a_job_whose_movement_has_a_new_job_is_not_restored(): void
    {
        $admin = $this->admin();
        [, $jobs] = $this->planWithJobs(null, 2);
        [$first, $second] = $jobs->all();

        $this->as($admin)->delete('/jobs', ['ids' => [$first->id, $second->id], 'confirm' => 'DELETE']);
        // A new job was generated for the first movement in the meantime.
        $replacement = $this->createJob($this->event, $first->movement, $first->team);
        $first->movement->update(['job_id' => $replacement->job_id]);

        $batch = Deletion::where('kind', 'jobs')->firstOrFail();
        $this->as($admin)->post("/recycle-bin/{$batch->id}/restore")
            ->assertSessionHas('success', fn ($m) => str_contains($m, "Job {$first->job_id}: its movement now has job {$replacement->job_id}"));

        $this->assertSoftDeleted($first);
        $this->assertNotSoftDeleted($second);
        $this->assertSame('partially_restored', $batch->refresh()->status);

        // Only the chosen items: the remaining job stays in the bin.
        $this->as($admin)->post("/recycle-bin/{$batch->id}/restore", ['ids' => [$first->id]]);
        $this->assertSoftDeleted($first);
    }

    public function test_the_purge_removes_the_batch_and_its_evidence_after_30_days(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        [$plan, $jobs] = $this->planWithJobs(null, 1);
        $job = $jobs->first();
        Storage::disk('local')->put("checkpoint-photos/{$job->job_id}.jpg", 'photo');

        $this->as($admin)->delete('/jobs', ['ids' => [$job->id], 'confirm' => $job->job_id]);
        $this->artisan('deletions:purge --apply');
        $this->assertSoftDeleted($job);
        Storage::disk('local')->assertExists("checkpoint-photos/{$job->job_id}.jpg");

        $this->travel(31)->days();
        $this->artisan('deletions:purge')->expectsOutputToContain('Dry run: 1 batch(es)')->assertSuccessful();
        $this->assertSoftDeleted($job);

        $this->artisan('deletions:purge --apply')->assertSuccessful();
        $this->assertDatabaseMissing('jobs_operations', ['id' => $job->id]);
        $this->assertSame(0, JobCheckpoint::where('job_id', $job->id)->count());
        Storage::disk('local')->assertMissing("checkpoint-photos/{$job->job_id}.jpg");
        $this->assertDatabaseHas('deletions', ['kind' => 'jobs', 'status' => 'purged']);
        $this->assertNotSoftDeleted($plan);
    }

    public function test_the_database_no_longer_cascades_a_plan_delete_onto_movements_and_jobs(): void
    {
        [$plan] = $this->planWithJobs(null, 1);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \Illuminate\Support\Facades\DB::table('plans')->where('id', $plan->id)->delete();
    }

    public function test_the_recycle_bin_page_lists_requests_and_batches_for_admins_only(): void
    {
        $malik = $this->admin('Malik');
        $aida = $this->admin('Aida');
        [, $jobs] = $this->planWithJobs(null, 12);
        [, $few] = $this->planWithJobs(null, 2);
        $this->as($malik)->delete('/jobs', ['ids' => $jobs->pluck('id')->all(), 'confirm' => 'DELETE']);
        $this->as($malik)->delete('/jobs', ['ids' => $few->pluck('id')->all(), 'confirm' => 'DELETE']);

        $this->as($aida)->get('/setups/recycle-bin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RecycleBin')
                ->where('pendingDeletions', 1)
                ->where('pending.0.label', '12 jobs')
                ->where('pending.0.mine', false)
                ->where('deleted.0.label', '2 jobs')
                ->where('deleted.0.days_left', 30)
                ->has('deleted.0.items', 2));

        // Your own request doesn't count as waiting for you.
        $this->as($malik)->get('/setups/recycle-bin')->assertInertia(fn (Assert $page) => $page->where('pendingDeletions', 0)->where('pending.0.mine', true));

        $transport = User::factory()->create();
        $transport->assignRole('transport');
        $this->actingAs($transport)->get('/setups/recycle-bin')->assertForbidden();
        $this->actingAs($transport)->post('/recycle-bin/'.Deletion::firstOrFail()->id.'/restore')->assertForbidden();
    }

    public function test_plans_deleted_before_the_recycle_bin_can_be_restored(): void
    {
        $admin = $this->admin();
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $movement = $this->createMovement($this->event, $plan, $team);
        $older = $this->createMovement($this->event, $plan, $team);
        $older->delete();
        $this->travel(10)->minutes();
        $movement->delete();
        $plan->delete();

        $this->as($admin)->get('/setups/recycle-bin')
            ->assertInertia(fn (Assert $page) => $page->where('earlier.0.id', $plan->id)->where('earlier.0.movements', 1));

        $this->as($admin)->post("/recycle-bin/earlier-plans/{$plan->id}/restore")->assertSessionHas('success');
        $this->assertNotSoftDeleted($plan);
        $this->assertNotSoftDeleted($movement);
        $this->assertSoftDeleted($older, [], 'deleted', 'a movement deleted separately earlier stays deleted');
    }
}
