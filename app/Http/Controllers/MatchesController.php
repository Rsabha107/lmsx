<?php

namespace App\Http\Controllers;

use App\Models\GameMatch;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MatchesController extends Controller
{
    /**
     * Display a listing of the matches.
     */
    public function index()
    {
        $matches = GameMatch::with(['team1', 'team2'])
            ->orderBy('match_date', 'asc')
            ->get();

        $teams = Team::orderBy('team_name', 'asc')->get();

        return Inertia::render('Matches', [
            'matches' => $matches,
            'teams' => $teams,
        ]);
    }

    /**
     * Store a newly created match in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'match_number' => 'required|string|max:50|unique:matches,match_number',
            'event' => 'nullable|string|max:255',
            'venue' => 'nullable|string|max:255',
            'team1_id' => 'nullable|string|exists:teams,code',
            'team2_id' => 'nullable|string|exists:teams,code',
            'stage' => 'nullable|string|max:100',
            'match_date' => 'nullable|date',
            'gates_opening' => 'nullable|date',
            'kick_off' => 'nullable|date',
        ]);

        GameMatch::create($validated);

        return redirect()->back()->with('success', 'Match created successfully.');
    }

    /**
     * Update the specified match in storage.
     */
    public function update(Request $request, $id)
    {
        $match = GameMatch::findOrFail($id);

        $validated = $request->validate([
            'match_number' => 'required|string|max:50|unique:matches,match_number,' . $match->id,
            'event' => 'nullable|string|max:255',
            'venue' => 'nullable|string|max:255',
            'team1_id' => 'nullable|string|exists:teams,code',
            'team2_id' => 'nullable|string|exists:teams,code',
            'stage' => 'nullable|string|max:100',
            'match_date' => 'nullable|date',
            'gates_opening' => 'nullable|date',
            'kick_off' => 'nullable|date',
        ]);

        $match->update($validated);

        return redirect()->back()->with('success', 'Match updated successfully.');
    }

    /**
     * Remove the specified match from storage.
     */
    public function destroy($id)
    {
        $match = GameMatch::findOrFail($id);
        $match->delete();

        return redirect()->back()->with('success', 'Match deleted successfully.');
    }
}
