<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Setups/Permissions', [
            'permissions' => Permission::withCount('roles')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:permissions,name',
        ]);

        $permission = Permission::create(['name' => $data['name'], 'guard_name' => 'web']);

        Log::info("Permission created: {$permission->name}");

        return redirect()->route('permissions.index')->with('success', 'Permission created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $permission = Permission::findOrFail($id);

        $data = $request->validate([
            'name' => "required|string|max:100|unique:permissions,name,{$id}",
        ]);

        $permission->update(['name' => $data['name']]);

        Log::info("Permission updated: {$permission->name}");

        return redirect()->route('permissions.index')->with('success', 'Permission updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $permission = Permission::findOrFail($id);
        $name = $permission->name;
        $permission->delete();

        Log::info("Permission deleted: {$name}");

        return redirect()->route('permissions.index')->with('success', 'Permission deleted.');
    }
}
