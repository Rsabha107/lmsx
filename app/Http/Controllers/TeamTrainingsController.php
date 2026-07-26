<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamTraining;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamTrainingsController extends Controller
{
    public function store(Request $request, int $eventId, string $teamCode): RedirectResponse
    {
        $validated = $request->validate([
            'training_ground'   => 'nullable|string|max:255',
            'training_start_at' => 'nullable|date',
            'notes'             => 'nullable|string',
        ]);

        $team = Team::where('event_id', $eventId)->where('code', $teamCode)->firstOrFail();

        TeamTraining::create([...$validated, 'event_id' => $eventId, 'team_id' => $team->id]);

        return redirect()->back()->with('success', 'Training added.');
    }

    public function update(Request $request, int $eventId, int $trainingId): RedirectResponse
    {
        $training  = TeamTraining::where('event_id', $eventId)->findOrFail($trainingId);
        $validated = $request->validate([
            'training_ground'   => 'nullable|string|max:255',
            'training_start_at' => 'nullable|date',
            'notes'             => 'nullable|string',
        ]);

        $training->update($validated);

        return redirect()->back()->with('success', 'Training updated.');
    }

    public function destroy(int $eventId, int $trainingId): RedirectResponse
    {
        TeamTraining::where('event_id', $eventId)->findOrFail($trainingId)->delete();

        return redirect()->back()->with('success', 'Training removed.');
    }
}
