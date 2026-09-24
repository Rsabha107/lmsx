<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class MenuVisibilityTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_the_jobs_mobile_menu_is_shown_by_default(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)->get('/setups/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('uiFlags.jobsMobileMenu', true));
    }

    public function test_an_admin_can_hide_the_menu(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->post('/setups/settings/ui-flag', ['key' => SettingsService::FLAG_JOBS_MOBILE_MENU, 'enabled' => false])
            ->assertRedirect();

        $this->assertDatabaseHas('pma_settings', [
            'key' => SettingsService::FLAG_JOBS_MOBILE_MENU,
            'scope' => Setting::SCOPE_GLOBAL,
            'value' => '0',
        ]);

        $this->actingAs($admin)->get('/setups/settings')
            ->assertInertia(fn ($page) => $page->where('ui.jobsMobileMenu', false));
    }

    public function test_hiding_the_menu_closes_the_route(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)->get('/jobs/mobile')->assertOk();

        $this->actingAs($admin)
            ->post('/setups/settings/ui-flag', ['key' => SettingsService::FLAG_JOBS_MOBILE_MENU, 'enabled' => false]);

        $this->actingAs($admin)->get('/jobs/mobile')->assertForbidden();
        $this->actingAs($admin)->get('/jobs/mobile/1')->assertForbidden();
    }

    public function test_a_mobile_only_user_is_told_why_rather_than_bounced_into_a_redirect(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->actingAs($admin)
            ->post('/setups/settings/ui-flag', ['key' => SettingsService::FLAG_JOBS_MOBILE_MENU, 'enabled' => false]);

        // 'transport' gets jobs.view but not console.view, so the dashboard
        // would normally redirect them straight to the mobile view.
        $fieldUser = $this->createUserWithRole('transport');

        $this->actingAs($fieldUser)->get('/')->assertForbidden();
    }

    public function test_the_settings_page_reports_who_would_be_locked_out(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->createUserWithRole('transport');

        $this->actingAs($admin)->get('/setups/settings')
            ->assertInertia(fn ($page) => $page->where('mobileOnlyUsers', 1));
    }

    public function test_only_a_known_flag_key_is_accepted(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->post('/setups/settings/ui-flag', ['key' => 'ui.something_else', 'enabled' => false])
            ->assertSessionHasErrors('key');
    }

    public function test_non_admins_cannot_change_menu_visibility(): void
    {
        $this->actingAs(\App\Models\User::factory()->create())
            ->post('/setups/settings/ui-flag', ['key' => SettingsService::FLAG_JOBS_MOBILE_MENU, 'enabled' => false])
            ->assertForbidden();
    }
}
