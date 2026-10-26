<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Movement Tracking → Export to Excel: the on-screen table for the chosen type, event, date and area. */
class MovementTrackingExportTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    private Event $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->event = $this->createEvent();
        $this->other = $this->createEvent();
    }

    private function arrivalJob(Event $event, string $teamName, string $area = 'LOG'): void
    {
        $team = $this->createTeam($event);
        $team->update(['team_name' => $teamName]);
        $movement = $this->createMovement($event, $this->createPlan($event), $team, [
            'kind' => 'arrival', 'functional_area' => $area, 'window_start' => '2026-11-20 09:00', 'window_end' => '2026-11-20 10:00',
        ]);
        $job = $this->createJob($event, $movement, $team, ['status' => 'completed']);
        $this->createCheckpoint($event, $job, ['order' => 1, 'name' => 'Meet the team'])
            ->forceFill(['state' => 'done', 'scheduled_at' => '2026-11-20 09:00', 'completed_at' => '2026-11-20 09:20'])->save();
    }

    private function sheet(User $user, array $query): Worksheet
    {
        $response = $this->actingAs($user)->get('/kit-truck/export?'.http_build_query($query));
        $response->assertOk();
        $this->assertStringContainsString('Movement-Tracking_Arrivals_', $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'mt').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
    }

    /** @return list<string> team names in the data rows */
    private function teams(Worksheet $sheet): array
    {
        $col = array_search('Team', $sheet->rangeToArray('A4:Z4')[0], true);

        return collect($sheet->toArray())->slice(5)->pluck($col)->filter()->values()->all();
    }

    public function test_it_exports_the_chosen_type_and_event_with_planned_actual_and_variance(): void
    {
        $this->arrivalJob($this->event, 'Alpha FC');
        $this->arrivalJob($this->other, 'Beta FC');
        $admin = $this->createUserWithRole('admin');

        $sheet = $this->sheet($admin, ['kind' => 'arrival', 'event' => $this->event->id]);

        $this->assertSame(['Alpha FC'], $this->teams($sheet));
        $this->assertSame('Meet the team', $sheet->getCell('K4')->getValue());
        $this->assertSame(['Planned', 'Actual', 'Late / early (min)', '+/- min'], [$sheet->getCell('K5')->getValue(), $sheet->getCell('L5')->getValue(), $sheet->getCell('M5')->getValue(), $sheet->getCell('N5')->getValue()]);
        $this->assertSame(['09:00', '09:20', 'Late 20', 20], [$sheet->getCell('K6')->getValue(), $sheet->getCell('L6')->getValue(), $sheet->getCell('M6')->getValue(), $sheet->getCell('N6')->getValue()]);
        $this->assertSame('+20', $sheet->getCell('N6')->getFormattedValue());

        // All events adds an Event column; the team search narrows it like the page.
        $all = $this->sheet($admin, ['kind' => 'arrival', 'event' => '']);
        $this->assertSame('Event', $all->getCell('A4')->getValue());
        $this->assertEqualsCanonicalizing(['Alpha FC', 'Beta FC'], $this->teams($all));
        $this->assertSame(['Beta FC'], $this->teams($this->sheet($admin, ['kind' => 'arrival', 'event' => '', 'q' => 'beta'])));
    }

    public function test_the_stats_shown_above_the_table_go_on_top_of_the_sheet(): void
    {
        $this->arrivalJob($this->event, 'Alpha FC');
        $admin = $this->createUserWithRole('admin');
        $stats = [
            ['title' => 'Arrivals Overview', 'tiles' => [['label' => 'Teams', 'value' => 1, 'sub' => null], ['label' => 'Movements Completed', 'value' => 1]]],
            ['title' => 'GWC Performance', 'tiles' => [['label' => 'Avg Planned v Actual (Staging)', 'value' => '20 mins late', 'tone' => 'late', 'sub' => '=HYPERLINK("x")']]],
        ];

        $sheet = $this->sheet($admin, ['kind' => 'arrival', 'event' => $this->event->id, 'stats' => json_encode($stats)]);

        // Like the page: group titles (row 4), then one card per tile: value, label, note.
        $this->assertSame(['ARRIVALS OVERVIEW', 'GWC PERFORMANCE'], [$sheet->getCell('A4')->getValue(), $sheet->getCell('C4')->getValue()]);
        $this->assertTrue($sheet->getCell('A4')->isMergeRangeValueCell());
        $this->assertSame([1, 'Teams', 1, 'Movements Completed'], [$sheet->getCell('A5')->getValue(), $sheet->getCell('A6')->getValue(), $sheet->getCell('B5')->getValue(), $sheet->getCell('B6')->getValue()]);
        $this->assertSame(['20 mins late', 'Avg Planned v Actual (Staging)'], [$sheet->getCell('C5')->getValue(), $sheet->getCell('C6')->getValue()]);
        $this->assertSame('B91C1C', $sheet->getStyle('C5')->getFont()->getColor()->getRGB());
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $sheet->getCell('C7')->getDataType());
        // The table starts two rows below the cards.
        $this->assertSame(['PMA', 'Alpha FC'], [$sheet->getCell('A9')->getValue(), $sheet->getCell('B11')->getValue()]);

        // Anything that isn't the expected shape is ignored, not an error.
        $this->assertSame('PMA', $this->sheet($admin, ['kind' => 'arrival', 'event' => $this->event->id, 'stats' => 'nonsense'])->getCell('A4')->getValue());
    }

    public function test_a_scoped_user_exports_only_their_events_and_functional_areas(): void
    {
        $this->arrivalJob($this->event, 'Mine FC');
        $this->arrivalJob($this->event, 'Mobility FC', 'MOB');
        $this->arrivalJob($this->other, 'Foreign FC');
        $transport = User::factory()->create();
        $transport->assignRole('transport');
        $transport->functionalAreas()->create(['functional_area' => 'LOG']);
        $transport->events()->attach($this->event->id);

        $this->assertSame(['Mine FC'], $this->teams($this->sheet($transport, ['kind' => 'arrival', 'event' => ''])));
        $this->assertSame([], $this->teams($this->sheet($transport, ['kind' => 'arrival', 'event' => $this->other->id])));
    }
}
