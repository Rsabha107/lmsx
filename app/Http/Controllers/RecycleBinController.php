<?php

namespace App\Http\Controllers;

use App\Models\Deletion;
use App\Models\Event;
use App\Models\Movement;
use App\Models\Plan;
use App\Models\Scopes\ProviderScope;
use App\Services\DeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use App\Exceptions\DeletionRefused;

/** Setups → Recycle bin: delete requests waiting for approval, deleted batches to restore, history. */
class RecycleBinController extends Controller
{
    public function __construct(private readonly DeletionService $deletions)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $events = Event::withTrashed()->pluck('name', 'id');
        $visible = fn ($query) => $user->canAccessAllEvents()
            ? $query
            : $query->where(fn ($q) => $q->whereNull('event_id')->orWhereIn('event_id', $user->accessibleEventIds()));

        $present = function (Deletion $d, bool $withItems = false) use ($user, $events) {
            return [
                'id' => $d->id,
                'kind' => $d->kind,
                'label' => Deletion::label($d->kind, $d->item_count),
                'status' => $d->isPending() ? 'pending' : ($d->status === 'pending' ? 'expired' : $d->status),
                'why' => $this->deletions->triggerText($d),
                'trigger' => $d->trigger,
                'event' => $d->event_id ? ($events[$d->event_id] ?? "Event #{$d->event_id}") : null,
                'requester' => $d->requester_name,
                'approver' => $d->approver_name,
                'reason' => $d->reason,
                'note' => $d->decision_note,
                'mine' => (int) $d->requested_by === (int) $user->id,
                'labels' => array_slice($d->summary['labels'] ?? [], 0, 30),
                'counts' => $d->summary['counts'] ?? null,
                'requested_at' => $d->created_at?->format('d M Y H:i'),
                'expires_at' => $d->expires_at?->format('d M Y H:i'),
                'deleted_at' => $d->executed_at?->format('d M Y H:i'),
                'purge_after' => $d->purge_after?->format('d M Y'),
                'days_left' => $d->purge_after ? max(0, (int) ceil(now()->diffInHours($d->purge_after, false) / 24)) : null,
                'restored_at' => $d->restored_at?->format('d M Y H:i'),
                'items' => $withItems ? $this->items($d) : [],
            ];
        };

