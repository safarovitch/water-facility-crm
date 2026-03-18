<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourierLocation;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        CourierLocation::create([
            'user_id' => $request->user()->id,
            'lat' => $request->lat,
            'lng' => $request->lng,
        ]);

        return response()->json([
            'message' => 'Location updated successfully.',
        ]);
    }
}
