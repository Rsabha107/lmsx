<?php

namespace App\Services;

use App\Mail\DeletionRequestMail;
use App\Models\AuditLog;
use App\Models\Deletion;
use App\Models\Event;
use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\MovementTemplate;
use App\Models\Plan;
use App\Models\Scopes\ProviderScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Exceptions\DeletionRefused;

/**
 * Every delete of jobs, plans, movements, movement templates or an event goes through here.
 * Nothing is removed outright: rows are soft-deleted into one recycle-bin batch (a Deletion),
 * which can be restored for 30 days and is then purged. Large or risky deletes, and anything
 * past a user's hourly limit, wait for a second admin's approval.
 */
class DeletionService
{
    /** More than this many in one request needs another admin's approval. */
    public const APPROVAL_OVER = ['jobs' => 10, 'plans' => 3];

    /** Per user, within BRAKE_MINUTES: past this, every further delete of the kind needs approval. */
    public const BRAKE = ['jobs' => 25, 'plans' => 5, 'movement_templates' => 3];
    public const BRAKE_MINUTES = 60;

    public const KEEP_DAYS = 30;
    public const REQUEST_HOURS = 24;

    /** Restore order: parents before children. Purge runs it backwards. */
    private const TABLE_ORDER = ['events', 'movement_templates', 'plans', 'movements', 'jobs'];

    /**
     * Ask to delete $items (already authorised and confirmed by the caller). Runs at once, or
     * waits for approval; the returned Deletion's status says which ('done' or 'pending').
     *
     * @param  'jobs'|'plans'|'movements'|'movement_templates'|'event'  $kind
     */
    public function request(string $kind, Collection $items, User $user, ?int $eventId, ?string $reason = null): Deletion
    {
        $items = $items->values();
        $trigger = $this->approvalTrigger($kind, $items, $user);

        $create = fn () => Deletion::create([
            'event_id' => $eventId,
            'kind' => $kind,
            'status' => 'pending',
            'trigger' => $trigger,
            'requested_by' => $user->id,
            'requester_name' => $user->name,
            'reason' => $reason ? trim($reason) : null,
            'request' => ['ids' => $items->map(fn (Model $m) => $m->getKey())->all()],
            'summary' => $this->describe($kind, $items),
            'item_count' => $items->count(),
            'expires_at' => $trigger ? now()->addHours(self::REQUEST_HOURS) : null,
        ]);

        if (! $trigger) {
            // One transaction: a failed delete leaves no half-made batch behind.
            return DB::transaction(fn () => $this->execute($create(), $user));
        }

        $deletion = $create();
        AuditLog::change(
            $trigger === 'brake' ? 'Mass-delete brake: approval needed' : 'Delete sent for approval',
            Deletion::label($kind, $items->count()),
            ['request' => $deletion->id, 'why' => $this->triggerText($deletion)],
            $deletion,
            $eventId,
        );
        $this->notifyApprovers($deletion);

        return $deletion;
    }

    public function approve(Deletion $deletion, User $approver): Deletion
    {
        $this->assertDecidable($deletion, $approver);

        return DB::transaction(function () use ($deletion, $approver) {
            $deletion->update(['approved_by' => $approver->id, 'approver_name' => $approver->name, 'decided_at' => now()]);

            return $this->execute($deletion, $approver);
        });
    }

    public function reject(Deletion $deletion, User $user, ?string $note = null): Deletion
    {
        $this->assertDecidable($deletion, $user);
        $deletion->update([
            'status' => 'rejected', 'approved_by' => $user->id, 'approver_name' => $user->name,
            'decided_at' => now(), 'decision_note' => $note ? trim($note) : null,
        ]);
        AuditLog::change('Delete request rejected', Deletion::label($deletion->kind, $deletion->item_count),
            ['request' => $deletion->id, 'requested_by' => $deletion->requester_name, 'note' => $deletion->decision_note], $deletion, $deletion->event_id);

        return $deletion;
    }

