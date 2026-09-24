<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The combined Roles & Permissions screen. Read-only: every change still goes
 * through RoleController / PermissionController, which own the validation and
 * audit logging for these privilege-granting operations.
 */
class AccessController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Setups/Access', [
            'roles' => Role::with('permissions:id,name')
                ->withCount('permissions')
                ->orderBy('name')
                ->get(['id', 'name', 'guard_name', 'created_at']),
            'permissions' => Permission::with('roles:id,name')
                ->withCount('roles')
                ->orderBy('name')
                ->get(['id', 'name', 'guard_name', 'created_at']),
        ]);
    }
}
