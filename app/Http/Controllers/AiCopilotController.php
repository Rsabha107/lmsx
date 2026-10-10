<?php

namespace App\Http\Controllers;

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
        ]);

        $eventId = $request->session()->get('active_event_id');

        if (! $eventId) {
            return response()->json([
                'ok' => false,
                'message' => 'Select an active event before asking Daleel.',
            ]);
        }

        $result = $copilot->ask($validated['question'], $request->user(), (int) $eventId, $validated['history'] ?? []);

        return response()->json($result);
    }
}