    /** The requester takes the request back. */
    public function withdraw(Deletion $deletion, User $user): Deletion
    {
        if (! $deletion->isPending() || (int) $deletion->requested_by !== (int) $user->id) {
            throw new DeletionRefused('Only a request of yours that is still waiting can be withdrawn.');
        }
        $deletion->update(['status' => 'withdrawn', 'decided_at' => now()]);
        AuditLog::change('Delete request withdrawn', Deletion::label($deletion->kind, $deletion->item_count), ['request' => $deletion->id], $deletion, $deletion->event_id);

        return $deletion;
    }

    public function expireOld(): int
    {
        return Deletion::where('status', 'pending')->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->update(['status' => 'expired', 'updated_at' => now()]);
    }

    /**
     * Put back what a batch deleted, or only the given items of its kind (a plan brings its
     * movements). Items that can't come back are skipped with the reason.
     *
     * @param  array<int, int>|null  $ids
     * @return array{restored: int, skipped: array<int, string>}
     */
    public function restore(Deletion $deletion, User $user, ?array $ids = null): array
    {
        if (! in_array($deletion->status, Deletion::IN_BIN, true)) {
            throw new DeletionRefused('This deletion is no longer in the recycle bin.');
        }

        $result = DB::transaction(function () use ($deletion, $ids) {
            $rows = $this->binRows($deletion, $deletion->kind === 'event' ? null : $ids);
            $restored = 0;
            $skipped = [];

            foreach (self::TABLE_ORDER as $table) {
                foreach ($rows[$table] ?? collect() as $row) {
                    $why = $this->restoreRow($table, $row);
                    $why === null ? $restored++ : $skipped[] = $why;
                }
            }

            return ['restored' => $restored, 'skipped' => $skipped];
        });

        $left = $this->binCount($deletion);
        $deletion->update([
            'status' => $left ? 'partially_restored' : 'restored',
            'restored_by' => $user->id,
            'restored_at' => now(),
        ]);

        AuditLog::change('Deletion restored', Deletion::label($deletion->kind, $deletion->item_count), [
            'bin' => $deletion->id, 'restored' => $result['restored'], 'skipped' => $result['skipped'] ?: null, 'still_in_bin' => $left,
        ], $deletion, $deletion->event_id);

        return $result;
    }

    /**
     * Plans and movements deleted before the recycle bin existed have no batch: bring back a plan
     * with the movements that were deleted together with it.
     */
    public function restoreEarlierPlan(Plan $plan, User $user): int
    {
        if (! $plan->trashed() || $plan->deletion_id) {
            throw new DeletionRefused('That plan is not an earlier deletion.');
        }
        if ($plan->event_id && ! Event::whereKey($plan->event_id)->exists()) {
            throw new DeletionRefused('Its event is deleted.');
        }

        $count = DB::transaction(function () use ($plan) {
            $at = $plan->deleted_at;
            $movements = Movement::onlyTrashed()->withoutGlobalScope(ProviderScope::class)
                ->where('plan_id', $plan->id)->whereNull('deletion_id')
                ->whereBetween('deleted_at', [$at->copy()->subMinutes(2), $at->copy()->addMinutes(2)])
                ->get();
            $plan->restore();
            $movements->each->restore();

            return $movements->count();
        });

        AuditLog::change('Deletion restored', "Plan {$plan->code}", ['earlier_deletion' => true, 'movements' => $count], $plan, $plan->event_id);

        return $count;
    }

