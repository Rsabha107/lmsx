<?php

namespace Tests\Feature;

use App\Mail\DailySummaryMail;
use App\Models\Event;
use App\Models\JobIssue;
use App\Models\User;
use App\Services\DailySummaryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Daily Email: the per-event audience (Settings → Notifications) and the compact content. */
class DailyEmailTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->event = $this->createEvent();
        $this->admin = $this->createUserWithRole('admin');
        Carbon::setTestNow('2026-11-20 08:00:00');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession(['active_event_id' => $this->event->id]);
    }

    private function build(): array
    {
        return app(DailySummaryService::class)->build($this->event->id, '2026-11-20');
    }

    public function test_an_admin_picks_the_audience_and_the_change_is_audited(): void
    {
        [$a, $b] = [User::factory()->create(), User::factory()->create()];

        $this->asAdmin()->post('/setups/settings/daily-email', ['user_ids' => [$a->id, $b->id]])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $this->event->dailyEmailRecipients()->pluck('users.id')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Daily Email audience changed']);

        $this->asAdmin()->get('/setups/settings')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dailyEmail.event.id', $this->event->id)
            ->where('dailyEmail.recipientIds', fn ($ids) => collect($ids)->sort()->values()->all() === collect([$a->id, $b->id])->sort()->values()->all()));

        $transport = $this->createUserWithRole('transport');
        $this->actingAs($transport)->withSession(['active_event_id' => $this->event->id])
            ->post('/setups/settings/daily-email', ['user_ids' => []])->assertForbidden();
    }

    public function test_without_an_audience_nothing_is_sent(): void
    {
        $this->event->users()->attach(User::factory()->create()->id);

        $this->asAdmin()->get('/email')->assertInertia(fn (AssertableInertia $page) => $page->where('recipients', [])->where('canManageAudience', true));
        $this->asAdmin()->post('/email/send')->assertSessionHas('error');
        Mail::assertNothingSent();
    }

    public function test_it_goes_only_to_active_chosen_users_with_the_attention_count_in_the_subject(): void
    {
        $chosen = User::factory()->create(['email' => 'chosen@example.com']);
        $inactive = User::factory()->create(['email' => 'off@example.com', 'is_active' => false]);
        User::factory()->create(['email' => 'assigned-only@example.com'])->events()->attach($this->event->id);
        $this->event->dailyEmailRecipients()->attach([$chosen->id, $inactive->id]);

        $team = $this->createTeam($this->event);
        $this->createMovement($this->event, $this->createPlan($this->event), $team, ['window_start' => '2026-11-20 10:00', 'window_end' => '2026-11-20 10:30']);

        $this->asAdmin()->post('/email/send')->assertSessionHas('success');

        Mail::assertSent(DailySummaryMail::class, function (DailySummaryMail $mail) {
            return $mail->hasTo('chosen@example.com') && ! $mail->hasTo('off@example.com') && ! $mail->hasTo('assigned-only@example.com')
                && str_starts_with($mail->envelope()->subject, '⚠ 2 items need attention');
        });
    }

    public function test_attention_counts_missing_crew_jobs_conflicts_and_open_issues_for_today_and_tomorrow(): void
    {
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $vehicle = $this->createVehicle($this->event);
        $driver = $this->createDriver($this->event);
        $supervisor = $this->createProviderUser('ground_control');
        $crew = ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'field_supervisor_id' => $supervisor->id];

        // Fully crewed with a job: nothing to flag.
        $ok = $this->createMovement($this->event, $plan, $team, $crew + ['window_start' => '2026-11-20 06:00', 'window_end' => '2026-11-20 06:30']);
        $this->createJob($this->event, $ok, $team);
        // Tomorrow, no driver and no job: two items.
        $this->createMovement($this->event, $plan, $team, ['vehicle_id' => $vehicle->id, 'field_supervisor_id' => $supervisor->id, 'window_start' => '2026-11-21 09:00']);
        // The day after tomorrow, and a cancelled one today: outside the email.
        $this->createMovement($this->event, $plan, $team, ['window_start' => '2026-11-22 09:00']);
        $this->createMovement($this->event, $plan, $team, ['window_start' => '2026-11-20 09:00', 'status' => 'cancelled']);
        // The same driver twice at once today: a high-severity clash.
        foreach (['11:00', '11:10'] as $time) {
            $clash = $this->createMovement($this->event, $plan, $team, $crew + ['window_start' => "2026-11-20 {$time}", 'window_end' => "2026-11-20 12:00", 'from_location' => "Hotel {$time}"]);
            $this->createJob($this->event, $clash, $team);
        }
        $issue = JobIssue::create(['job_id' => $ok->job->id, 'event_id' => $this->event->id, 'type' => 'vehicle_breakdown', 'severity' => 'danger']);
        $issue->forceFill(['created_at' => '2026-11-18 06:00'])->save();

        $blocks = collect($this->build()['attention'])->keyBy('key');

        $this->assertSame(1, $blocks['crew']['count']);
        $this->assertStringContainsString('no driver', $blocks['crew']['rows'][0]);
        $this->assertSame(1, $blocks['jobs']['count']);
        $this->assertGreaterThanOrEqual(1, $blocks['conflicts']['count']);
        $this->assertSame(1, $blocks['issues']['count']);
        $this->assertStringContainsString('open 2 d 2 h', $blocks['issues']['rows'][0]);
        $this->assertStringContainsString('/crew-assignment?date=2026-11-20', $blocks['crew']['link']);
    }

    public function test_yesterday_counts_only_that_days_checkpoints_and_the_table_shows_the_movements_own_vehicle(): void
    {
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $job = $this->createJob($this->event, $this->createMovement($this->event, $plan, $team, ['window_start' => '2026-11-19 09:00']), $team, ['status' => 'completed']);
        $make = fn (array $a) => $this->createCheckpoint($this->event, $job)->forceFill($a)->save();
        $make(['scheduled_at' => '2026-11-19 09:00', 'state' => 'done', 'is_on_time' => true, 'delay_minutes' => 0]);
        $make(['scheduled_at' => '2026-11-19 10:00', 'state' => 'done', 'is_on_time' => false, 'delay_minutes' => 20]);
        $make(['scheduled_at' => '2026-11-19 11:00', 'state' => 'skipped']);
        // Future checkpoints never count against yesterday.
        $make(['scheduled_at' => '2026-11-25 10:00', 'state' => 'pending']);

        $vehicle = $this->createVehicle($this->event, ['code' => 'BUS-7']);
        $this->createMovement($this->event, $plan, $team, ['vehicle_id' => $vehicle->id, 'window_start' => '2026-11-20 14:00']);

        $summary = $this->build();
        $y = $summary['yesterday'];

        $this->assertSame([1, 1], [$y['jobsPlanned'], $y['jobsCompleted']]);
        $this->assertSame([2, 50, 20, 1], [$y['judged'], $y['onTimePct'], $y['avgDelay'], $y['skipped']]);
        $this->assertSame('BUS-7', $summary['schedule'][0]['vehicle']);
        $this->assertSame(1, $summary['glance']['movements']);
        $this->assertSame('14:00–15:00', $summary['glance']['busiestHour']);
    }
}
