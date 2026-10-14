<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\RosterMember;
use App\Models\RosterShift;
use App\Models\ShiftType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class DutyRosterTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function as(string $role, Event $event): static
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->events()->attach($event->id);

        return $this->actingAs($user)->withSession(['active_event_id' => $event->id]);
    }

    public function test_a_manager_builds_the_roster_and_paints_shifts(): void
    {
        $event = $this->createEvent();
        $aida = User::factory()->create(['name' => 'Aida', 'job_title' => 'PMA OPS Manager']);
        $morning = ShiftType::where('name', 'Morning')->firstOrFail();

        $this->as('admin', $event)->post('/duty-roster/members', ['user_ids' => [$aida->id]])->assertRedirect();
        $this->assertDatabaseHas('roster_members', ['event_id' => $event->id, 'user_id' => $aida->id, 'designation' => 'PMA OPS Manager']);

        $this->as('admin', $event)->put('/duty-roster/shifts', [
            'shift_type_id' => $morning->id,
            'cells' => [['user_id' => $aida->id, 'date' => '2026-10-11'], ['user_id' => $aida->id, 'date' => '2026-10-12']],
        ])->assertRedirect();

        $this->as('admin', $event)->get('/duty-roster?from=2026-10-11&weeks=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DutyRoster')
                ->where('from', '2026-10-11')
                ->where('members.0.name', 'Aida')
                ->where("shifts.{$aida->id}|2026-10-12", $morning->id));

        $this->as('admin', $event)->put('/duty-roster/shifts', [
            'shift_type_id' => null,
            'cells' => [['user_id' => $aida->id, 'date' => '2026-10-12']],
        ])->assertRedirect();
        $this->assertSame(1, RosterShift::where('event_id', $event->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Duty roster updated']);
    }

    public function test_only_admins_can_open_or_change_the_roster(): void
    {
        $event = $this->createEvent();
        $user = User::factory()->create();
        RosterMember::create(['event_id' => $event->id, 'user_id' => $user->id]);

        foreach (['agency', 'transport', 'SecurityRole', 'ground_control'] as $role) {
            $this->as($role, $event)->get('/duty-roster')->assertForbidden();
            $this->as($role, $event)->get('/duty-roster/export')->assertForbidden();
            $this->as($role, $event)->put('/duty-roster/shifts', [
                'shift_type_id' => ShiftType::first()->id,
                'cells' => [['user_id' => $user->id, 'date' => '2026-10-11']],
            ])->assertForbidden();
            $this->as($role, $event)->post('/duty-roster/members', ['user_ids' => [$user->id]])->assertForbidden();
        }

        $this->assertSame(0, RosterShift::count());
    }

    public function test_only_roster_staff_can_be_given_a_shift(): void
    {
        $event = $this->createEvent();
        $stranger = User::factory()->create();
        $other = $this->createEvent();
        RosterMember::create(['event_id' => $other->id, 'user_id' => $stranger->id]);

        $this->as('admin', $event)->put('/duty-roster/shifts', [
            'shift_type_id' => ShiftType::first()->id,
            'cells' => [['user_id' => $stranger->id, 'date' => '2026-10-11']],
        ])->assertSessionHasErrors('cells.0.user_id');
    }

    public function test_a_shift_type_in_use_cannot_be_deleted(): void
    {
        $event = $this->createEvent();
        $user = User::factory()->create();
        $type = ShiftType::where('name', 'Night')->firstOrFail();
        RosterShift::create(['event_id' => $event->id, 'user_id' => $user->id, 'date' => '2026-10-11', 'shift_type_id' => $type->id]);

        $this->as('admin', $event)->delete("/duty-roster/shift-types/{$type->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('shift_types', ['id' => $type->id]);

        $this->as('admin', $event)->post('/duty-roster/shift-types', ['name' => 'Split', 'code' => 's', 'color' => '#0ea5e9', 'start_time' => '07:00', 'end_time' => '11:00'])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('shift_types', ['name' => 'Split', 'code' => 'S', 'color' => '#0EA5E9']);
    }

    public function test_the_export_lays_out_the_roster(): void
    {
        $event = $this->createEvent();
        $user = User::factory()->create(['name' => 'Marcus']);
        RosterMember::create(['event_id' => $event->id, 'user_id' => $user->id, 'designation' => 'PMA OPS Manager']);
        $evening = ShiftType::where('name', 'Evening')->firstOrFail();
        RosterShift::create(['event_id' => $event->id, 'user_id' => $user->id, 'date' => '2026-10-14', 'shift_type_id' => $evening->id]);

        $response = $this->as('admin', $event)->get('/duty-roster/export?from=2026-10-11&weeks=1')->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame('11-Oct', $sheet->getCell('D4')->getValue());
        $this->assertSame('Marcus', $sheet->getCell('B6')->getValue());
        $this->assertSame('PMA OPS Manager', $sheet->getCell('C6')->getValue());
        $this->assertSame('Evening', $sheet->getCell('G6')->getValue());
        $this->assertNull($sheet->getCell('F6')->getValue());
    }
}
