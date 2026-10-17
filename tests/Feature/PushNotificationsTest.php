<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Checkpoint;
use App\Models\Setting;
use App\Models\TeamFlight;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\JobLifecycleService;
use App\Services\SettingsService;
use App\Support\Push\FcmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class PushNotificationsTest extends TestCase
{
    use CreatesOperationsFixtures;
    use RefreshDatabase;

    /** A throwaway service account so FcmClient is switched on; Google is faked. */
    private function enableFcm(): void
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        // PHP on Windows ships its openssl.cnf beside the binary instead of where OpenSSL looks.
        $cnf = getenv('OPENSSL_CONF') ?: dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (is_file($cnf)) {
            $options['config'] = $cnf;
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $pem, null, $options);
        $path = storage_path('framework/testing/fcm-test.json');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode([
            'project_id' => 'naqla-test',
            'client_email' => 'push@naqla-test.iam.gserviceaccount.com',
            'private_key' => $pem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.fcm.credentials' => $path]);
    }

    private function pushed(string $type, array $userIds): \Closure
    {
        return fn (SendPushNotification $push) => $push->type === $type && $push->userIds == $userIds;
    }

    public function test_a_phone_registers_its_token_and_signing_out_drops_it(): void
    {
        $user = $this->createUserWithRole('ground_control');
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/mobile/devices', ['token' => 'fcm-token-1', 'platform' => 'android'])->assertOk();
        $this->assertDatabaseHas('user_devices', ['user_id' => $user->id, 'token_hash' => UserDevice::hashToken('fcm-token-1')]);

        // The same phone signed in by someone else moves to them.
        $other = $this->createUserWithRole('ground_control');
        $this->app['auth']->forgetGuards();
        $this->withToken($other->createToken('phone')->plainTextToken)
            ->postJson('/api/mobile/devices', ['token' => 'fcm-token-1', 'platform' => 'android'])->assertOk();
        $this->assertSame($other->id, UserDevice::sole()->user_id);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/mobile/devices', ['token' => 'fcm-token-2', 'platform' => 'android'])->assertOk();
        $this->withToken($token)->postJson('/api/mobile/logout')->assertOk();

        $this->assertDatabaseMissing('user_devices', ['token_hash' => UserDevice::hashToken('fcm-token-2')]);
        $this->assertDatabaseHas('user_devices', ['token_hash' => UserDevice::hashToken('fcm-token-1')]);
    }

    public function test_supervisors_added_to_or_taken_off_a_job_are_told(): void
    {
        Bus::fake([SendPushNotification::class]);
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        [$old, $lead, $extra] = [$this->createUserWithRole('ground_control'), $this->createUserWithRole('ground_control'), $this->createUserWithRole('ground_control')];
        $movement = $this->createMovement($event, $this->createPlan($event), $team, ['field_supervisor_id' => $old->id]);
        $this->createJob($event, $movement, $team, ['supervisor_id' => $old->id]);

        $this->actingAs($this->createUserWithRole('admin'));
        app(JobLifecycleService::class)->assignMovementCrew($movement, null, null, $lead->id, null, [$extra->id]);

        Bus::assertDispatchedAfterResponse(SendPushNotification::class, $this->pushed('job_assigned', [$lead->id, $extra->id]));
        Bus::assertDispatchedAfterResponse(SendPushNotification::class, $this->pushed('job_removed', [$old->id]));
    }

    public function test_nobody_is_told_about_a_movement_without_a_job_or_about_their_own_change(): void
    {
        Bus::fake([SendPushNotification::class]);
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $supervisor = $this->createUserWithRole('ground_control');
        $movement = $this->createMovement($event, $this->createPlan($event), $team);

        $this->actingAs($this->createUserWithRole('admin'));
        app(JobLifecycleService::class)->assignMovementCrew($movement, null, null, $supervisor->id);
        Bus::assertNothingDispatched();

        $this->createJob($event, $movement->refresh(), $team, ['supervisor_id' => $supervisor->id]);
        $this->actingAs($supervisor);
        app(JobLifecycleService::class)->assignMovementCrew($movement->refresh(), null, null, $supervisor->id, null, [$this->createUserWithRole('ground_control')->id]);
        Bus::assertDispatchedAfterResponseTimes(SendPushNotification::class, 1);
    }

    public function test_a_re_timed_job_tells_its_supervisor(): void
    {
        Bus::fake([SendPushNotification::class]);
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $supervisor = $this->createUserWithRole('ground_control');
        $flight = TeamFlight::create(['event_id' => $event->id, 'team_id' => $team->id, 'direction' => 'arrival', 'flight_number' => 'QR100', 'scheduled_at' => '2026-11-08 04:00:00']);
        $movement = $this->createMovement($event, $this->createPlan($event), $team, [
            'kind' => 'arrival', 'flight_id' => $flight->id, 'flight_number' => 'QR100', 'field_supervisor_id' => $supervisor->id,
            'window_start' => '2026-11-08 03:00:00', 'window_end' => '2026-11-08 04:30:00',
        ]);
        $job = $this->createJob($event, $movement, $team, ['supervisor_id' => $supervisor->id]);
        $template = Checkpoint::create(['code' => 'CKP-POA', 'name' => 'ARRIVAL TIME AT POA STAGING']);
        app(SettingsService::class)->setSetting('movement_offset.arrival', -90, Setting::SCOPE_GLOBAL, null, null, $template->id);
        app(SettingsService::class)->setSetting('movement_offset.arrival', -60);
        $this->createCheckpoint($event, $job, ['checkpoint_id' => $template->id, 'name' => $template->name, 'scheduled_at' => '2026-11-08 03:00:00']);

        $this->actingAs($this->createUserWithRole('admin'));
        app(JobLifecycleService::class)->syncOffsets($job);

        Bus::assertDispatchedAfterResponse(SendPushNotification::class, $this->pushed('job_retimed', [$supervisor->id]));
    }

    public function test_start_reminders_go_out_once_at_60_and_again_at_15_minutes(): void
    {
        $this->enableFcm();
        Bus::fake([SendPushNotification::class]);
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $supervisor = $this->createUserWithRole('ground_control');
        $movement = $this->createMovement($event, $this->createPlan($event), $team, ['field_supervisor_id' => $supervisor->id]);
        $job = $this->createJob($event, $movement, $team, ['supervisor_id' => $supervisor->id]);
        $this->createCheckpoint($event, $job, ['scheduled_at' => now()->addMinutes(50)->startOfMinute()]);

        $this->artisan('push:job-reminders')->assertSuccessful();
        $this->artisan('push:job-reminders')->assertSuccessful();
        Bus::assertDispatchedAfterResponseTimes(SendPushNotification::class, 1);
        Bus::assertDispatchedAfterResponse(SendPushNotification::class, $this->pushed('job_starting', [$supervisor->id]));

        $this->travel(40)->minutes();
        $this->artisan('push:job-reminders')->assertSuccessful();
        Bus::assertDispatchedAfterResponseTimes(SendPushNotification::class, 2);
    }

    public function test_no_reminders_while_push_is_not_configured(): void
    {
        Bus::fake([SendPushNotification::class]);
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $supervisor = $this->createUserWithRole('ground_control');
        $movement = $this->createMovement($event, $this->createPlan($event), $team, ['field_supervisor_id' => $supervisor->id]);
        $job = $this->createJob($event, $movement, $team);
        $this->createCheckpoint($event, $job, ['scheduled_at' => now()->addMinutes(30)]);

        $this->artisan('push:job-reminders')->assertSuccessful();

        Bus::assertNothingDispatched();
        $this->assertDatabaseCount('push_notification_log', 0);
    }

    public function test_a_push_reaches_each_device_and_a_dead_token_is_dropped(): void
    {
        $this->enableFcm();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::sequence()
                ->push(['name' => 'projects/naqla-test/messages/1'])
                ->push(['error' => ['status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.']], 404),
        ]);
        $user = User::factory()->create();
        foreach (['live-token', 'dead-token'] as $token) {
            UserDevice::create(['user_id' => $user->id, 'token' => $token, 'token_hash' => UserDevice::hashToken($token), 'platform' => 'android']);
        }

        (new SendPushNotification([$user->id], 'job_retimed', 'Job times changed', 'JOB-1 · Qatar', ['job_id' => 7]))
            ->handle(app(FcmClient::class));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/projects/naqla-test/messages:send')
            && $request->hasHeader('Authorization', 'Bearer ya29.test')
            && $request['message']['token'] === 'live-token'
            && $request['message']['notification']['title'] === 'Job times changed'
            && $request['message']['data'] === ['type' => 'job_retimed', 'job_id' => '7']);
        $this->assertDatabaseHas('user_devices', ['token_hash' => UserDevice::hashToken('live-token')]);
        $this->assertDatabaseMissing('user_devices', ['token_hash' => UserDevice::hashToken('dead-token')]);
    }
}
