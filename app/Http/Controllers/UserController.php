<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\FleetProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Setups/Users', [
            'users' => User::with(['roles:id,name', 'events:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'fleet_provider_id', 'created_at']),
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'events' => Event::orderByDesc('active_flag')->orderByDesc('id')->get(['id', 'name', 'short_name']),
            'fleetProviders' => FleetProvider::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'roles'    => 'nullable|array',
            'roles.*'  => 'integer|exists:roles,id',
            'events'   => 'nullable|array',
            'events.*' => 'integer|exists:events,id',
            'fleet_provider_id' => 'nullable|integer|exists:fleet_providers,id',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'fleet_provider_id' => $data['fleet_provider_id'] ?? null,
        ]);

        $user->syncRoles($data['roles'] ?? []);
        $user->events()->sync($data['events'] ?? []);

        Log::info("User created: {$user->email}");

        return redirect()->route('setups.users.index')->with('success', 'User created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => "required|email|max:255|unique:users,email,{$id}",
            'password' => 'nullable|string|min:8',
            'roles'    => 'nullable|array',
            'roles.*'  => 'integer|exists:roles,id',
            'events'   => 'nullable|array',
            'events.*' => 'integer|exists:events,id',
            'fleet_provider_id' => 'nullable|integer|exists:fleet_providers,id',
        ]);

        $user->name  = $data['name'];
        $user->email = $data['email'];
        $user->fleet_provider_id = $data['fleet_provider_id'] ?? null;

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->syncRoles($data['roles'] ?? []);
        $user->events()->sync($data['events'] ?? []);

        Log::info("User updated: {$user->email}");

        return redirect()->route('setups.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['delete' => 'You cannot delete your own account.']);
        }

        $email = $user->email;
        $user->delete();

        Log::info("User deleted: {$email}");

        return redirect()->route('setups.users.index')->with('success', 'User deleted.');
    }
}
