<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index() : Response{
        return Inertia::render('users/Users')->with([
            'users' => User::all(),
            'roles' => Role::all()
        ]);
    }
}
