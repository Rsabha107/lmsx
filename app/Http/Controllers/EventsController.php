<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Country;
use App\Models\Event;
use App\Models\MovementTemplate;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            'fleetProviders'  => \App\Models\FleetProvider::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'short_name'   => 'nullable|string|max:100',
            'host_country' => 'nullable|string|max:10|exists:countries,country_code',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'active_flag'  => 'sometimes|boolean',
            'notes'        => 'nullable|string',
            'fleet_provider_id' => 'nullable|integer|exists:fleet_providers,id',
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
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'active_flag'  => 'sometimes|boolean',
            'notes'        => 'nullable|string',
            'fleet_provider_id' => 'nullable|integer|exists:fleet_providers,id',
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

    /** Child-first, so each step is FK-safe on its own; the final 'event' step cascades the rest. */
    private const DELETE_STEPS = [
        'job_checkpoints' => ['Job checkpoints', 'job_checkpoints'],
        'jobs' => ['Jobs', 'jobs_operations'],
        'movements' => ['Movements', 'movements'],
        'plans' => ['Plans', 'plans'],
        'movement_templates' => ['Movement templates and their legs', 'movement_templates'],
    ];

    /** What deleting the event will remove, in the order destroyStep() runs it. */
    public function deletionPlan(int $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $count = fn (string $table) => DB::table($table)->where('event_id', $id)->count();

        $steps = [];
        foreach (self::DELETE_STEPS as $key => [$label, $table]) {
            $steps[] = ['key' => $key, 'label' => $label, 'count' => $count($table)];
        }

        $rest = ['teams' => 'teams', 'team_flights' => 'flights', 'team_stays' => 'stays', 'checkpoint_templates' => 'checkpoint templates'];
        $detail = collect($rest)->map(fn ($label, $table) => $count($table).' '.$label)->implode(', ');

        $steps[] = ['key' => 'event', 'label' => "The event itself, with its {$detail}", 'count' => 1];

        return response()->json(['steps' => $steps]);
    }

    public function destroyStep(Request $request, int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $data = $request->validate([
            'step' => ['required', Rule::in([...array_keys(self::DELETE_STEPS), 'event'])],
            'confirm_name' => ['required', Rule::in([$event->name])],
        ]);

        if ($data['step'] === 'event') {
            DB::transaction(fn () => $event->delete());
            $this->deleteLogo($event->event_logo);

            return response()->json(['deleted' => 1]);
        }

        [, $table] = self::DELETE_STEPS[$data['step']];

        return response()->json(['deleted' => DB::table($table)->where('event_id', $id)->delete()]);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $event = Event::findOrFail($id);

        $request->validate(['confirm_name' => ['required', Rule::in([$event->name])]]);

        // movement_template_legs restricts deleting a checkpoint template, so the event's movement
        // templates (whose legs cascade) must go before the event cascades to its checkpoint templates.
        DB::transaction(function () use ($event) {
            MovementTemplate::where('event_id', $event->id)->delete();
            $event->delete();
        });

        $this->deleteLogo($event->event_logo);

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
