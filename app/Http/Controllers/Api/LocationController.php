<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurrierLocation;
use App\Models\User;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $user = $request->user();

        CurrierLocation::create([
            'user_id' => $user->id,
            'lat' => $request->lat,
            'lng' => $request->lng,
        ]);

        $user->update(['last_active_at' => now()]);

        return response()->json([
            'message' => 'Location updated successfully.',
        ]);
    }

    public function ping(Request $request)
    {
        $request->user()->update(['last_active_at' => now()]);

        return response()->json([
            'message' => 'Pong',
            'server_time' => now(),
        ]);
    }

    /**
     * Public, anonymized feed of currently-active courier positions.
     * Returns only couriers active in the last 15 minutes. The `id` is an
     * opaque hash (no PII) the map uses to match couriers across polls and
     * animate them smoothly between fixes.
     */
    public function publicLocations()
    {
        $cutoff = now()->subMinutes(15);

        $couriers = User::role('Currier')
            ->where('last_active_at', '>=', $cutoff)
            ->with('lastLocation')
            ->get()
            ->filter(fn ($u) => $u->lastLocation !== null);

        $payload = [
            'count' => $couriers->count(),
            'locations' => $couriers->values()->map(fn ($u) => [
                'id' => substr(hash('sha256', $u->id . '|' . config('app.key')), 0, 12),
                'lat' => (float) $u->lastLocation->lat,
                'lng' => (float) $u->lastLocation->lng,
                'updated_at' => $u->lastLocation->updated_at->toIso8601String(),
            ]),
        ];

        return response()
            ->json($payload)
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
