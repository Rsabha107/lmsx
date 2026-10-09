<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100|unique:roles,name',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        Log::info("Role created: {$role->name}");

        return redirect()->route('setups.access.index')->with('success', 'Role created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $role = Role::findOrFail($id);

        $data = $request->validate([
            'name'          => "required|string|max:100|unique:roles,name,{$id}",
            'permissions'   => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        if (in_array($role->name, User::PROTECTED_ROLES, true) && $data['name'] !== $role->name) {
            return back()->withErrors(['name' => "The {$role->name} role is built in and cannot be renamed."]);
        }

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        Log::info("Role updated: {$role->name}");

        return redirect()->route('setups.access.index')->with('success', 'Role updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $role = Role::findOrFail($id);

        abort_if(in_array($role->name, User::PROTECTED_ROLES, true), 403, "The {$role->name} role is built in and cannot be deleted.");

        $name = $role->name;
        $role->delete();

        Log::info("Role deleted: {$name}");

        return redirect()->route('setups.access.index')->with('success', 'Role deleted.');
    }
}
