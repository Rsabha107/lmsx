<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AirportController extends Controller
{
    public function index(): Response
    {
        $airports = Airport::withCount(['flightsAsOrigin', 'flightsAsDestination', 'teamsAsOrigin', 'teamsAsDestination'])
            ->orderBy('code')
            ->get()
            ->map(fn (Airport $airport) => [
                'id' => $airport->id,
                'code' => $airport->code,
                'name' => $airport->name,
                'city' => $airport->city,
                'country' => $airport->country,
                'flights_count' => $airport->flights_as_origin_count + $airport->flights_as_destination_count,
                'teams_count' => $airport->teams_as_origin_count + $airport->teams_as_destination_count,
            ]);

        return Inertia::render('Airports', ['airports' => $airports]);
    }

    public function store(Request $request): RedirectResponse
    {
        Airport::create($this->validated($request));

        return redirect()->route('airports.index')->with('success', 'Airport added.');
    }

    public function update(Request $request, Airport $airport): RedirectResponse
    {
        $airport->update($this->validated($request, $airport));

        return redirect()->route('airports.index')->with('success', 'Airport updated.');
    }

    /**
     * Refused while in use: the foreign keys are ON DELETE SET NULL, so deleting
     * would silently blank the airport on every flight and team that uses it.
     */
    public function destroy(Airport $airport): RedirectResponse
    {
        $flights = $airport->flightsAsOrigin()->count() + $airport->flightsAsDestination()->count();
        $teams = $airport->teamsAsOrigin()->count() + $airport->teamsAsDestination()->count();

        if ($flights + $teams > 0) {
            return back()->withErrors([
                'airport' => "{$airport->code} is used by {$flights} flight(s) and {$teams} team(s). Change those first.",
            ]);
        }

        $airport->delete();

        return redirect()->route('airports.index')->with('success', 'Airport deleted.');
    }

    /**
     * @return array{code: string, name: string, city: ?string, country: ?string}
     */
    private function validated(Request $request, ?Airport $airport = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        // Every save carries the full record, so an omitted city/country means cleared, not kept.
        return [
            'city' => null,
            'country' => null,
            ...$request->validate([
                'code' => ['required', 'string', 'max:10', 'alpha_num', Rule::unique('airports', 'code')->ignore($airport)],
                'name' => ['required', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:255'],
                'country' => ['nullable', 'string', 'max:255'],
            ]),
        ];
    }
}
