<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MatchesController extends Controller
{
    /**
     * Display a listing of the matches.
     */
    public function index(Request $request)
    {
        $activeEventId = $request->session()->get('active_event_id');
        
        $query = GameMatch::with(['team1.country', 'team2.country', 'event', 'venue.country'])
            ->orderBy('match_date', 'asc');
        
        // Filter by active event if one is selected
        if ($activeEventId) {
            $query->where('event_id', $activeEventId);
        }
        
        $matches = $query->get();

        $teams = $activeEventId
            ? Team::where('event_id', $activeEventId)->orderBy('team_name', 'asc')->get()
            : collect();
        $events = Event::with(['venues', 'teams'])->orderBy('name', 'asc')->get();
        $venues = Venue::with('country')->orderBy('name', 'asc')->get();

        return Inertia::render('Matches', [
            'matches' => $matches,
            'teams' => $teams,
            'events' => $events,
            'venues' => $venues,
        ]);
    }

    /**
     * Store a newly created match in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'match_number' => ['required', 'string', 'max:50', Rule::unique('matches', 'match_number')->where('event_id', $request->input('event_id'))],
            'event_id' => 'nullable|integer|exists:events,id',
            'venue_id' => 'nullable|integer|exists:venues,id',
            'team1_id' => 'nullable|integer|exists:teams,id',
            'team2_id' => 'nullable|integer|exists:teams,id',
            'stage' => 'nullable|string|max:100',
            'match_date' => 'nullable|date',
            'gates_opening' => 'nullable|string',
            'kick_off' => 'nullable|string',
        ]);

        // Combine date with times
        if (!empty($validated['match_date'])) {
            $baseDate = $validated['match_date'];
            
            if (!empty($validated['gates_opening']) && preg_match('/^\d{2}:\d{2}$/', $validated['gates_opening'])) {
                $validated['gates_opening'] = $baseDate . ' ' . $validated['gates_opening'] . ':00';
            }
            
            if (!empty($validated['kick_off']) && preg_match('/^\d{2}:\d{2}$/', $validated['kick_off'])) {
                $validated['kick_off'] = $baseDate . ' ' . $validated['kick_off'] . ':00';
            }
        }

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
            'match_number' => ['required', 'string', 'max:50', Rule::unique('matches', 'match_number')->where('event_id', $request->input('event_id'))->ignore($match->id)],
            'event_id' => 'nullable|integer|exists:events,id',
            'venue_id' => 'nullable|integer|exists:venues,id',
            'team1_id' => 'nullable|integer|exists:teams,id',
            'team2_id' => 'nullable|integer|exists:teams,id',
            'stage' => 'nullable|string|max:100',
            'match_date' => 'nullable|date',
            'gates_opening' => 'nullable|string',
            'kick_off' => 'nullable|string',
        ]);

        // Combine date with times
        if (!empty($validated['match_date'])) {
            $baseDate = $validated['match_date'];
            
            if (!empty($validated['gates_opening']) && preg_match('/^\d{2}:\d{2}$/', $validated['gates_opening'])) {
                $validated['gates_opening'] = $baseDate . ' ' . $validated['gates_opening'] . ':00';
            }
            
            if (!empty($validated['kick_off']) && preg_match('/^\d{2}:\d{2}$/', $validated['kick_off'])) {
                $validated['kick_off'] = $baseDate . ' ' . $validated['kick_off'] . ':00';
            }
        }

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