    /**
     * Remove a batch for good (child rows first), then its checkpoint photos and signatures.
     * Rows another record still points at are left for the next run.
     */
    public function purge(Deletion $deletion): int
    {
        $evidence = [];

        $removed = DB::transaction(function () use ($deletion, &$evidence) {
            if ($deletion->kind === 'event') {
                return $this->purgeEvent($deletion, $evidence);
            }

            $rows = $this->binRows($deletion);
            $removed = 0;
            foreach (array_reverse(self::TABLE_ORDER) as $table) {
                foreach ($rows[$table] ?? collect() as $row) {
                    $removed += $this->purgeRow($table, $row, $evidence);
                }
            }

            return $removed;
        });

        $this->discardEvidence($evidence);

        // The event row may be gone now (its id on the batch was cleared with it).
        $deletion->refresh();
        if (! $this->binCount($deletion)) {
            $deletion->update(['status' => 'purged', 'purged_at' => now()]);
        }
        AuditLog::change('Recycle bin purged', Deletion::label($deletion->kind, $deletion->item_count), ['bin' => $deletion->id, 'rows' => $removed], $deletion, $deletion->event_id);

        return $removed;
    }

    /** Batches past their 30 days. */
    public function purgeDue(): int
    {
        $count = 0;
        Deletion::whereIn('status', Deletion::IN_BIN)->where('purge_after', '<=', now())->orderBy('id')
            ->each(function (Deletion $deletion) use (&$count) {
                $this->purge($deletion);
                $count++;
            });

        return $count;
    }

    /** What a pending or batch deletion still holds in the bin, per table, for the recycle bin page. */
    public function binRows(Deletion $deletion, ?array $ids = null): array
    {
        $query = fn (string $model) => $model::onlyTrashed()->withoutGlobalScope(ProviderScope::class)->where('deletion_id', $deletion->id);

        $rows = [
            'events' => $query(Event::class)->get(),
            'movement_templates' => $query(MovementTemplate::class)->get(),
            'plans' => $query(Plan::class)->get(),
            'movements' => $query(Movement::class)->get(),
            'jobs' => $query(JobOperation::class)->with('team:id,team_name')->get(),
        ];

        if ($ids !== null) {
            $table = $deletion->kind === 'event' ? 'events' : $deletion->kind;
            $rows[$table] = $rows[$table]->whereIn('id', $ids)->values();
            // Restoring a plan brings its movements; otherwise only the chosen kind.
            foreach (array_keys($rows) as $other) {
                if ($other === $table) {
                    continue;
                }
                $rows[$other] = $table === 'plans' && $other === 'movements'
                    ? $rows['movements']->whereIn('plan_id', $ids)->values()
                    : collect();
            }
        }

        return $rows;
    }

    public function binCount(Deletion $deletion): int
    {
        return collect($this->binRows($deletion))->sum(fn (Collection $rows) => $rows->count());
    }

    /** The flash message for a delete the user just asked for. */
    public function message(Deletion $deletion): string
    {
        $what = Deletion::label($deletion->kind, $deletion->status === 'pending'
            ? $deletion->item_count
            : count($deletion->items[$deletion->kind === 'event' ? 'events' : $deletion->kind] ?? []));

        if ($deletion->status === 'pending') {
            return "Sent for approval: {$what}. Another admin must approve it in Setups → Recycle bin ({$this->triggerText($deletion)}).";
        }

        $skipped = (int) ($deletion->summary['skipped'] ?? 0);

        return "Deleted {$what}. You can restore it from Setups → Recycle bin for ".self::KEEP_DAYS.' days.'
            .($skipped ? " {$skipped} skipped (they have jobs)." : '');
    }

    public function triggerText(Deletion $deletion): ?string
    {
        return match ($deletion->trigger) {
            'threshold' => 'More than '.(self::APPROVAL_OVER[$deletion->kind] ?? 0).' '.(Deletion::KIND_LABELS[$deletion->kind][1] ?? $deletion->kind).' at once',
            'in_use' => 'The template is used by plans',
            'event' => 'Deleting an event',
            'brake' => 'Over the limit of '.(self::BRAKE[$deletion->kind] ?? 0).' '.(Deletion::KIND_LABELS[$deletion->kind][1] ?? $deletion->kind).' deleted in '.self::BRAKE_MINUTES.' minutes',
            default => null,
        };
    }

    // ────────────────────────────────────────────────────────────────────────

