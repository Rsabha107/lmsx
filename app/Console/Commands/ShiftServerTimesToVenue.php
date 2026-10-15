<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off, for the switch of the app clock from UTC to venue time: moves every value the
 * server clock stamped in UTC onto venue time. Venue wall-clock values (plans, flights,
 * checkpoint schedules, typed or device completion times) already are, and are left alone.
 */
class ShiftServerTimesToVenue extends Command
{
    protected $signature = 'time:shift-to-venue {--apply : Write the changes (default is a dry run)}';

    protected $description = 'Move server-clock (UTC) timestamps onto venue time, once, after APP_TIMEZONE becomes the venue zone';

    public const MARKER = 'system.server_times_shifted_to_venue';

    /** Server-clock columns other than created_at / updated_at / deleted_at. */
    private const COLUMNS = [
        'failed_jobs' => ['failed_at'],
        'users' => ['email_verified_at'],
        'personal_access_tokens' => ['last_used_at', 'expires_at'],
        'jobs_operations' => ['dispatched_at'],
        'movements' => ['job_generated_at'],
        'job_checkpoints' => ['skipped_at', 'overridden_at'],
        'job_issues' => ['resolved_at'],
        'team_flights' => ['flight_synced_at'],
        'teams' => ['flight_synced_at'],
    ];

    /**
     * Columns stamped by the server clock only for some rows: checkpoints when the device sent no
     * time (event_at), and job/movement start and end unless copied from a checkpoint (commit 13272a3).
     */
    private const CONDITIONAL = [
        ['job_checkpoints', 'received_at', 'event_at IS NULL'],
        ['job_checkpoints', 'completed_at', "time_source = 'server' AND event_at IS NULL"],
        ['jobs_operations', 'started_at', "NOT (started_at <=> (SELECT MIN(c.completed_at) FROM job_checkpoints c WHERE c.job_id = jobs_operations.id AND c.state = 'done'))"],
        ['jobs_operations', 'completed_at', "NOT (completed_at <=> (SELECT MAX(c.completed_at) FROM job_checkpoints c WHERE c.job_id = jobs_operations.id AND c.state = 'done'))"],
        ['movements', 'actual_departure', "NOT (actual_departure <=> (SELECT MIN(c.completed_at) FROM job_checkpoints c JOIN jobs_operations j ON j.id = c.job_id WHERE j.movement_id = movements.id AND c.state = 'done'))"],
        ['movements', 'actual_arrival', "NOT (actual_arrival <=> (SELECT MAX(c.completed_at) FROM job_checkpoints c JOIN jobs_operations j ON j.id = c.job_id WHERE j.movement_id = movements.id AND c.state = 'done'))"],
    ];

    public function handle(): int
    {
        $hours = intdiv(now()->utcOffset(), 60);
        if ($hours === 0) {
            $this->error('APP_TIMEZONE is UTC: switch it to the venue zone first.');

            return self::FAILURE;
        }

        if (Setting::where('key', self::MARKER)->exists()) {
            $this->error('Already applied on '.Setting::where('key', self::MARKER)->value('value').'. It must only run once.');

            return self::FAILURE;
        }

        $plan = $this->plan();
        $rows = [];
        foreach ($plan as [$table, $column, $where]) {
            $rows[] = [$table, $column, $where ?? 'all', DB::table($table)->whereNotNull($column)->when($where, fn ($q) => $q->whereRaw($where))->count()];
        }
        $this->table(['Table', 'Column', 'Rows', 'Values to shift'], $rows);

        $unknown = DB::table('job_checkpoints')->where('state', 'done')->whereNull('time_source')->whereNotNull('completed_at')->count();
        $this->line(sprintf('  +%d hours. Left alone: %d completed checkpoint(s) from before time_source was recorded (typed or server time cannot be told apart).', $hours, $unknown));

        if (! $this->option('apply')) {
            $this->warn('Dry run. Re-run with --apply (in maintenance mode, after a backup) to write these changes.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan, $hours) {
            foreach ($plan as [$table, $column, $where]) {
                DB::table($table)
                    ->whereNotNull($column)
                    ->when($where, fn ($q) => $q->whereRaw($where))
                    ->update([$column => DB::raw("`{$column}` + INTERVAL {$hours} HOUR")]);
            }

            Setting::create(['key' => self::MARKER, 'scope' => Setting::SCOPE_GLOBAL, 'value' => now()->format('Y-m-d H:i:s'), 'description' => "Server-clock timestamps moved +{$hours}h to venue time"]);
        });

        AuditLog::change('Server timestamps moved to venue time', "+{$hours}h", ['columns' => count($plan)]);
        $this->info('Done.');

        return self::SUCCESS;
    }

    /** @return list<array{0: string, 1: string, 2: ?string}> */
    private function plan(): array
    {
        $generic = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->whereIn('column_name', ['created_at', 'updated_at', 'deleted_at'])
            ->whereIn('data_type', ['timestamp', 'datetime'])
            ->orderBy('table_name')
            ->get(['table_name as t', 'column_name as c'])
            ->map(fn ($r) => [$r->t, $r->c, null])
            ->all();

        $explicit = [];
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $explicit[] = [$table, $column, null];
            }
        }

        return [...$generic, ...$explicit, ...self::CONDITIONAL];
    }
}
