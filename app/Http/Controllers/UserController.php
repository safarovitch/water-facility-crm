<?php

namespace App\Http\Controllers;

use App\Enums\UserActivityStatus;
use App\Enums\UserStatus;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): Response
    {
        $pagination = request()->has('pagination')
            ? request()->input('pagination')
            : ['limit' => 50, 'page' => 1];

        return Inertia::render('users/Index')->with([
            'users' => User::query()->excludeSelf()->with('roles')->paginate(
                $pagination['limit'],
                ['*'],
                'page',
                $pagination['page']
            ),
            'roles' => Role::all(),
            'statuses' => UserStatus::asArray(),
            'activityStatuses' => UserActivityStatus::asArray(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/Create')->with([
            'roles' => Role::all(),
            'statuses' => UserStatus::asArray()
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = User::create($data);
        if (isset($data['roles'])) {
            $user->assignRole($data['roles']);
        }

        if ($request->file('avatar') != null) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
            $user->save();
        }

        return redirect()->route('users.index')->with('success', __('User created successfully'));
    }

    public function edit(User $user): Response
    {
        return Inertia::render('users/Edit')->with([
            'roles' => Role::all(),
            'statuses' => UserStatus::asArray(),
            'user' => $user->load('roles')
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = bcrypt($data['password']);
        }

        $user->update($data);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if ($request->file('avatar') != null) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
            $user->save();
            Cache::forget('avatar_url' . $user->id);
        }

        if($request->input('avatar_remove') == '1') {
            $user->avatar = null;
            $user->save();
            Cache::forget('avatar_url' . $user->id);
        }

        return redirect()->route('users.index')->with('success', __('User updated successfully'));
    }
}
