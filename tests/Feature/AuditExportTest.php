<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class AuditExportTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['name' => 'Audit Admin']);
        $user->assignRole('admin');

        return $user;
    }

    private function entry(string $at, array $attrs): AuditLog
    {
        $log = AuditLog::create($attrs + ['user_name' => 'Someone', 'user_role' => 'admin', 'action' => 'Something']);
        $log->forceFill(['created_at' => Carbon::parse($at)])->save();

        return $log;
    }

    private function rows($response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'aud');
        file_put_contents($path, $response->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        unlink($path);

        return $rows;
    }

    public function test_the_export_downloads_every_matching_entry_with_readable_details(): void
    {
        $event = $this->createEvent();
        $this->entry('2026-10-21 09:15:00', [
            'user_name' => 'Aida', 'action' => 'Movement template deleted', 'target' => 'MVT-ARR', 'event_id' => $event->id,
            'meta' => json_encode(['name' => 'Team Arrival', 'ip' => '78.100.89.194']),
        ]);
        $this->entry('2026-10-21 10:00:00', [
            'user_name' => 'Malik', 'action' => 'User updated', 'target' => 'r.sabha',
            'meta' => json_encode(['from' => ['job_title' => 'Ops'], 'to' => ['job_title' => 'Lead'], 'ip' => '10.0.0.1']),
        ]);
        $this->entry('2026-10-20 08:00:00', ['user_name' => 'System', 'user_role' => 'Automation', 'action' => 'Job deleted', 'meta' => 'Status was completed']);

        $response = $this->actingAs($this->admin())->get('/audit/export')->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('AUDIT_TRAIL_all.xlsx', $response->headers->get('Content-Disposition'));

        $rows = $this->rows($response);
        $this->assertSame(['Date & time', 'Who', 'Role', 'Action', 'Target', 'Event', 'IP', 'Details'], $rows[0]);
        // Newest first; the export's own audit entry is written after the file is built.
        $this->assertSame(['2026-10-21 10:00:00', 'Malik', 'admin', 'User updated', 'r.sabha', null, '10.0.0.1', 'job_title: Ops → Lead'], $rows[1]);
        $this->assertSame(['2026-10-21 09:15:00', 'Aida', 'admin', 'Movement template deleted', 'MVT-ARR', $event->name, '78.100.89.194', 'name: Team Arrival'], $rows[2]);
        $this->assertSame([null, 'Status was completed'], [$rows[3][6], $rows[3][7]]);
        $this->assertCount(4, $rows);

        $this->assertDatabaseHas('audit_logs', ['action' => 'Audit trail exported', 'target' => '3 entries']);
    }

    public function test_the_page_and_the_export_share_the_filters(): void
    {
        $this->entry('2026-10-19 12:00:00', ['action' => 'Plan created', 'user_role' => 'transport']);
        $this->entry('2026-10-21 12:00:00', ['action' => 'Job deleted', 'target' => 'JOB-1']);
        $this->entry('2026-10-21 13:00:00', ['action' => 'Job deleted', 'target' => 'JOB-2', 'user_role' => 'transport']);
        $this->entry('2026-10-22 12:00:00', ['action' => 'Job deleted', 'target' => 'JOB-3']);
        $as = $this->actingAs($this->admin());

        $as->get('/audit?search=job deleted&from=2026-10-21&to=2026-10-21')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Audit')
                ->where('total', 2)
                ->where('filters.from', '2026-10-21')
                ->where('audit', fn ($rows) => collect($rows)->pluck('target')->all() === ['JOB-2', 'JOB-1']));

        $rows = $this->rows($as->get('/audit/export?search=job%20deleted&role=transport&from=2026-10-21&to=2026-10-21')->assertOk());
        $this->assertSame([['JOB-2']], array_map(fn ($r) => [$r[4]], array_slice($rows, 1)));

        // A reversed range still works; a bad date is refused.
        // The export's own audit entry stores its filters, so search the job numbers here.
        $as->get('/audit?search=JOB-&from=2026-10-22&to=2026-10-21')->assertInertia(fn (Assert $page) => $page->where('total', 3));
        $as->get('/audit/export?from=21-10-2026')->assertSessionHasErrors('from');
    }

    public function test_cells_are_written_as_text(): void
    {
        $this->entry('2026-10-21 12:00:00', ['action' => 'Contact updated', 'target' => '=HYPERLINK("x")', 'meta' => '+97450000000']);

        $path = tempnam(sys_get_temp_dir(), 'aud');
        file_put_contents($path, $this->actingAs($this->admin())->get('/audit/export')->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame('=HYPERLINK("x")', $sheet->getCell('E2')->getValue());
        $this->assertSame('s', $sheet->getCell('E2')->getDataType());
        $this->assertSame('+97450000000', $sheet->getCell('H2')->getValue());
    }

    public function test_it_needs_audit_view(): void
    {
        $user = User::factory()->create();
        $user->assignRole('transport');

        $this->actingAs($user)->get('/audit/export')->assertForbidden();
        $this->actingAs($user)->get('/audit')->assertForbidden();
    }
}
