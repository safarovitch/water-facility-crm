<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class UserPermissionController extends Controller
{
    public function index(): Response
    {
        $pagination = request()->has('pagination')
            ? request()->input('pagination')
            : ['limit' => 50, 'page' => 1];

        return Inertia::render('permissions/Index')->with([
            'permissions' => Permission::query()->paginate(
                $pagination['limit'],
                ['*'],
                'page',
                $pagination['page']
            )
        ]);
    }

    public function create(): Response
    {
        $app_guards = array_keys(config('auth.guards'));
        return Inertia::render('permissions/Create')->with([
            'guards' => $app_guards
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
            'guard_name' => 'required|string|max:255'
        ]);

        Permission::create([
            'name' => $request->input('name'),
            'guard_name' => $request->input('guard_name'),
        ]);

        return to_route('permissions.index')->with('success', __('Permission created successfully'));
    }

    public function edit(Permission $permission): Response
    {
        $app_guards = array_keys(config('auth.guards'));
        return Inertia::render('permissions/Edit')->with([
            'guards' => $app_guards,
            'permission' => $permission
        ]);
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,' . $permission->id,
            'guard_name' => 'required|string|max:255'
        ]);

        $permission->update([
            'name' => $request->input('name'),
            'guard_name' => $request->input('guard_name'),
        ]);

        return to_route('permissions.index')->with('success', __('Permission updated successfully'));
    }
}
