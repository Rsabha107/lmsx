<?php

namespace Tests\Feature;

use App\Ai\Agents\SheetColumnMappingAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class SheetConverterTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'fleet.manage', 'guard_name' => 'web']);
    }

    public function test_guest_cannot_reach_the_converter(): void
    {
        $this->get('/utilities')->assertRedirect('/login');
    }

    public function test_user_without_fleet_manage_is_forbidden(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->get('/utilities')->assertForbidden();
    }

    public function test_every_converter_is_offered_as_a_tab(): void
    {
        $this->actingAs($this->admin())->get('/utilities')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Utilities/Index')
                ->has('tools', 2)
                ->where('activeTool', 'teams'));
    }

    public function test_a_tool_can_be_deep_linked(): void
    {
        $this->actingAs($this->admin())->get('/utilities?tool=matches')
            ->assertInertia(fn ($page) => $page->where('activeTool', 'matches'));

        // An unknown tool falls back to the first tab rather than erroring.
        $this->actingAs($this->admin())->get('/utilities?tool=nonsense')
            ->assertInertia(fn ($page) => $page->where('activeTool', 'teams'));
    }

    public function test_the_old_per_converter_urls_redirect_to_the_tab(): void
    {
        $this->actingAs($this->admin())->get('/utilities/converters/matches')
            ->assertRedirect('/utilities?tool=matches');
    }

    public function test_a_recognisable_sheet_converts_without_calling_the_ai(): void
    {
        SheetColumnMappingAgent::fake(['the agent must not be called']);

        $response = $this->actingAs($this->admin())->post('/utilities/converters/teams/preview', [
            'file' => $this->sheet([
                ['Trigram', 'Team Name', 'Arrival Flight Number', 'Arrival Date', 'Arrival Time'],
                ['EGY', 'Egypt', 'MS 905', '2026-11-15', '18:05'],
                ['FRA', 'France', 'AF 123', '2026-11-16', '09:30'],
            ]),
            'use_ai' => '0',
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true, 'usedAi' => false, 'headerRow' => 1]);

        $rows = $response->json('rows');
        $this->assertCount(2, $rows);
        $this->assertSame('EGY', $rows[0][0]);
        $this->assertSame('Egypt', $rows[0][1]);
        $this->assertSame('MS 905', $rows[0][7]);
        $this->assertContains('country_code', $response->json('unmappedFields'));
    }

    public function test_a_sheet_with_no_trigram_column_fails_cleanly_when_ai_is_off(): void
    {
        $response = $this->actingAs($this->admin())->post('/utilities/converters/teams/preview', [
            'file' => $this->sheet([
                ['PMA', 'TEAM', 'INBOUND FLIGHT NUMBER'],
                ['BHR-V', 'BAHRAIN-V', 'QR1103'],
            ]),
            'use_ai' => '0',
        ]);

        $response->assertStatus(422)->assertJson(['ok' => false]);
    }

    public function test_download_returns_an_xlsx_of_the_reviewed_rows(): void
    {
        $row = array_fill(0, 16, '');
        $row[0] = 'EGY';
        $row[1] = 'Egypt';

        $response = $this->actingAs($this->admin())->post('/utilities/converters/teams/download', [
            'rows' => [$row],
        ]);

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString(
            'PMA_' . now()->format('dmY') . '_TEAMS.xlsx',
            $response->headers->get('content-disposition'),
        );
    }

    public function test_the_filename_carries_the_active_event_code_and_date(): void
    {
        $event = \App\Models\Event::create([
            'name' => 'GFF U17 Gulf Cup Qatar 2026',
            'short_name' => 'GFFU1726',
            'start_date' => '2026-10-28',
            'end_date' => '2026-11-05',
        ]);

        $expected = "PMA_GFFU1726_{$this->today()}_MATCHES.xlsx";

        $response = $this->actingAs($this->admin())
            ->withSession(['active_event_id' => $event->id])
            ->post('/utilities/converters/matches/download', [
                'rows' => [array_fill(0, 7, 'x')],
            ]);

        $this->assertStringContainsString($expected, $response->headers->get('content-disposition'));
    }

    public function test_the_filename_follows_the_event_switched_to_after_converting(): void
    {
        $first = \App\Models\Event::create([
            'name' => 'GFF U17 Gulf Cup Qatar 2026',
            'short_name' => 'GFFU1726',
            'start_date' => '2026-10-28',
            'end_date' => '2026-11-05',
        ]);
        $second = \App\Models\Event::create([
            'name' => 'FIFA U-17 World Cup Qatar 2026',
            'short_name' => 'FU17WC26',
            'start_date' => '2026-11-10',
            'end_date' => '2026-12-02',
        ]);

        $admin = $this->admin();
        $rows = [array_fill(0, 16, '')];

        $this->actingAs($admin)->withSession(['active_event_id' => $first->id])
            ->post('/utilities/converters/teams/download', ['rows' => $rows]);

        // Switching the active event must rename the next download, not reuse
        // the name minted during the earlier preview.
        $response = $this->actingAs($admin)->withSession(['active_event_id' => $second->id])
            ->post('/utilities/converters/teams/download', ['rows' => $rows]);

        $this->assertStringContainsString(
            "PMA_FU17WC26_{$this->today()}_TEAMS.xlsx",
            $response->headers->get('content-disposition'),
        );
    }

    private function today(): string
    {
        return now()->format('dmY');
    }

    public function test_download_rejects_rows_that_are_not_the_template_shape(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/utilities/converters/teams/download', ['rows' => [['EGY', 'Egypt']]])
            ->assertStatus(422);
    }

    public function test_country_name_and_code_come_from_the_countries_table_not_the_sheet(): void
    {
        \App\Models\Country::create([
            'country_code' => 'BHR',
            'country_name' => 'Bahrain',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->post('/utilities/converters/teams/preview', [
            'file' => $this->sheet([
                ['Trigram', 'Team Name'],
                ['BHR-V', 'BAHRAIN-V'],
                ['CLUB1', 'Some Club Side'],
            ]),
            'use_ai' => '0',
        ]);

        $rows = $response->assertOk()->json('rows');

        // Trigram is the event-unique key, so the suffix stays on it.
        $this->assertSame(['BHR-V', 'Bahrain', 'BHR'], array_slice($rows[0], 0, 3));

        // No matching country - left exactly as the sheet had it.
        $this->assertSame(['CLUB1', 'Some Club Side', ''], array_slice($rows[1], 0, 3));
    }

    public function test_a_fixtures_sheet_converts_to_the_matches_template(): void
    {
        SheetColumnMappingAgent::fake(['the agent must not be called']);

        $response = $this->actingAs($this->admin())->post('/utilities/converters/matches/preview', [
            'file' => $this->sheet([
                ['Match No.', 'Match Date', 'KO', 'TEAM1', 'PMA1', 'PMA2', 'Venue', 'Match Round'],
                ['VGC26-001', '2026-10-30', '17:30', 'Bahrain', 'BHR-V', 'IRQ-V', 'GRAND HAMAD', 'MD-R1'],
            ]),
            'use_ai' => '0',
        ]);

        $rows = $response->assertOk()->assertJson(['ok' => true, 'usedAi' => false])->json('rows');

        // Match Number, Match Date, Kick Off, Team1 Code, Team2 Code, Venue, Stage
        $this->assertSame(
            ['VGC26-001', '2026-10-30', '17:30', 'BHR-V', 'IRQ-V', 'GRAND HAMAD', 'MD-R1'],
            $rows[0],
        );
    }

    public function test_an_unknown_converter_type_is_not_found(): void
    {
        $this->actingAs($this->admin())->post('/utilities/converters/nonsense/preview', [])->assertNotFound();
    }

    private function admin(): \App\Models\User
    {
        return $this->createUserWithRole('admin');
    }

    /**
     * @param array<int, array<int, string>> $grid
     */
    private function sheet(array $grid): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($grid, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'sheet') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'teams.xlsx', null, null, true);
    }
}
