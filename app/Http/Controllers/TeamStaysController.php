<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamStay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamStaysController extends Controller
{
    public function store(Request $request, int $eventId, string $teamCode): RedirectResponse
    {
        $validated = $request->validate([
            'hotel_name'      => 'nullable|string|max:255',
            'address'         => 'nullable|string|max:255',
            'training_ground' => 'nullable|string|max:255',
            'check_in'        => 'nullable|date',
            'check_out'       => 'nullable|date|after_or_equal:check_in',
            'room_count'      => 'nullable|integer|min:0',
            'notes'           => 'nullable|string',
        ]);

        // Convert team code to team_id
        $team = Team::where('code', $teamCode)->firstOrFail();

        TeamStay::create([...$validated, 'event_id' => $eventId, 'team_id' => $team->id]);

        return redirect()->back()->with('success', 'Accommodation added.');
    }

    public function update(Request $request, int $eventId, int $stayId): RedirectResponse
    {
        $stay      = TeamStay::where('event_id', $eventId)->findOrFail($stayId);
        $validated = $request->validate([
            'hotel_name'      => 'nullable|string|max:255',
            'address'         => 'nullable|string|max:255',
            'training_ground' => 'nullable|string|max:255',
            'check_in'        => 'nullable|date',
            'check_out'       => 'nullable|date|after_or_equal:check_in',
            'room_count'      => 'nullable|integer|min:0',
            'notes'           => 'nullable|string',
        ]);

        $stay->update($validated);

        return redirect()->back()->with('success', 'Accommodation updated.');
    }

    public function destroy(int $eventId, int $stayId): RedirectResponse
    {
        TeamStay::where('event_id', $eventId)->findOrFail($stayId)->delete();

        return redirect()->back()->with('success', 'Accommodation removed.');
    }
}
