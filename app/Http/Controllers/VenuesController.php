<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Models\Country;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class VenuesController extends Controller
{
    public function index(): Response
    {
        $venues = Venue::with(['country', 'events:id,name,short_name'])
            ->orderBy('name')
            ->get();

        $countries = Country::orderBy('country_name')->get();

        return Inertia::render('Venues', [
            'venues' => $venues,
            'countries' => $countries,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'nullable|string|max:255',
            'country_code' => 'nullable|string|exists:countries,country_code',
            'capacity' => 'nullable|integer|min:0',
            'address' => 'nullable|string|max:500',
            'type' => 'required|in:stadium,training_ground,hotel,conference,other',
            'notes' => 'nullable|string',
        ]);

        Venue::create($validated);

        return redirect()->route('venues.index')->with('success', 'Venue created successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $venue = Venue::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'nullable|string|max:255',
            'country_code' => 'nullable|string|exists:countries,country_code',
            'capacity' => 'nullable|integer|min:0',
            'address' => 'nullable|string|max:500',
            'type' => 'required|in:stadium,training_ground,hotel,conference,other',
            'notes' => 'nullable|string',
        ]);

        $venue->update($validated);

        return redirect()->route('venues.index')->with('success', 'Venue updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $venue = Venue::findOrFail($id);
        $venue->delete();

        return redirect()->route('venues.index')->with('success', 'Venue deleted successfully.');
    }
}
