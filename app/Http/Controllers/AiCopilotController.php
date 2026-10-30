<?php

namespace App\Http\Controllers;

use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\Plan;
use App\Models\User;
use App\Services\Ai\OperationsAttention;
use App\Services\AiCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiCopilotController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Ai');
    }

    public function query(Request $request, AiCopilotService $copilot): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            // Earlier turns of this chat, so follow-ups like "the first one" make sense.
            'history' => ['sometimes', 'array', 'max:12'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:4000'],
            // What the user was looking at when they pressed an "Ask Daleel" button (a hint only).
            'context' => ['sometimes', 'array'],
            'context.page' => ['sometimes', 'string', 'in:'.implode(',', array_keys(self::PAGES))],
            'context.date' => ['sometimes', 'date_format:Y-m-d'],
            'context.plan_id' => ['sometimes', 'integer'],
            'context.job_id' => ['sometimes', 'integer'],
            'context.movement_id' => ['sometimes', 'integer'],
            'context.flight_id' => ['sometimes', 'integer'],
        ]);

        $eventId = $request->session()->get('active_event_id');

        if (! $eventId) {
            return response()->json([
                'ok' => false,
                'message' => 'Select an active event before asking Daleel.',
            ]);
        }

        $context = $this->describeContext($validated['context'] ?? [], (int) $eventId, $request->user());
        $result = $copilot->ask($validated['question'], $request->user(), (int) $eventId, $validated['history'] ?? [], $context);

        return response()->json($result);
    }

    private const PAGES = [
        'planning' => 'Planning',
        'crew-assignment' => 'Crew Assignment',
        'day-board' => 'Day Board',
        'jobs' => 'Jobs Queue',
        'job' => 'Job detail',
        'event-teams' => 'Event Teams',
    ];

    /**
     * One line naming what the user is looking at. Records are looked up within the active event and what the
     * user may see; anything else is dropped, so the context can't widen what Daleel reports.
     */
    private function describeContext(array $context, int $eventId, User $user): ?string
    {
        $parts = array_filter([
            isset($context['page']) ? self::PAGES[$context['page']].' page' : null,
            isset($context['date']) ? 'date '.$context['date'] : null,
            isset($context['plan_id']) && $user->can('plans.view')
                ? optional(Plan::where('event_id', $eventId)->find($context['plan_id']), fn (Plan $p) => "plan {$p->code} ({$p->name})")
                : null,
            isset($context['movement_id'])
                ? optional(OperationsAttention::visibleMovements($eventId, $user)->with('team:id,team_name')->find($context['movement_id']),
                    fn (Movement $m) => "movement {$m->code} ({$m->team?->team_name}, {$m->window_start?->format('D j M H:i')})")
                : null,
            isset($context['flight_id'])
                ? optional(\App\Models\TeamFlight::with('team:id,team_name')->where('event_id', $eventId)->find($context['flight_id']),
                    fn (\App\Models\TeamFlight $f) => "flight {$f->flight_number} ({$f->team?->team_name}, {$f->direction}, scheduled {$f->scheduled_at?->format('Y-m-d H:i')})")
                : null,
            isset($context['job_id'])
                ? optional(JobOperation::visibleTo($user, $eventId)->with('team:id,team_name')->find($context['job_id']),
                    fn (JobOperation $j) => "job {$j->job_id} ({$j->team?->team_name})")
                : null,
        ]);

        return $parts ? 'The user is looking at: '.implode(', ', $parts).'.' : null;
    }
}
