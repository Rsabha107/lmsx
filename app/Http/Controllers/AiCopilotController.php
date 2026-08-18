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
        ]);

        $eventId = $request->session()->get('active_event_id');

        if (! $eventId) {
            return response()->json([
                'ok' => false,
                'message' => 'Select an active event before asking the AI Copilot.',
            ]);
        }

        $result = $copilot->ask($validated['question'], $request->user(), (int) $eventId);

        return response()->json($result);
    }
}
