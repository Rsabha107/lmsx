<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileAppDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_agency_and_supervisors_download_the_apk(): void
    {
        Storage::disk('local')->put(config('app.mobile_apk'), 'apk-bytes');

        foreach (['admin', 'agency', 'ground_control'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get('/downloads/mobile-app')
                ->assertOk()
                ->assertDownload('NAQLA LMS - V(1.0.0).apk')
                ->assertHeader('Content-Type', 'application/vnd.android.package-archive');
        }
    }

    public function test_other_roles_are_refused(): void
    {
        Storage::disk('local')->put(config('app.mobile_apk'), 'apk-bytes');

        $this->actingAs($this->userWithRole('transport'))
            ->get('/downloads/mobile-app')
            ->assertForbidden();
    }

    public function test_a_missing_file_is_a_404(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/downloads/mobile-app')
            ->assertNotFound();
    }
}
