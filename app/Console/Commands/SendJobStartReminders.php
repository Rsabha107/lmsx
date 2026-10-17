<?php

namespace App\Console\Commands;

use App\Models\JobOperation;
use App\Services\JobPushNotifier;
use App\Support\Push\FcmClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Every minute: pushes "job starts in 60 min" and "in 15 min" to the supervisors
 * of jobs not started yet. push_notification_log keeps each reminder to one
 * send; the key carries the start time, so a re-timed job is reminded again.
 */
class SendJobStartReminders extends Command
{
    protected $signature = 'push:job-reminders';

    protected $description = 'Push "job starts soon" reminders to field supervisors';

    private const LEADS = [15, 60];

    public function handle(JobPushNotifier $notifier, FcmClient $fcm): int
    {
        if (! $fcm->enabled()) {
            return self::SUCCESS;
        }

        $now = now();
        $horizon = $now->copy()->addMinutes(max(self::LEADS));

        $jobs = JobOperation::with(['checkpoints:id,job_id,scheduled_at', 'movement', 'team'])
            ->whereIn('status', ['pending', 'dispatched'])
            ->where(fn ($q) => $q
                ->whereHas('checkpoints', fn ($c) => $c->whereBetween('scheduled_at', [$now, $horizon]))
                ->orWhereHas('movement', fn ($m) => $m->whereBetween('window_start', [$now, $horizon])))
            ->get();

        $sent = 0;
        foreach ($jobs as $job) {
            $startsAt = $notifier->startsAt($job);
            if (! $startsAt || $startsAt->lte($now) || $startsAt->gt($horizon)) {
                continue;
            }

            $minutes = $now->diffInMinutes($startsAt);
            $lead = collect(self::LEADS)->first(fn ($l) => $minutes <= $l);

            $due = [];
            foreach ($notifier->supervisorIds($job->movement_id) as $userId) {
                $key = "job_start:{$lead}:{$job->id}:{$startsAt->format('YmdHi')}:{$userId}";
                if (DB::table('push_notification_log')->insertOrIgnore(['key' => $key, 'user_id' => $userId, 'sent_at' => $now])) {
                    $due[] = $userId;
                }
            }

            if ($due !== []) {
                $notifier->startingSoon($job, $startsAt, $due);
                $sent += count($due);
            }
        }

        DB::table('push_notification_log')->where('sent_at', '<', $now->copy()->subDays(7))->delete();

        $this->info("{$sent} reminder(s) queued.");

        return self::SUCCESS;
    }
}
