<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Country;
use App\Models\Event;
use App\Models\EventTeam;
use App\Models\Team;
use App\Models\TeamClassification;
use App\Models\TeamFlight;
use App\Models\TeamStay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventsController extends Controller
{
    public function index(): Response
    {
        $events = Event::with(['country', 'eventTeams.team', 'eventTeams.classification'])
                       ->orderBy('start_date', 'desc')
                       ->get();

        // Attach flights and stays to each event_team
        $eventIds = $events->pluck('id')->toArray();

        $flights = TeamFlight::with(['originAirport', 'destinationAirport'])
            ->whereIn('event_id', $eventIds)
            ->get()
            ->groupBy(fn ($f) => "{$f->event_id}_{$f->team_id}");

        $stays = TeamStay::whereIn('event_id', $eventIds)
            ->get()
            ->groupBy(fn ($s) => "{$s->event_id}_{$s->team_id}");

        $events->each(function ($event) use ($flights, $stays) {
            $event->eventTeams->each(function ($et) use ($flights, $stays) {
                $key         = "{$et->event_id}_{$et->team_id}";
                $et->flights = $flights->get($key, collect())->values();
                $et->stay    = $stays->get($key, collect())->first();
            });
        });

        return Inertia::render('Events', [
            'events'          => $events,
            'teams'           => Team::active()->orderBy('code')->get(),
            'classifications' => TeamClassification::active()->orderBy('name')->get(),
            'countries'       => Country::active()->orderBy('country_name')->get(),
            'airports'        => Airport::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'short_name'   => 'nullable|string|max:100',
            'host_country' => 'nullable|string|max:10|exists:countries,country_code',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'status'       => 'nullable|in:upcoming,active,completed',
            'notes'        => 'nullable|string',
        ]);

        Event::create($validated);

        return redirect()->back()->with('success', 'Event created successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'short_name'   => 'nullable|string|max:100',
            'host_country' => 'nullable|string|max:10|exists:countries,country_code',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'status'       => 'nullable|in:upcoming,active,completed',
            'notes'        => 'nullable|string',
        ]);

        $event->update($validated);

        return redirect()->back()->with('success', 'Event updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Event::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Event deleted successfully.');
    }

    // Assign a team to an event
    public function assignTeam(Request $request, int $id): RedirectResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'team_code'              => 'required|string|exists:teams,code',
            'group_pool'             => 'nullable|string|max:50',
            'classification_type_id' => 'nullable|integer|exists:team_classifications,id',
        ]);

        // Convert team code to team_id
        $team = \App\Models\Team::where('code', $validated['team_code'])->firstOrFail();

        EventTeam::updateOrCreate(
            ['event_id' => $event->id, 'team_id' => $team->id],
            ['group_pool' => $validated['group_pool'] ?? null, 'classification_type_id' => $validated['classification_type_id'] ?? null]
        );

        return redirect()->back()->with('success', 'Team assigned to event.');
    }

    // Remove a team from an event
    public function removeTeam(int $id, string $teamCode): RedirectResponse
    {
        // Convert team code to team_id
        $team = \App\Models\Team::where('code', $teamCode)->firstOrFail();
        
        EventTeam::where('event_id', $id)
                 ->where('team_id', $team->id)
                 ->delete();

        return redirect()->back()->with('success', 'Team removed from event.');
    }
}
