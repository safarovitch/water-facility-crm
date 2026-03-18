<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));

        Fortify::authenticateUsing(function (Request $request) {
            $identifier = $request->email; // Fortify default field name
            
            // Normalize phone if applicable
            try {
                if (!str_contains($identifier, '@')) {
                    $identifier = (string) phone($identifier, 'AZ', 'E164');
                }
            } catch (\Exception $e) {}

            $user = \App\Models\User::whereHas('phones', function($q) use ($identifier) {
                    $q->where('phone', $identifier);
                })
                ->orWhere('email', $identifier)
                ->first();

            if ($user && \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
                return $user;
            }
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
