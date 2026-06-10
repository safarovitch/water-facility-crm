<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    public function index(): Response
    {
        $query = Role::with(['permissions']);

        if (request()->filled('search')) {
            $query->where('name', 'like', '%' . request()->search . '%');
        }

        return Inertia::render('roles/Index')->with([
            'roles' => $query->paginate(request()->integer('per_page', 50))->withQueryString(),
            'filters' => request()->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $app_guards = array_keys(config('auth.guards'));
        $permissions = Permission::all();
        return Inertia::render('roles/Create')->with([
            'guards' => $app_guards,
            'permissions' => $permissions
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required',
            'guard_name' => 'required',
            'permissions' => 'required|array'
        ]);

        $role = Role::create([
            'name' => $request->input('name'),
            'guard_name' => $request->input('guard_name'),
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->input('permissions'));
        }
        return to_route('admin.roles.index')->with('success', __('Role created successfully'));
    }

    public function edit(Role $role): Response
    {
        $app_guards = array_keys(config('auth.guards'));
        $permissions = Permission::all();
        
        return Inertia::render('roles/Edit')->with([
            'role' => $role->load('permissions'),
            'guards' => $app_guards,
            'permissions' => $permissions
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $request->validate([
            'name' => 'required',
            'guard_name' => 'required',
            'permissions' => 'required|array'
        ]);

        $role->update([
            'name' => $request->input('name'),
            'guard_name' => $request->input('guard_name'),
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->input('permissions'));
        }
        return to_route('admin.roles.index')->with('success', __('Role updated successfully'));
    }
}