        return Inertia::render('RecycleBin', [
            'pending' => $visible(Deletion::pending())->latest()->get()->map(fn ($d) => $present($d))->values(),
            'deleted' => $visible(Deletion::whereIn('status', Deletion::IN_BIN))->latest('executed_at')->get()->map(fn ($d) => $present($d, true))->values(),
            'history' => $visible(Deletion::query())
                ->where(fn ($q) => $q->whereIn('status', ['rejected', 'withdrawn', 'expired', 'restored', 'purged'])
                    ->orWhere(fn ($p) => $p->where('status', 'pending')->where('expires_at', '<=', now())))
                ->latest('updated_at')->limit(100)->get()->map(fn ($d) => $present($d))->values(),
            'earlier' => $this->earlierPlans($user, $events),
            'keepDays' => DeletionService::KEEP_DAYS,
        ]);
    }

    public function approve(Request $request, Deletion $deletion): RedirectResponse
    {
        return $this->act($request, $deletion, function () use ($request, $deletion) {
            $this->deletions->approve($deletion, $request->user());

            return $this->deletions->message($deletion->refresh());
        });
    }

    public function reject(Request $request, Deletion $deletion): RedirectResponse
    {
        $note = $request->validate(['note' => 'nullable|string|max:500'])['note'] ?? null;

        return $this->act($request, $deletion, function () use ($request, $deletion, $note) {
            $this->deletions->reject($deletion, $request->user(), $note);

            return 'Request rejected; nothing was deleted.';
        });
    }

    public function withdraw(Request $request, Deletion $deletion): RedirectResponse
    {
        return $this->act($request, $deletion, function () use ($request, $deletion) {
            $this->deletions->withdraw($deletion, $request->user());

            return 'Request withdrawn.';
        });
    }

    public function restore(Request $request, Deletion $deletion): RedirectResponse
    {
        $ids = $request->validate(['ids' => 'nullable|array', 'ids.*' => 'integer'])['ids'] ?? null;

        return $this->act($request, $deletion, function () use ($request, $deletion, $ids) {
            $result = $this->deletions->restore($deletion, $request->user(), $ids ?: null);
            $message = "Restored {$result['restored']} item(s).";
            if ($result['skipped']) {
                $message .= ' Not restored: '.implode(' ', array_slice($result['skipped'], 0, 3))
                    .(count($result['skipped']) > 3 ? ' (+'.(count($result['skipped']) - 3).' more)' : '');
            }

            return $message;
        });
    }

    public function restoreEarlierPlan(Request $request, int $plan): RedirectResponse
    {
        $plan = Plan::onlyTrashed()->whereNull('deletion_id')->findOrFail($plan);
        abort_unless(! $plan->event_id || $request->user()->canAccessEvent((int) $plan->event_id), 403);

        try {
            $count = $this->deletions->restoreEarlierPlan($plan, $request->user());
        } catch (DeletionRefused $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Restored plan {$plan->code} with {$count} movement(s).");
    }

    private function act(Request $request, Deletion $deletion, callable $action): RedirectResponse
    {
        abort_unless(! $deletion->event_id || $request->user()->canAccessEvent((int) $deletion->event_id)
            || ! Event::whereKey($deletion->event_id)->exists(), 403);

        try {
            return back()->with('success', $action());
        } catch (DeletionRefused $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** The batch's items of its own kind, for picking what to restore. */
    private function items(Deletion $deletion): array
    {
        $rows = $this->deletions->binRows($deletion);
        $table = $deletion->kind === 'event' ? 'events' : $deletion->kind;
        $movementsByPlan = $rows['movements']->countBy('plan_id');

        return $rows[$table]->take(500)->map(fn ($row) => [
            'id' => $row->id,
            'label' => match ($table) {
                'jobs' => $row->job_id.($row->team ? ' · '.$row->team->team_name : '').' · '.$row->status,
                'plans' => "{$row->code} · {$row->name}".(($n = $movementsByPlan[$row->id] ?? 0) ? " · {$n} movement(s)" : ''),
                'movements' => trim("{$row->code} · {$row->from_location} → {$row->to_location}"),
                'movement_templates' => "{$row->code} · {$row->name}",
                'events' => $row->name,
                default => (string) $row->id,
            },
        ])->values()->all();
    }

    /** Plans soft-deleted before the recycle bin existed (no batch), newest first. */
    private function earlierPlans($user, Collection $events): array
    {
        $plans = Plan::onlyTrashed()->whereNull('deletion_id')
            ->when(! $user->canAccessAllEvents(), fn ($q) => $q->whereIn('event_id', $user->accessibleEventIds()))
            ->latest('deleted_at')->limit(300)->get();

        $movements = Movement::onlyTrashed()->withoutGlobalScope(ProviderScope::class)
            ->whereNull('deletion_id')->whereIn('plan_id', $plans->modelKeys())
            ->get(['id', 'plan_id', 'deleted_at'])->groupBy('plan_id');

        return $plans->map(fn (Plan $plan) => [
            'id' => $plan->id,
            'label' => trim("{$plan->code} · {$plan->name}"),
            'event' => $plan->event_id ? ($events[$plan->event_id] ?? null) : null,
            'date' => $plan->date?->format('d M Y'),
            'deleted_at' => $plan->deleted_at?->format('d M Y H:i'),
            'movements' => ($movements[$plan->id] ?? collect())
                ->filter(fn ($m) => abs($m->deleted_at->diffInSeconds($plan->deleted_at)) <= 120)->count(),
        ])->values()->all();
    }
}
