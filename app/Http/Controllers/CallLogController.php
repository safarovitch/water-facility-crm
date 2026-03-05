<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class CallLogController extends Controller
{
  /**
   * Display a listing of the recent calls.
   */
  public function index(Request $request)
  {
    $calls = Activity::where('log_name', 'call')
      ->where('causer_id', $request->user()->id)
      ->where('causer_type', get_class($request->user()))
      ->latest()
      ->limit(50)
      ->get();

    return response()->json($calls);
  }

  /**
   * Store a newly created call log in storage.
   */
  public function store(Request $request)
  {
    $validated = $request->validate([
      'phone' => 'required|string|max:255',
      'duration' => 'required|integer|min:0',
      'direction' => 'required|in:inbound,outbound',
    ]);

    $activity = activity('call')
      ->causedBy($request->user())
      ->withProperties([
        'phone' => $validated['phone'],
        'duration' => $validated['duration'],
        'direction' => $validated['direction'],
      ])
      ->log('Call ' . $validated['direction'] . ' to/from ' . $validated['phone']);

    return response()->json($activity, 201);
  }
}
