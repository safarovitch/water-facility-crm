<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Propaganistas\LaravelPhone\Rules\Phone;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required_without:phone', 'nullable', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required_without:email', 'nullable', 'unique:user_phones,phone', new Phone('TJ')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole('Client');

        if ($request->has('phone') && $request->phone) {
            $user->phones()->create([
                'phone' => $request->phone,
                'label' => 'Primary',
                'is_default' => true,
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}
