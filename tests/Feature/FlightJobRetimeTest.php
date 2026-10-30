<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Mail\FlightChangesDigestMail;
use App\Models\Checkpoint;
use App\Models\CheckpointTemplate;
use App\Models\Country;
use App\Models\Event;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\MovementTemplate;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamFlight;
use App\Models\User;
use App\Services\FlightJobRetimer;
use App\Services\NotificationFeedService;
use App\Services\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** A flight's new time under a generated job: re-timed when not started, flagged when started; supervisors, feed and email. */
class FlightJobRetimeTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    private User $admin;

    private User $supervisor;

    private Team $team;

    private TeamFlight $flight;

    private JobOperation $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->event = $this->createEvent();
        $this->admin = User::factory()->create(['email' => 'planner@example.com']);
        $this->admin->assignRole('admin');

        $meet = Checkpoint::create(['code' => 'CP-MEET', 'name' => 'Meet the team', 'type' => 'manual', 'is_active' => true]);
        $hotel = Checkpoint::create(['code' => 'CP-HOTEL', 'name' => 'Arrive at hotel', 'type' => 'manual', 'is_active' => true]);
        $sequence = CheckpointTemplate::create(['event_id' => $this->event->id, 'code' => 'CT-ARR', 'name' => 'Arrival', 'movement_type' => 'arrival', 'is_active' => true]);
        $sequence->checkpoints()->attach($meet->id, ['order' => 1, 'is_required' => true]);
        $sequence->checkpoints()->attach($hotel->id, ['order' => 2, 'is_required' => true]);
        $template = MovementTemplate::create(['event_id' => $this->event->id, 'code' => 'MVT-ARR', 'name' => 'Team Arrival', 'scenario_type' => 'arrival_day', 'is_active' => true]);
        $template->legs()->create(['order' => 1, 'leg_type' => 'arrival', 'checkpoint_template_id' => $sequence->id, 'estimated_duration_minutes' => 60,
            'from_location' => 'Airport', 'to_location' => 'Team hotel']);

        // The job starts 1 h before landing; the hotel step is 1 h after.
        $settings = app(SettingsService::class);
        $settings->setSetting('movement_offset.arrival', -60, Setting::SCOPE_GLOBAL);
        $settings->setSetting('movement_offset.arrival', -60, Setting::SCOPE_GLOBAL, null, null, $meet->id);
        $settings->setSetting('movement_offset.arrival', 60, Setting::SCOPE_GLOBAL, null, null, $hotel->id);

        $this->team = $this->createTeam($this->event);
        $this->flight = TeamFlight::create(['event_id' => $this->event->id, 'team_id' => $this->team->id, 'direction' => 'arrival',
            'flight_number' => 'QR 194', 'scheduled_at' => '2026-11-16 09:09:00', 'party_size_total' => 30]);

        $this->actingAs($this->admin)->withSession(['active_event_id' => $this->event->id])
            ->post('/plans/bulk', ['plans' => [[
                'date' => '2026-11-16',
                'movement_template_id' => $template->id,
                'teams' => [['team_id' => $this->team->id, 'start_time' => '09:00']],
            ]]])
            ->assertSessionHasNoErrors();

        $movement = Movement::where('team_id', $this->team->id)->firstOrFail();
        $this->supervisor = $this->createProviderUser('ground_control');
        $movement->update(['vehicle_id' => $this->createVehicle($this->event)->id, 'driver_id' => $this->createDriver($this->event)->id,
            'field_supervisor_id' => $this->supervisor->id]);
        $this->post("/plans/{$movement->plan_id}/generate-jobs", ['movement_ids' => [$movement->id]])->assertSessionHasNoErrors();
        $this->job = JobOperation::where('movement_id', $movement->id)->firstOrFail();
        $this->assertSame(['08:09', '10:09'], $this->times());
    }

    private function times(): array
    {
        return $this->job->checkpoints()->orderBy('order')->get()->map(fn ($cp) => $cp->scheduled_at?->format('H:i'))->all();
    }

    private function moveFlightTo(string $time)
    {
        $country = Country::where('country_code', $this->team->country_id)->value('country_code');

        return $this->actingAs($this->admin)
            ->put("/events/{$this->event->id}/teams/{$this->team->code}/profile", [
                'team' => ['code' => $this->team->code, 'team_name' => $this->team->team_name, 'country_id' => $country, 'role' => 'team'],
                'arrival' => ['id' => $this->flight->id, 'date' => '2026-11-16', 'time' => $time, 'flight_number' => 'QR 194', 'status' => 'auto'],
                'departure' => null,
                'trainings' => [],
            ]);
    }

    public function test_a_job_not_started_is_re_timed_and_everyone_is_told(): void
    {
        Bus::fake([SendPushNotification::class]);
        Mail::fake();

        $this->moveFlightTo('05:45')
            ->assertSessionHas('success', 'Team updated. 1 job re-timed and supervisors notified.')
            ->assertSessionHas('flight_change', fn ($c) => $c['movements'][0]['status'] === 'job_retimed');

        $this->assertSame(['04:45', '06:45'], $this->times(), 'every checkpoint follows the new landing time');
        $this->assertSame('2026-11-16 04:45', $this->job->movement->refresh()->window_start->format('Y-m-d H:i'));

        Bus::assertDispatchedAfterResponse(SendPushNotification::class, fn ($push) => $push->type === 'job_flight_changed'
            && $push->userIds === [$this->supervisor->id] && str_contains($push->body, 'Now starts Mon 16 Nov 04:45'));
        $this->assertDatabaseHas('audit_logs', ['action' => FlightJobRetimer::RETIMED, 'auditable_id' => $this->job->id]);

        $feed = collect(app(NotificationFeedService::class)->recent($this->event->id));
        $alert = $feed->first(fn ($a) => str_starts_with($a['title'], 'Job re-timed: '.$this->job->job_id));
        $this->assertNotNull($alert);
        $this->assertSame('QR 194 3h 24m earlier · job now starts Mon 16 Nov 04:45.', $alert['body']);
        $this->assertSame(['number' => 'QR 194', 'from' => 'Mon 16 Nov 09:09', 'to' => 'Mon 16 Nov 05:45', 'shift' => '3h 24m earlier'], $alert['detail']['flight']);
        $this->assertSame('/job/'.$this->job->job_id, $alert['detail']['url']);

        // 3 h 24 earlier is over the 60-minute email threshold.
        Mail::assertSent(FlightChangesDigestMail::class, fn ($mail) => $mail->hasTo('planner@example.com') && count($mail->items) === 1
            && $mail->items[0]['kind'] === 'retimed' && $mail->items[0]['shift'] === -204 && $mail->items[0]['start_after'] === 'Mon 16 Nov 04:45');
    }

    public function test_a_small_shift_re_times_without_an_email(): void
    {
        Bus::fake([SendPushNotification::class]);
        Mail::fake();

        $this->moveFlightTo('09:39');
        $this->assertSame(['08:39', '10:39'], $this->times());
        Mail::assertNothingSent();
    }

    public function test_an_email_threshold_of_0_sends_none(): void
    {
        Bus::fake([SendPushNotification::class]);
        Mail::fake();
        app(SettingsService::class)->setSetting('conflict.retime_email_minutes', 0, Setting::SCOPE_GLOBAL);

        $this->moveFlightTo('05:45');
        $this->assertSame(['04:45', '06:45'], $this->times());
        Mail::assertNothingSent();
    }

    public function test_a_started_job_keeps_its_times_and_is_flagged(): void
    {
        Bus::fake([SendPushNotification::class]);
        Mail::fake();
        $this->job->checkpoints()->orderBy('order')->first()->update(['state' => 'done', 'completed_at' => '2026-11-16 08:10:00']);

        $this->moveFlightTo('05:45')
            ->assertSessionHas('success', 'Team updated. 1 movement already has a job and must be updated by hand.');

        $this->assertSame(['08:09', '10:09'], $this->times());
        Bus::assertDispatchedAfterResponse(SendPushNotification::class, fn ($push) => $push->type === 'job_flight_changed' && $push->title === 'Flight changed: check your job');
        $this->assertDatabaseHas('audit_logs', ['action' => FlightJobRetimer::REVIEW, 'auditable_id' => $this->job->id]);
        Mail::assertSent(FlightChangesDigestMail::class, fn ($mail) => $mail->items[0]['kind'] === 'review');
    }

    public function test_one_run_sends_one_merged_push_per_supervisor_and_one_email_per_planner(): void
    {
        Bus::fake([SendPushNotification::class]);
        Mail::fake();
        $digest = app(\App\Services\FlightChangeDigest::class);
        $item = fn (string $ref, string $kind, int $shift) => [
            'event_id' => $this->event->id, 'job_id' => $this->job->id, 'job_ref' => $ref, 'team' => 'Kuwait', 'kind' => $kind,
            'flight' => "QR {$ref}", 'shift' => $shift, 'start_before' => 'Mon 16 Nov 08:09', 'start_after' => 'Mon 16 Nov 04:45',
            'clashes' => [], 'supervisor_ids' => [$this->supervisor->id],
        ];

        $digest->add($item('JOB-1', 'retimed', -204));
        $digest->add($item('JOB-2', 'retimed', -90));
        $digest->add($item('JOB-3', 'review', 20));
        $digest->flush();

        Bus::assertDispatchedAfterResponseTimes(SendPushNotification::class, 1);
        Bus::assertDispatchedAfterResponse(SendPushNotification::class, fn ($push) => $push->title === '3 of your jobs changed with their flights'
            && str_contains($push->body, 'JOB-1 · Kuwait now Mon 16 Nov 04:45') && str_contains($push->body, 'JOB-3 · Kuwait needs a check'));

        // One email for the planner, with the two jobs over the 60-minute threshold.
        Mail::assertSentCount(1);
        Mail::assertSent(FlightChangesDigestMail::class, fn ($mail) => $mail->hasTo('planner@example.com')
            && array_column($mail->items, 'job_ref') === ['JOB-1', 'JOB-2'] && $mail->counts() === '2 jobs re-timed');

        $digest->flush();
        Mail::assertSentCount(1);
    }

    public function test_the_digest_email_renders_flights_jobs_and_clashes(): void
    {
        $html = (new FlightChangesDigestMail([[
            'event_id' => $this->event->id, 'job_id' => $this->job->id, 'job_ref' => 'JOB-1', 'team' => 'China', 'kind' => 'retimed',
            'flight' => 'QR893 Wed 14 Oct 05:40 → Wed 14 Oct 08:40', 'shift' => 180,
            'start_before' => 'Wed 14 Oct 04:40', 'start_after' => 'Wed 14 Oct 07:40', 'supervisor_ids' => [],
            'clashes' => [['type' => 'Tight Turnaround', 'text' => 'V-1 has only 25 minutes between TRP-1 and TRP-2 — no margin for traffic or loading.']],
        ]]))->render();

        $this->assertStringContainsString('3h later', $html);
        $this->assertStringContainsString('>05:40<', $html);
        $this->assertStringContainsString('>07:40<', $html);
        $this->assertStringContainsString('Tight Turnaround', $html);
        $this->assertStringContainsString('no margin for traffic or loading', $html);
        $this->assertStringContainsString('/job/JOB-1', $html);
    }

    public function test_an_older_alert_with_text_clashes_still_reads_as_fields(): void
    {
        \App\Models\AuditLog::create([
            'user_name' => 'System', 'user_role' => 'Automation', 'action' => FlightJobRetimer::RETIMED, 'event_id' => $this->event->id,
            'target' => 'JOB-20261030-0002 · China',
            'meta' => json_encode([
                'flight' => 'QR893 Wed 14 Oct 05:40 → Wed 14 Oct 09:40',
                'start' => 'Wed 14 Oct 04:40 → Wed 14 Oct 08:40',
                'checkpoints' => 2,
                'clashes' => ['5 Ton Truck-05-332045 is booked on TRP-00525 (Wed 08:40–12:40) and TRP-00195 (Wed 12:05–16:05) — 35 minutes overlap.'],
            ]),
        ]);

        $alert = collect(app(NotificationFeedService::class)->recent($this->event->id))->firstWhere('title', 'Job re-timed: JOB-20261030-0002 · China');

        $this->assertSame('QR893 4h later · job now starts Wed 14 Oct 08:40 · 1 clash to check.', $alert['body']);
        $this->assertSame(['from' => 'Wed 14 Oct 04:40', 'to' => 'Wed 14 Oct 08:40'], $alert['detail']['start']);
        $this->assertSame('QR893', $alert['detail']['flight']['number']);
        $this->assertNull($alert['detail']['clashes'][0]['type']);
        $this->assertStringStartsWith('5 Ton Truck', $alert['detail']['clashes'][0]['text']);
        $this->assertSame('danger', $alert['tone']);
    }

    public function test_a_finished_job_is_left_alone(): void
    {
        Bus::fake([SendPushNotification::class]);
        $this->job->update(['status' => 'completed']);

        $this->moveFlightTo('05:45')->assertSessionHas('success', 'Team updated.');

        $this->assertSame(['08:09', '10:09'], $this->times());
        Bus::assertNotDispatched(SendPushNotification::class);
    }
}
