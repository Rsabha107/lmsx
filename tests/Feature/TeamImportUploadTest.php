<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class TeamImportUploadTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_a_valid_xlsx_is_accepted_even_when_sniffed_as_a_zip(): void
    {
        $event = $this->createEvent();
        $user = $this->createUserWithRole('admin');

        // An .xlsx really is a ZIP container; some hosts' libmagic reports it as
        // such, which a mimes:xlsx rule would reject.
        $response = $this->actingAs($user)->post("/events/{$event->id}/teams/import", [
            'file' => $this->sheet([
                ['Trigram', 'Team Name'],
                ['EGY', 'Egypt'],
            ]),
        ]);

        $response->assertOk()->assertJson(['total' => 1]);
    }

    public function test_an_unreadable_file_returns_a_clear_422_not_a_500(): void
    {
        $event = $this->createEvent();
        $user = $this->createUserWithRole('admin');

        $response = $this->actingAs($user)->post("/events/{$event->id}/teams/import", [
            'file' => UploadedFile::fake()->createWithContent('notes.csv', "no,useful,headers\n1,2,3"),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Trigram', $response->json('message'));
    }

    public function test_a_disallowed_extension_is_still_rejected(): void
    {
        $event = $this->createEvent();
        $user = $this->createUserWithRole('admin');

        $this->actingAs($user)->post("/events/{$event->id}/teams/import", [
            'file' => UploadedFile::fake()->createWithContent('payload.php', '<?php echo 1;'),
        ])->assertStatus(302); // redirected back with validation errors
    }

    private function sheet(array $grid): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($grid, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'teams.xlsx', null, null, true);
    }
}
