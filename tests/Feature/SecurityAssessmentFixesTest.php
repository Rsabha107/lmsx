<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\GameMatch;
use App\Models\MovementTemplate;
use App\Models\Plan;
use App\Models\Team;
use App\Models\TeamFlight;
use App\Models\User;
use App\Support\Spreadsheet\ImportLimits;
use App\Support\Spreadsheet\TextOnlyValueBinder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Regression tests for the open findings of security/SECURITY-ASSESSMENT.md (SEC-002 to SEC-007, reset parity). */
class SecurityAssessmentFixesTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $mine;

    private Event $foreign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->mine = $this->createEvent();
        $this->foreign = $this->createEvent();
    }

    /** A delegated manager: may plan and manage fleet/matches, assigned to one event only. */
    private function manager(): User
    {
        Role::firstOrCreate(['name' => 'event_manager', 'guard_name' => 'web'])
            ->syncPermissions(['console.view', 'plans.view', 'plans.manage', 'movements.view', 'fleet.view', 'fleet.manage']);
        $user = User::factory()->create();
        $user->assignRole('event_manager');
        $user->events()->attach($this->mine->id);

        return $user;
    }

    private function template(Event $event): MovementTemplate
    {
        return MovementTemplate::create(['event_id' => $event->id, 'code' => 'MVT-'.$event->id, 'name' => 'Arrival', 'scenario_type' => 'arrival_day', 'is_active' => true]);
    }

    private function asManager(): static
    {
        return $this->actingAs($this->manager())->withSession(['active_event_id' => $this->mine->id]);
    }

    public function test_sec_002_plan_creation_refuses_another_events_team_template_flight_or_match(): void
    {
        $ownTeam = $this->createTeam($this->mine);
        $ownTemplate = $this->template($this->mine);
        $foreignTeam = $this->createTeam($this->foreign);
        $foreignTemplate = $this->template($this->foreign);
        $foreignFlight = TeamFlight::create(['event_id' => $this->foreign->id, 'team_id' => $foreignTeam->id, 'direction' => 'arrival', 'flight_number' => 'QR1', 'scheduled_at' => '2026-11-16 10:00']);
        $foreignMatch = GameMatch::create(['event_id' => $this->foreign->id, 'match_number' => 'MF', 'team1_id' => $foreignTeam->id, 'kick_off' => '2026-11-16 18:00']);
        $as = $this->asManager();

        $as->postJson('/plans', ['date' => '2026-11-16', 'team_id' => $foreignTeam->id, 'movement_template_id' => $ownTemplate->id])
            ->assertJsonValidationErrors('team_id');
        $as->postJson('/plans', ['date' => '2026-11-16', 'team_id' => $ownTeam->id, 'movement_template_id' => $foreignTemplate->id])
            ->assertJsonValidationErrors('movement_template_id');
        $as->postJson('/plans', ['date' => '2026-11-16', 'team_id' => $ownTeam->id, 'flight_id' => $foreignFlight->id])
            ->assertJsonValidationErrors('flight_id');

        $as->postJson('/plans/bulk', ['plans' => [['date' => '2026-11-16', 'movement_template_id' => $ownTemplate->id, 'teams' => [['team_id' => $foreignTeam->id]]]]])
            ->assertJsonValidationErrors('plans.0.teams.0.team_id');
        $as->postJson('/plans/bulk-matches', ['plans' => [['date' => '2026-11-16', 'movement_template_id' => $ownTemplate->id,
            'teams' => [['team_id' => $ownTeam->id, 'start_time' => '13:00', 'match_id' => $foreignMatch->id]]]]])
            ->assertJsonValidationErrors('plans.0.teams.0.match_id');

        $this->assertSame(0, Plan::count());
    }

    public function test_sec_002_a_flight_must_belong_to_the_chosen_team_and_an_event_must_be_picked(): void
    {
        $team = $this->createTeam($this->mine);
        $otherTeam = $this->createTeam($this->mine);
        $flight = TeamFlight::create(['event_id' => $this->mine->id, 'team_id' => $otherTeam->id, 'direction' => 'arrival', 'flight_number' => 'QR2', 'scheduled_at' => '2026-11-16 10:00']);

        $this->asManager()->postJson('/plans', ['date' => '2026-11-16', 'team_id' => $team->id, 'flight_id' => $flight->id])
            ->assertJsonValidationErrors('flight_id');

        $this->flushSession();
        $this->actingAs($this->manager())->postJson('/plans', ['date' => '2026-11-16'])->assertForbidden();
    }

    public function test_sec_003_matches_stay_inside_events_the_user_can_access(): void
    {
        $ownTeam = $this->createTeam($this->mine);
        $foreignTeam = $this->createTeam($this->foreign);
        $foreignMatch = GameMatch::create(['event_id' => $this->foreign->id, 'match_number' => 'MF', 'team1_id' => $foreignTeam->id, 'kick_off' => '2026-11-16 18:00']);
        $as = $this->asManager();

        $as->post('/matches', ['match_number' => 'X1', 'event_id' => $this->foreign->id])->assertForbidden();
        $as->post('/matches', ['match_number' => 'X2', 'event_id' => $this->mine->id, 'team1_id' => $foreignTeam->id])->assertSessionHasErrors('team1_id');
        $as->put("/matches/{$foreignMatch->id}", ['match_number' => 'MF', 'event_id' => $this->mine->id])->assertForbidden();
        $as->delete("/matches/{$foreignMatch->id}")->assertForbidden();
        $this->assertNotNull($foreignMatch->fresh());

        // A blank event means the active one.
        $as->post('/matches', ['match_number' => 'OK1', 'event_id' => '', 'team1_id' => $ownTeam->id])->assertSessionHasNoErrors();
        $this->assertSame($this->mine->id, (int) GameMatch::where('match_number', 'OK1')->value('event_id'));
    }

    public function test_sec_004_an_oversized_sheet_is_refused_before_it_is_used(): void
    {
        $admin = $this->createUserWithRole('admin');
        $rows = "Trigram,Team\n".str_repeat("ABC,Team\n", ImportLimits::MAX_ROWS + 5);

        $this->actingAs($admin)->post("/events/{$this->mine->id}/teams/import", [
            'file' => UploadedFile::fake()->createWithContent('teams.csv', $rows),
        ])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'too large'));

        // A tiny archive that expands past the limit.
        $path = tempnam(sys_get_temp_dir(), 'bomb').'.xlsx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('xl/worksheets/sheet1.xml', str_repeat('0', ImportLimits::MAX_UNCOMPRESSED_BYTES + 1));
        $zip->close();

        $this->actingAs($admin)->post("/events/{$this->mine->id}/teams/import", [
            'file' => new UploadedFile($path, 'teams.xlsx', null, null, true),
        ])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'expands'));
        @unlink($path);
    }

    public function test_sec_005_user_text_never_becomes_a_spreadsheet_formula(): void
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->fromArray([['=HYPERLINK("http://x","y")', 42, '-3', 'plain']]);

        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A1')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('B1')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('C1')->getDataType());

        $this->assertSame("'=cmd|' /C calc'!A0", TextOnlyValueBinder::csvCell("=cmd|' /C calc'!A0"));
        $this->assertSame("\"'@SUM(1,2)\"", TextOnlyValueBinder::csvCell('@SUM(1,2)'));
        $this->assertSame('-4.5', TextOnlyValueBinder::csvCell(-4.5));
    }

    public function test_sec_006_responses_carry_security_headers(): void
    {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; object-src 'none'; base-uri 'self'");
    }

    public function test_password_reset_answers_the_same_for_known_and_unknown_addresses(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'known@example.com']);

        $known = $this->post('/forgot-password', ['email' => 'known@example.com']);
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertSessionHasNoErrors();
        $unknown->assertSessionHasNoErrors();
        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
    }

    public function test_sec_007_an_unset_proxy_list_is_left_to_infrastructure(): void
    {
        $this->artisan('app:check-production')
            ->expectsOutputToContain('PASS  TRUSTED_PROXIES is not "*"')
            ->assertFailed();
    }
}
