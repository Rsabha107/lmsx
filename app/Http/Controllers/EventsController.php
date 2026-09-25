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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
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
            'event_logo'   => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:2048',
            'venue_ids'    => 'nullable|array',
            'venue_ids.*'  => 'integer|exists:venues,id',
        ]);

        $attributes = Arr::except($validated, ['venue_ids', 'event_logo']);

        if ($request->hasFile('event_logo')) {
            $attributes['event_logo'] = $this->storeLogo($request->file('event_logo'));
        }

        $event = Event::create($attributes);
        $event->venues()->sync($validated['venue_ids'] ?? []);

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
            'event_logo'   => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:2048',
            'remove_logo'  => 'sometimes|boolean',
            'venue_ids'    => 'nullable|array',
            'venue_ids.*'  => 'integer|exists:venues,id',
        ]);

        $attributes = Arr::except($validated, ['venue_ids', 'event_logo', 'remove_logo']);

        if ($request->hasFile('event_logo')) {
            $this->deleteLogo($event->event_logo);
            $attributes['event_logo'] = $this->storeLogo($request->file('event_logo'));
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($event->event_logo);
            $attributes['event_logo'] = null;
        }

        $event->update($attributes);

        // Only touch assignments when the form actually submitted them, so other
        // callers can't silently wipe the pivot (purpose/notes included).
        if ($request->has('venue_ids')) {
            $event->venues()->syncWithoutDetaching($validated['venue_ids'] ?? []);
            $event->venues()->detach(
                $event->venues()->pluck('venues.id')->diff($validated['venue_ids'] ?? [])->all()
            );
        }

        return redirect()->back()->with('success', 'Event updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $event = Event::findOrFail($id);
        $this->deleteLogo($event->event_logo);
        $event->delete();

        return redirect()->back()->with('success', 'Event deleted successfully.');
    }

    /**
     * Event logos are the one upload served straight from the web root - they're
     * public branding. Everything else (checkpoint photos, signatures) stays on
     * the private 'local' disk behind an authorised controller route.
     */
    private function storeLogo(UploadedFile $file): string
    {
        return $file->store('event-logos', 'public');
    }

    private function deleteLogo(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    // Assign one or more venues to an event
    public function assignVenue(Request $request, int $id): RedirectResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'venue_ids'   => 'required|array|min:1',
            'venue_ids.*' => 'integer|exists:venues,id',
            'purpose'     => 'nullable|string|max:100',
            'notes'       => 'nullable|string',
        ]);

        $pivot = [
            'purpose' => $validated['purpose'] ?? null,
            'notes'   => $validated['notes'] ?? null,
        ];

        // syncWithoutDetaching keeps existing assignments and makes a repeated
        // submit idempotent instead of raising a duplicate-key error.
        $event->venues()->syncWithoutDetaching(
            collect($validated['venue_ids'])->mapWithKeys(fn ($venueId) => [$venueId => $pivot])->all()
        );

        $count = count($validated['venue_ids']);

        return redirect()->back()->with('success', $count === 1
            ? 'Venue assigned to event.'
            : "{$count} venues assigned to event.");
    }

    // Remove a venue from an event
    public function removeVenue(int $id, int $venueId): RedirectResponse
    {
        $event = Event::findOrFail($id);
        $event->venues()->detach($venueId);

        return redirect()->back()->with('success', 'Venue removed from event.');
    }
}