    private function approvalTrigger(string $kind, Collection $items, User $user): ?string
    {
        if ($kind === 'event') {
            return 'event';
        }
        if (isset(self::APPROVAL_OVER[$kind]) && $items->count() > self::APPROVAL_OVER[$kind]) {
            return 'threshold';
        }
        // Deleted plans count too: they can be restored and would then point at nothing.
        if ($kind === 'movement_templates' && Plan::withTrashed()->whereIn('movement_template_id', $items->map(fn (Model $m) => $m->getKey())->all())->exists()) {
            return 'in_use';
        }
        if (isset(self::BRAKE[$kind])) {
            $recent = Deletion::where('requested_by', $user->id)->where('kind', $kind)
                ->whereIn('status', ['pending', 'done', 'partially_restored', 'restored', 'purged'])
                ->where('created_at', '>=', now()->subMinutes(self::BRAKE_MINUTES))
                ->sum('item_count');
            if ($recent + $items->count() > self::BRAKE[$kind]) {
                return 'brake';
            }
        }

        return null;
    }

    private function assertDecidable(Deletion $deletion, User $user): void
    {
        if (! $deletion->isPending()) {
            throw new DeletionRefused('This request is no longer waiting for a decision.');
        }
        if ((int) $deletion->requested_by === (int) $user->id) {
            throw new DeletionRefused('Another admin has to decide on your own delete request.');
        }
    }

    /** Soft-deletes the request's items into the batch. */
    private function execute(Deletion $deletion, User $actor): Deletion
    {
        $ids = $deletion->request['ids'] ?? [];
        $items = ['jobs' => [], 'movements' => [], 'plans' => [], 'movement_templates' => [], 'events' => []];
        $skipped = 0;

        DB::transaction(function () use ($deletion, $ids, &$items, &$skipped) {
            $live = fn (string $model) => $model::withoutGlobalScope(ProviderScope::class)->whereKey($ids)->get();

            switch ($deletion->kind) {
                case 'jobs':
                    foreach ($live(JobOperation::class)->load('team') as $job) {
                        // Free the movement so a new job can be generated; restore links it back.
                        Movement::withTrashed()->withoutGlobalScope(ProviderScope::class)
                            ->where('job_id', $job->job_id)->update(['job_id' => null, 'job_generated_at' => null]);
                        AuditLog::record(
                            action: 'Job deleted',
                            target: $job->job_id.($job->team ? ' · '.$job->team->team_name : ''),
                            meta: "Status was {$job->status} · recycle bin #{$deletion->id}",
                            subject: $job,
                            eventId: $job->event_id,
                        );
                        $items['jobs'][] = $this->binIt($job, $deletion);
                    }
                    break;

                case 'plans':
                    foreach ($live(Plan::class) as $plan) {
                        // Same rule as before: a plan whose movements have jobs stays.
                        if (Movement::withoutGlobalScope(ProviderScope::class)->where('plan_id', $plan->id)->whereNotNull('job_id')->exists()) {
                            $skipped++;
                            continue;
                        }
                        $items['movements'] = [...$items['movements'], ...$this->binQuery(
                            Movement::withoutGlobalScope(ProviderScope::class)->where('plan_id', $plan->id), $deletion)];
                        $items['plans'][] = $this->binIt($plan, $deletion);
                    }
                    break;

                case 'movements':
                    foreach ($live(Movement::class) as $movement) {
                        if ($movement->job_id) {
                            $skipped++;
                            continue;
                        }
                        $items['movements'][] = $this->binIt($movement, $deletion);
                    }
                    break;

                case 'movement_templates':
                    foreach ($live(MovementTemplate::class) as $template) {
                        $items['movement_templates'][] = $this->binIt($template, $deletion);
                    }
                    break;

                case 'event':
                    foreach ($live(Event::class) as $event) {
                        // Everything of the event goes into this batch; movements keep their job links.
                        $of = fn (string $model) => $model::withoutGlobalScope(ProviderScope::class)->where('event_id', $event->id);
                        $items['jobs'] = $this->binQuery($of(JobOperation::class), $deletion);
                        $items['movements'] = $this->binQuery($of(Movement::class), $deletion);
                        $items['plans'] = $this->binQuery($of(Plan::class), $deletion);
                        $items['movement_templates'] = $this->binQuery($of(MovementTemplate::class), $deletion);
                        $items['events'][] = $this->binIt($event, $deletion);
                    }
                    break;
            }

            // In the same transaction, so a failure here never leaves rows deleted without a record.
            $primary = $deletion->kind === 'event' ? 'events' : $deletion->kind;
            $counts = array_filter(array_map('count', $items));
            $deletion->update([
                'status' => 'done',
                'items' => $items,
                'item_count' => count($items[$primary]),
                'summary' => array_merge($deletion->summary ?? [], ['counts' => $counts, 'skipped' => $skipped]),
                'executed_at' => now(),
                'purge_after' => now()->addDays(self::KEEP_DAYS),
            ]);

            AuditLog::change(
                'Deleted '.Deletion::label($deletion->kind, count($items[$primary])),
                \Illuminate\Support\Str::limit(implode(', ', array_slice($deletion->summary['labels'] ?? [], 0, 10)), 240),
                array_filter([
                    'recycle_bin' => $deletion->id,
                    'requested_by' => $deletion->requester_name,
                    'approved_by' => $deletion->approver_name,
                    'also' => array_diff_key($counts, [$primary => true]) ?: null,
                    'skipped' => $skipped ?: null,
                ]),
                $deletion,
                $deletion->event_id,
            );
        });

        return $deletion->refresh();
    }

