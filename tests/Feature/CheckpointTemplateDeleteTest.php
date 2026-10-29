<?php

namespace Tests\Feature;

use App\Models\CheckpointTemplate;
use App\Models\Deletion;
use App\Models\Event;
use App\Models\MovementTemplate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Checkpoint templates go to the recycle bin; ones only used by binned movement templates can be deleted. */
class CheckpointTemplateDeleteTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Mail::fake();
        $this->event = $this->createEvent();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->events()->attach($this->event);
    }

    private function as(): static
    {
        return $this->actingAs($this->admin)->withSession(['active_event_id' => $this->event->id]);
    }

    /** A checkpoint template used by one leg of a movement template. */
    private function templates(): array
    {
        $checkpointTemplate = CheckpointTemplate::create(['event_id' => $this->event->id, 'code' => 'CT-OLD', 'name' => 'Old departure', 'movement_type' => 'departure']);
        $movementTemplate = MovementTemplate::create(['event_id' => $this->event->id, 'code' => 'MVT-OLD', 'name' => 'Old movement']);
        $movementTemplate->legs()->create(['order' => 1, 'leg_type' => 'departure', 'checkpoint_template_id' => $checkpointTemplate->id]);

        return [$checkpointTemplate, $movementTemplate];
    }

    public function test_a_template_used_by_a_live_movement_template_is_refused_with_its_name(): void
    {
        [$checkpointTemplate] = $this->templates();

        $this->as()->delete("/admin/checkpoint-templates/{$checkpointTemplate->id}")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'MVT-OLD · Old movement'));
        $this->assertNotSoftDeleted($checkpointTemplate);
    }

    public function test_once_its_movement_template_is_in_the_bin_it_is_binned_and_both_come_back_together(): void
    {
        [$checkpointTemplate, $movementTemplate] = $this->templates();
        $this->as()->delete("/admin/movement-templates/{$movementTemplate->id}")->assertSessionHas('success');

        $this->as()->delete("/admin/checkpoint-templates/{$checkpointTemplate->id}")->assertSessionHas('success');
        $this->assertSoftDeleted($checkpointTemplate);
        $this->assertSame(0, CheckpointTemplate::where('event_id', $this->event->id)->count());
        $ctBatch = Deletion::where('kind', 'checkpoint_templates')->firstOrFail();

        // A leg can no longer pick it.
        $this->as()->post("/admin/movement-templates/{$movementTemplate->id}/legs", ['checkpoint_template_id' => $checkpointTemplate->id, 'order' => 2, 'transport_type' => 'bus'])
            ->assertSessionHasErrors('checkpoint_template_id');

        // Restoring the movement template brings its checkpoint template back and closes that batch.
        $mtBatch = Deletion::where('kind', 'movement_templates')->firstOrFail();
        $this->as()->post("/recycle-bin/{$mtBatch->id}/restore")->assertSessionHas('success');
        $this->assertNotSoftDeleted($movementTemplate);
        $this->assertNotSoftDeleted($checkpointTemplate);
        $this->assertNull($checkpointTemplate->refresh()->deletion_id);
        $this->assertSame('restored', $ctBatch->refresh()->status);
    }

    public function test_the_purge_removes_the_movement_template_before_its_checkpoint_template(): void
    {
        [$checkpointTemplate, $movementTemplate] = $this->templates();
        $this->as()->delete("/admin/movement-templates/{$movementTemplate->id}");
        $this->as()->delete("/admin/checkpoint-templates/{$checkpointTemplate->id}");

        $this->travel(31)->days();
        $this->artisan('deletions:purge --apply')->assertSuccessful();

        $this->assertDatabaseMissing('movement_templates', ['id' => $movementTemplate->id]);
        $this->assertDatabaseMissing('checkpoint_templates', ['id' => $checkpointTemplate->id]);
        $this->assertSame(0, Deletion::whereIn('status', Deletion::IN_BIN)->count());
    }
}
