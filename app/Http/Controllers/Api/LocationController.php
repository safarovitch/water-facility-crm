<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurrierLocation;
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
}
