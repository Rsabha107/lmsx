<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Setups/Roles', [
            'roles'       => Role::with('permissions:id,name')
                ->withCount('permissions')
                ->orderBy('name')
                ->get(),
            'permissions' => Permission::orderBy('name')->get(['id', 'name']),
        ]);
    }

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

        return redirect()->route('roles.index')->with('success', 'Role created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $role = Role::findOrFail($id);

        $data = $request->validate([
            'name'          => "required|string|max:100|unique:roles,name,{$id}",
            'permissions'   => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        Log::info("Role updated: {$role->name}");

        return redirect()->route('roles.index')->with('success', 'Role updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $role = Role::findOrFail($id);
        $name = $role->name;
        $role->delete();

        Log::info("Role deleted: {$name}");

        return redirect()->route('roles.index')->with('success', 'Role deleted.');
    }
}
