<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Event;
use App\Models\EventTeam;
use App\Models\Team;
use App\Models\TeamClassification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventTeamsController extends Controller
{
    /**
     * Display event teams for the active event.
     */
    public function index(Request $request)
    {
        $activeEventId = $request->session()->get('active_event_id');
        
        if (!$activeEventId) {
            return Inertia::render('EventTeams', [
                'activeEvent' => null,
                'eventTeams' => [],
                'airports' => Airport::orderBy('name')->get(),
                'teams' => Team::active()->orderBy('code')->get(),
                'classifications' => TeamClassification::active()->orderBy('name')->get(),
            ]);
        }

        $activeEvent = Event::with('country')->find($activeEventId);

        $eventTeams = EventTeam::where('event_id', $activeEventId)
            ->with([
                'team.country',
                'classification',
                'flights' => function($query) use ($activeEventId) {
                    $query->where('event_id', $activeEventId)
                          ->with(['originAirport', 'destinationAirport'])
                          ->orderBy('scheduled_at');
                },
                'stay' => function($query) use ($activeEventId) {
                    $query->where('event_id', $activeEventId);
                }
            ])
            ->get();

        return Inertia::render('EventTeams', [
            'activeEvent' => $activeEvent,
            'eventTeams' => $eventTeams,
            'airports' => Airport::orderBy('name')->get(),
            'teams' => Team::active()->orderBy('code')->get(),
            'classifications' => TeamClassification::active()->orderBy('name')->get(),
        ]);
    }
}
