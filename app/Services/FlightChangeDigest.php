<?php

namespace App\Services;

use App\Mail\FlightChangesDigestMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;

/**
 * Collects the jobs a flight change touched during one save, bulk refresh, import or scheduled sync
 * (FlightJobRetimer adds them), then tells people once: one push per supervisor (merged when several of
 * their jobs changed) and one email per planner listing every job that moved at least the
 * `retime_email_minutes` threshold. Flushed when the request or command ends (bound per scope).
 */
class FlightChangeDigest
{
    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    private bool $flushRegistered = false;

    public function __construct(private SettingsService $settings, private JobPushNotifier $push) {}

    /**
     * @param  array{event_id: int, job_id: int, job_ref: string, team: ?string, kind: string, flight: string,
     *               shift: ?int, start_before: ?string, start_after: ?string, clashes: array<int, string>, supervisor_ids: array<int, int>}  $item
     */
    public function add(array $item): void
    {
        $this->items[] = $item;
        if (! $this->flushRegistered) {
            $this->flushRegistered = true;
            // Runs after the response (or when the command ends); later items are flushed by the same callback.
            app()->terminating(fn () => $this->flush());
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function pending(): array
    {
        return $this->items;
    }

    public function flush(): void
    {
        $items = $this->items;
        $this->items = [];
        if ($items === []) {
            return;
        }

        $this->pushes($items);
        $this->emails($items);
    }

    /** One push per supervisor: the job's own text, or a merged one when several of theirs changed. */
    private function pushes(array $items): void
    {
        $byUser = [];
        foreach ($items as $item) {
            foreach ($item['supervisor_ids'] as $userId) {
                $byUser[$userId][] = $item;
            }
        }
        foreach ($byUser as $userId => $mine) {
            $this->push->flightChangesFor((int) $userId, $mine);
        }
    }

    /** One email per planner (plans.manage, active, with access to the event) for jobs that moved enough. */
    private function emails(array $items): void
    {
        $threshold = $this->settings->getThreshold('retime_email_minutes');
        if ($threshold <= 0 || ! Permission::where('name', 'plans.manage')->where('guard_name', 'web')->exists()) {
            return;
        }
        $worth = array_values(array_filter($items, fn ($i) => $i['shift'] !== null && abs($i['shift']) >= $threshold));
        if ($worth === []) {
            return;
        }

        $planners = User::permission('plans.manage')->where('is_active', true)->whereNotNull('email')->get();
        foreach ($planners as $user) {
            $theirs = array_values(array_filter($worth, fn ($i) => $user->canAccessEvent($i['event_id'])));
            if ($theirs === []) {
                continue;
            }
            try {
                Mail::to($user->email)->send(new FlightChangesDigestMail($theirs));
            } catch (\Throwable $e) {
                Log::warning('Flight changes digest mail failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
