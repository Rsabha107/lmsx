<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
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

        $this->guardSecurityRole($request, $data['roles'] ?? [], null);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'fleet_provider_id' => $data['fleet_provider_id'] ?? null,
        ]);

        $user->syncRoles($data['roles'] ?? []);
        $user->events()->sync($data['events'] ?? []);

        Log::info("User created: {$user->email}");
        AuditLog::change('User created', $user->email, $this->snapshot($user), $user);

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

        $this->guardSecurityRole($request, $data['roles'] ?? [], $user);

        $before = $this->snapshot($user);

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
        AuditLog::change('User updated', $user->email, ['from' => $before, 'to' => $this->snapshot($user->refresh()), 'password_changed' => ! empty($data['password'])], $user);

        return redirect()->route('setups.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['delete' => 'You cannot delete your own account.']);
        }

        abort_if(
            $user->hasRole(User::SECURITY_ROLE) && ! $request->user()->can(User::ACCESS_PERMISSION),
            403,
            'Only the SecurityRole can delete a SecurityRole holder.'
        );

        $email = $user->email;
        $snapshot = $this->snapshot($user);
        $user->delete();

        Log::info("User deleted: {$email}");
        AuditLog::change('User deleted', $email, $snapshot);

        return redirect()->route('setups.users.index')->with('success', 'User deleted.');
    }

    /** What an audit entry records about a user: access, never credentials. */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'roles' => $user->getRoleNames()->sort()->values()->all(),
            'provider_id' => $user->fleet_provider_id,
            'events' => $user->events()->pluck('events.id')->sort()->values()->all(),
        ];
    }

    /** Granting, removing or deleting a SecurityRole holder needs access.manage, or any admin could hand it to themselves. */
    private function guardSecurityRole(Request $request, array $roleIds, ?User $user): void
    {
        $securityId = Role::where('name', User::SECURITY_ROLE)->value('id');

        if (! $securityId || $request->user()->can(User::ACCESS_PERMISSION)) {
            return;
        }

        $wants = in_array($securityId, array_map('intval', $roleIds), true);
        $has = $user?->hasRole(User::SECURITY_ROLE) ?? false;

        abort_if($wants !== $has, 403, 'Only the SecurityRole can grant or remove the SecurityRole.');
    }
}
