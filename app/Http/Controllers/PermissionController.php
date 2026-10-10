<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:permissions,name',
        ]);

        $permission = Permission::create(['name' => $data['name'], 'guard_name' => 'web']);

        Log::info("Permission created: {$permission->name}");
        AuditLog::change('Permission created', $permission->name);

        return redirect()->route('setups.access.index')->with('success', 'Permission created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $permission = Permission::findOrFail($id);

        $data = $request->validate([
            'name' => "required|string|max:100|unique:permissions,name,{$id}",
        ]);

        $oldName = $permission->name;
        $permission->update(['name' => $data['name']]);

        Log::info("Permission updated: {$permission->name}");
        AuditLog::change('Permission updated', $permission->name, ['renamed_from' => $oldName]);

        return redirect()->route('setups.access.index')->with('success', 'Permission updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $permission = Permission::findOrFail($id);
        $name = $permission->name;
        $permission->delete();

        Log::info("Permission deleted: {$name}");
        AuditLog::change('Permission deleted', $name);

        return redirect()->route('setups.access.index')->with('success', 'Permission deleted.');
    }
}