    private function binIt(Model $model, Deletion $deletion): int
    {
        $model->forceFill(['deletion_id' => $deletion->id])->saveQuietly();
        $model->delete();

        return $model->getKey();
    }

    /** @return array<int, int> */
    private function binQuery($query, Deletion $deletion): array
    {
        $ids = (clone $query)->pluck('id')->all();
        if ($ids) {
            $query->getModel()->newQueryWithoutScopes()->whereKey($ids)
                ->update(['deletion_id' => $deletion->id, 'deleted_at' => now(), 'updated_at' => now()]);
        }

        return $ids;
    }

    private function restoreRow(string $table, Model $row): ?string
    {
        switch ($table) {
            case 'movement_templates':
                $taken = MovementTemplate::where('id', '!=', $row->id)
                    ->where(fn ($q) => $q->where('code', $row->code)->orWhere(fn ($n) => $n->where('event_id', $row->event_id)->where('name', $row->name)))
                    ->exists();
                if ($taken) {
                    return "Template {$row->code}: its code or name is now used by another template.";
                }
                break;

            case 'plans':
                if ($row->event_id && ! Event::whereKey($row->event_id)->exists()) {
                    return "Plan {$row->code}: its event is deleted.";
                }
                break;

            case 'movements':
                if (! Plan::whereKey($row->plan_id)->exists()) {
                    return "Movement {$row->code}: its plan is still deleted.";
                }
                break;

            case 'jobs':
                $movement = Movement::withoutGlobalScope(ProviderScope::class)->find($row->movement_id);
                if (! $movement) {
                    return "Job {$row->job_id}: its movement is deleted.";
                }
                if ($movement->job_id && $movement->job_id !== $row->job_id) {
                    return "Job {$row->job_id}: its movement now has job {$movement->job_id}.";
                }
                $movement->forceFill(['job_id' => $row->job_id, 'job_generated_at' => $movement->job_generated_at ?? $row->created_at])->saveQuietly();
                break;
        }

        $row->forceFill(['deletion_id' => null]);
        $row->restore();
        // A fresh updated_at brings a restored job back into the mobile app's next sync.
        if ($table === 'jobs') {
            $row->touch();
        }

        return null;
    }

