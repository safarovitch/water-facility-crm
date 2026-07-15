<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminModeController extends Controller
{
    /**
     * Toggle admin mode for the current session.
     * Only accessible to users with a non-client role.
     */
    public function switch(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Guard: client-only users cannot enter admin mode
        if (! $user->isStaff()) {
            return redirect()->route('dashboard');
        }

        $currentMode = $request->session()->get('admin_mode', false);
        $newMode     = ! $currentMode;

        $request->session()->put('admin_mode', $newMode);

        // When switching INTO admin mode → go to /admin dashboard
        // When switching OUT of admin mode → go to /profile
        return $newMode
            ? redirect()->route('admin.dashboard')
            : redirect()->route('dashboard');
    }
}
