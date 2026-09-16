<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\BaseCampHotel;
use App\Models\Country;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamClassification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
                'countries' => Country::active()->orderBy('country_name')->get(),
                'classifications' => TeamClassification::active()->orderBy('name')->get(),
                'baseCampHotels' => BaseCampHotel::active()->orderBy('name')->get(['id', 'name']),
            ]);
        }

        $activeEvent = Event::with('country')->find($activeEventId);

        $eventTeams = Team::where('event_id', $activeEventId)
            ->with([
                'country',
                'classification',
                'flights' => function ($query) use ($activeEventId) {
                    $query->where('event_id', $activeEventId)
                          ->with(['originAirport', 'destinationAirport'])
                          ->orderBy('scheduled_at');
                },
                'stay' => function ($query) use ($activeEventId) {
                    $query->where('event_id', $activeEventId);
                },
                'training' => function ($query) use ($activeEventId) {
                    $query->where('event_id', $activeEventId);
                }
            ])
            ->orderBy('code')
            ->get();

        return Inertia::render('EventTeams', [
            'activeEvent' => $activeEvent,
            'eventTeams' => $eventTeams,
            'airports' => Airport::orderBy('name')->get(),
            'countries' => Country::active()->orderBy('country_name')->get(),
            'classifications' => TeamClassification::active()->orderBy('name')->get(),
            'baseCampHotels' => BaseCampHotel::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, int $eventId): RedirectResponse
    {
        $event = Event::findOrFail($eventId);

        $validated = $request->validate([
            'code'                    => ['required', 'string', 'max:10', Rule::unique('teams', 'code')->where('event_id', $event->id)],
            'team_name'               => 'required|string|max:255',
            'country_id'              => 'nullable|string|max:10|exists:countries,country_code',
            'flag'                    => 'nullable|string|max:10',
            'group_pool'              => 'nullable|string|max:50',
            'classification_type_id'  => 'nullable|integer|exists:team_classifications,id',
            'head_of_delegation'      => 'nullable|string|max:255',
            'bib_accent_color'        => 'nullable|string|max:20',
            'notes'                   => 'nullable|string',
            'is_active'               => 'boolean',
        ]);

        Team::create([...$validated, 'event_id' => $event->id]);

        return redirect()->back()->with('success', 'Team added.');
    }

    public function update(Request $request, int $eventId, string $code): RedirectResponse
    {
        $team = Team::where('event_id', $eventId)->where('code', $code)->firstOrFail();

        $validated = $request->validate([
            'code'                    => ['required', 'string', 'max:10', Rule::unique('teams', 'code')->where('event_id', $eventId)->ignore($team->id)],
            'team_name'               => 'required|string|max:255',
            'country_id'              => 'nullable|string|max:10|exists:countries,country_code',
            'flag'                    => 'nullable|string|max:10',
            'group_pool'              => 'nullable|string|max:50',
            'classification_type_id'  => 'nullable|integer|exists:team_classifications,id',
            'head_of_delegation'      => 'nullable|string|max:255',
            'bib_accent_color'        => 'nullable|string|max:20',
            'notes'                   => 'nullable|string',
            'is_active'               => 'boolean',
        ]);

        $team->update($validated);

        return redirect()->back()->with('success', 'Team updated.');
    }

    public function destroy(int $eventId, string $code): RedirectResponse
    {
        $team = Team::where('event_id', $eventId)->where('code', $code)->firstOrFail();

        try {
            $team->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'Cannot delete this team while it has existing movements or job operations. Remove those first.');
        }

        return redirect()->back()->with('success', 'Team removed.');
    }
}