    private function purgeRow(string $table, Model $row, array &$evidence): int
    {
        switch ($table) {
            case 'jobs':
                $this->collectEvidence([$row->id], $evidence);
                break;
            case 'movements':
                if (JobOperation::withTrashed()->withoutGlobalScope(ProviderScope::class)->where('movement_id', $row->id)->exists()) {
                    return 0;
                }
                break;
            case 'plans':
                $inUse = Movement::withTrashed()->withoutGlobalScope(ProviderScope::class)->where('plan_id', $row->id)->exists()
                    || JobOperation::withTrashed()->withoutGlobalScope(ProviderScope::class)->where('plan_id', $row->id)->exists();
                if ($inUse) {
                    return 0;
                }
                break;
            case 'movement_templates':
                // Plans keep their data; only the link to the template that is now gone is cleared.
                Plan::withTrashed()->where('movement_template_id', $row->id)->update(['movement_template_id' => null]);
                break;
        }

        $row->forceDelete();

        return 1;
    }

    private function purgeEvent(Deletion $deletion, array &$evidence): int
    {
        $removed = 0;
        foreach (Event::onlyTrashed()->where('deletion_id', $deletion->id)->get() as $event) {
            $of = fn (string $model) => $model::withTrashed()->withoutGlobalScope(ProviderScope::class)->where('event_id', $event->id);

            $jobIds = $of(JobOperation::class)->pluck('id')->all();
            $this->collectEvidence($jobIds, $evidence);
            $removed += JobOperation::withTrashed()->withoutGlobalScope(ProviderScope::class)->whereKey($jobIds)->forceDelete();
            JobCheckpoint::where('event_id', $event->id)->delete();
            $removed += $of(Movement::class)->forceDelete();
            $removed += $of(Plan::class)->forceDelete();
            $removed += $of(MovementTemplate::class)->forceDelete();
            DB::table('ai_interactions')->where('event_id', $event->id)->update(['event_id' => null]);
            // Other batches of this event go with it.
            Deletion::where('event_id', $event->id)->where('id', '!=', $deletion->id)->whereIn('status', Deletion::IN_BIN)
                ->update(['status' => 'purged', 'purged_at' => now()]);
            // Teams, flights, stays, checkpoint templates and the rest cascade from here.
            $event->forceDelete();
            $removed++;
        }

        return $removed;
    }

    private function collectEvidence(array $jobIds, array &$evidence): void
    {
        if (! $jobIds) {
            return;
        }
        foreach (JobCheckpoint::whereIn('job_id', $jobIds)->get(['photo_path', 'signature_path']) as $checkpoint) {
            $evidence[] = $checkpoint->photo_path;
            $evidence[] = $checkpoint->signature_path;
        }
    }

    private function discardEvidence(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    /** Labels of what is asked for, for the request and the bin. */
    private function describe(string $kind, Collection $items): array
    {
        $label = match ($kind) {
            'jobs' => fn ($job) => $job->job_id.($job->team ? ' · '.$job->team->team_name : ''),
            'plans' => fn ($plan) => trim("{$plan->code} · {$plan->name}"),
            'movements' => fn ($movement) => $movement->code,
            'movement_templates' => fn ($template) => "{$template->code} · {$template->name}",
            'event' => fn ($event) => $event->name,
        };

        return ['labels' => $items->take(200)->map($label)->values()->all()];
    }

    private function notifyApprovers(Deletion $deletion): void
    {
        if (! \Spatie\Permission\Models\Permission::where('name', 'plans.manage')->where('guard_name', 'web')->exists()) {
            return;
        }
        $approvers = User::permission('plans.manage')->whereKeyNot($deletion->requested_by)->whereNotNull('email')->get();

        foreach ($approvers as $approver) {
            try {
                Mail::to($approver->email)->send(new DeletionRequestMail($deletion, $this->triggerText($deletion)));
            } catch (\Throwable $e) {
                // The request stands; the bin and the notification feed still show it.
                Log::warning('Deletion request mail failed', ['deletion' => $deletion->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
