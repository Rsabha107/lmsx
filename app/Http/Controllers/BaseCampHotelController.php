<?php

namespace App\Http\Controllers;

use App\Models\BaseCampHotel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BaseCampHotelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('BaseCampHotels', [
            'hotels' => BaseCampHotel::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('base_camp_hotels', 'name')],
            'disabled' => ['boolean'],
        ]);

        BaseCampHotel::create($validated);

        return redirect()->route('base-camp-hotels.index');
    }

    public function update(Request $request, $id)
    {
        $hotel = BaseCampHotel::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('base_camp_hotels', 'name')->ignore($hotel->id)],
            'disabled' => ['boolean'],
        ]);

        $hotel->update($validated);

        return redirect()->route('base-camp-hotels.index');
    }

    public function destroy($id)
    {
        BaseCampHotel::findOrFail($id)->delete();

        return redirect()->route('base-camp-hotels.index');
    }
}
