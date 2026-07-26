<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Country;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamClassification;
use App\Models\TeamFlight;
use App\Models\TeamStay;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventsController extends Controller
{
    public function index(): Response
    {
        $events = Event::with(['country', 'teams.country', 'teams.classification', 'venues.country'])
                       ->orderBy('start_date', 'desc')
                       ->get();

        // Attach flights and stays to each team
        $eventIds = $events->pluck('id')->toArray();

        $flights = TeamFlight::with(['originAirport', 'destinationAirport'])
            ->whereIn('event_id', $eventIds)
            ->get()
            ->groupBy(fn ($f) => "{$f->event_id}_{$f->team_id}");

        $stays = TeamStay::whereIn('event_id', $eventIds)
            ->get()
            ->groupBy(fn ($s) => "{$s->event_id}_{$s->team_id}");

        $events->each(function ($event) use ($flights, $stays) {
            $event->teams->each(function ($team) use ($flights, $stays) {
                $key           = "{$team->event_id}_{$team->id}";
                $team->flights = $flights->get($key, collect())->values();
                $team->stay    = $stays->get($key, collect())->first();
            });
        });

        return Inertia::render('Events', [
            'events'          => $events,
            'classifications' => TeamClassification::active()->orderBy('name')->get(),
            'countries'       => Country::active()->orderBy('country_name')->get(),
            'airports'        => Airport::orderBy('name')->get(),
            'venues'          => Venue::with('country')->orderBy('name')->get(),
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

    // Assign a venue to an event
    public function assignVenue(Request $request, int $id): RedirectResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'venue_id' => 'required|integer|exists:venues,id',
            'purpose'  => 'nullable|string|max:100',
            'notes'    => 'nullable|string',
        ]);

        $event->venues()->attach($validated['venue_id'], [
            'purpose' => $validated['purpose'] ?? null,
            'notes'   => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Venue assigned to event.');
    }

    // Remove a venue from an event
    public function removeVenue(int $id, int $venueId): RedirectResponse
    {
        $event = Event::findOrFail($id);
        $event->venues()->detach($venueId);

        return redirect()->back()->with('success', 'Venue removed from event.');
    }
}
