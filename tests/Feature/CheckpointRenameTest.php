<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Event;
use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/**
 * A library checkpoint rename reaches every job's copy of the step, and Movement Tracking groups
 * steps by library checkpoint (not by name or position), so renames and template changes can't
 * split a column or drop jobs from the stats.
 */
class CheckpointRenameTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->event = $this->createEvent();
    }

    private function library(string $code, string $name): Checkpoint
    {
        return Checkpoint::create(['code' => $code, 'name' => $name, 'type' => 'manual', 'category' => 'Logistics', 'is_active' => true]);
    }

    /** A completed match job whose steps are [checkpoint, name, order]. */
    private function job(array $steps): JobOperation
    {
        $team = $this->createTeam($this->event);
        $movement = $this->createMovement($this->event, $this->createPlan($this->event), $team, ['kind' => 'match', 'window_start' => '2026-10-19 14:00', 'window_end' => '2026-10-19 15:00']);
        $job = $this->createJob($this->event, $movement, $team, ['status' => 'completed']);
        foreach ($steps as [$checkpoint, $name, $order]) {
            $this->createCheckpoint($this->event, $job, ['order' => $order, 'name' => $name, 'checkpoint_id' => $checkpoint->id]);
        }

        return $job;
    }

    public function test_renaming_a_library_checkpoint_renames_the_step_on_every_job(): void
    {
        $vsa = $this->library('CK102', 'GWC ARRIVAL AT VSA/STA');
        $other = $this->library('CK105', 'FINAL WHISTLE');
        $old = $this->job([[$vsa, 'GWC ARRIVAL AT VSA/STA', 6], [$other, 'FINAL WHISTLE', 8]]);
        $before = JobCheckpoint::where('job_id', $old->id)->where('order', 6)->value('updated_at');
        Carbon::setTestNow(now()->addMinutes(5));

        $this->actingAs($this->createUserWithRole('admin'))->put("/admin/checkpoints/{$vsa->id}", [
            'code' => 'CK102', 'name' => 'GWC ARRIVAL TIME AT VSA/STADIUM', 'category' => $vsa->category, 'type' => 'manual',
            'capture_method' => 'manual', 'is_active' => true,
        ])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'renamed on 1 job step'));
        Carbon::setTestNow();

        $step = JobCheckpoint::where('job_id', $old->id)->where('order', 6)->first();
        $this->assertSame('GWC ARRIVAL TIME AT VSA/STADIUM', $step->name);
        $this->assertTrue($step->updated_at->gt($before), 'touched, so the mobile delta sync picks the rename up');
        $this->assertSame('FINAL WHISTLE', JobCheckpoint::where('job_id', $old->id)->where('order', 8)->value('name'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'Job steps renamed after checkpoint CK102']);
    }

    public function test_the_library_says_how_many_jobs_a_rename_would_reach(): void
    {
        $vsa = $this->library('CK102', 'GWC ARRIVAL TIME AT VSA/STADIUM');
        $unused = $this->library('CK999', 'UNUSED STEP');
        $this->job([[$vsa, 'GWC ARRIVAL TIME AT VSA/STADIUM', 6]]);
        $this->job([[$vsa, 'GWC ARRIVAL AT VSA/STA', 6]]);

        $this->actingAs($this->createUserWithRole('admin'))->get('/library')->assertInertia(function (AssertableInertia $page) use ($vsa, $unused) {
            $checkpoints = collect($page->toArray()['props']['checkpoints'])->keyBy('id');
            $this->assertSame(2, (int) $checkpoints[$vsa->id]['jobs_count']);
            $this->assertSame(0, (int) $checkpoints[$unused->id]['jobs_count']);
        });
    }

    public function test_the_catch_up_command_lists_first_and_renames_with_apply(): void
    {
        $vsa = $this->library('CK102', 'GWC ARRIVAL TIME AT VSA/STADIUM');
        $job = $this->job([[$vsa, 'GWC ARRIVAL AT VSA/STA', 6]]);

        $this->artisan('checkpoints:sync-names')->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->assertSame('GWC ARRIVAL AT VSA/STA', $job->checkpoints()->value('name'));

        $this->artisan('checkpoints:sync-names --apply')->expectsOutputToContain('1 job step(s) renamed')->assertSuccessful();
        $this->assertSame('GWC ARRIVAL TIME AT VSA/STADIUM', $job->checkpoints()->value('name'));
        $this->artisan('checkpoints:sync-names')->expectsOutputToContain('already has')->assertSuccessful();
    }

    public function test_movement_tracking_groups_steps_by_library_checkpoint_not_position(): void
    {
        $vsa = $this->library('CK102', 'GWC ARRIVAL TIME AT VSA/STADIUM');
        $handover = $this->library('CK103', 'KIT HANDOVER COMPLETE');
        $unload = $this->library('CK116', 'BAG UNLOAD START AT STADIUM');
        $oldReturn = $this->library('CK108', 'ARRIVAL TO HOTEL');
        $newReturn = $this->library('CK94', 'GWC ARRIVAL TIME AT HOTEL');
        // Old template: KIT HANDOVER at 7; new template: BAG UNLOAD START at 7. Names not yet synced.
        $this->job([[$vsa, 'GWC ARRIVAL AT VSA/STA', 6], [$handover, 'KIT HANDOVER COMPLETE', 7], [$oldReturn, 'ARRIVAL TO HOTEL', 11]]);
        $this->job([[$vsa, 'GWC ARRIVAL TIME AT VSA/STADIUM', 6], [$unload, 'BAG UNLOAD START AT STADIUM', 7], [$newReturn, 'GWC ARRIVAL TIME AT HOTEL', 13]]);

        $this->actingAs($this->createUserWithRole('admin'))->get('/kit-truck?'.http_build_query(['kind' => 'match', 'event' => $this->event->id]))
            ->assertInertia(function (AssertableInertia $page) {
                $props = $page->toArray()['props'];
                $columns = collect($props['columns']);

                // One VSA column under the current name; position 7 is two columns; the back-at-hotel step is one.
                $this->assertSame(['GWC ARRIVAL TIME AT VSA/STADIUM', 'KIT HANDOVER COMPLETE', 'BAG UNLOAD START AT STADIUM', 'GWC ARRIVAL TIME AT HOTEL'], $columns->pluck('name')->all());
                $vsaKey = $columns->firstWhere('name', 'GWC ARRIVAL TIME AT VSA/STADIUM')['key'];
                $returnKey = $columns->firstWhere('name', 'GWC ARRIVAL TIME AT HOTEL')['key'];
                foreach ($props['rows'] as $row) {
                    $keys = collect($row['checkpoints'])->pluck('key');
                    $this->assertContains($vsaKey, $keys);
                    $this->assertContains($returnKey, $keys);
                    $this->assertContains('CK102', collect($row['checkpoints'])->pluck('code'));
                }
            });
    }
}
