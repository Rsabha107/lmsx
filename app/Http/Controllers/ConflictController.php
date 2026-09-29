<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ConflictAcceptance;
use App\Models\Movement;
use App\Services\ConflictDetectionService;
use App\Services\JobGenerationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Actions behind Planning's Resolve panel. Crew changes themselves go through
 * MovementCrewController so they're audited and mirrored onto the job.
 */
class ConflictController extends Controller
{
    use AuthorizesRequests;

    public function crewOptions(Movement $movement, ConflictDetectionService $detector): JsonResponse
    {
        $this->authorize('view', $movement);

        return response()->json($detector->crewOptions($movement));
    }

    public function recomputeWindow(Movement $movement, JobGenerationService $jobs): RedirectResponse
    {
        $this->authorize('view', $movement);

        $movement->load(['flight', 'match', 'plan.movementTemplate.legs', 'checkpointTemplate.checkpoints']);

        if (! $jobs->recomputeMovementWindow($movement)) {
            return back()->with('error', "{$movement->code}'s window can't be recalculated (already generated, or no flight/kick-off to work from).");
        }

        AuditLog::record(
            action: 'Movement window recalculated',
            target: $movement->code,
            meta: $movement->window_start?->format('D H:i').'–'.$movement->window_end?->format('H:i'),
            subject: $movement,
            eventId: $movement->event_id,
        );

        return back()->with('success', "Window recalculated for {$movement->code}.");
    }

    public function accept(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'conflict_id' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $eventId = $this->activeEventId($request);

        ConflictAcceptance::updateOrCreate(
            ['event_id' => $eventId, 'conflict_id' => $validated['conflict_id']],
            ['reason' => $validated['reason'], 'user_id' => $request->user()->id],
        );

        AuditLog::record(
            action: 'Conflict accepted',
            target: $validated['conflict_id'],
            meta: $validated['reason'],
            eventId: $eventId,
        );

        return back()->with('success', 'Conflict accepted.');
    }

    public function reopen(Request $request): RedirectResponse
    {
        $validated = $request->validate(['conflict_id' => ['required', 'string', 'max:100']]);
        $eventId = $this->activeEventId($request);

        ConflictAcceptance::where('event_id', $eventId)->where('conflict_id', $validated['conflict_id'])->delete();

        AuditLog::record(action: 'Conflict reopened', target: $validated['conflict_id'], eventId: $eventId);

        return back()->with('success', 'Conflict reopened.');
    }

    private function activeEventId(Request $request): int
    {
        $eventId = (int) $request->session()->get('active_event_id');
        abort_unless($eventId && $request->user()->canAccessEvent($eventId), 403, 'Select an event first.');

        return $eventId;
    }
}
