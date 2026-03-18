<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $stats = [
            'total_orders' => Order::where('courier_id', $user->id)->count(),
            'delivered_orders' => Order::where('courier_id', $user->id)->where('status', 'delivered')->count(),
            'revenue_handled' => (float) Order::where('courier_id', $user->id)->where('status', 'delivered')->sum('total_amount'),
            'rating' => 5.0, // Placeholder
        ];

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
            ],
            'stats' => $stats,
        ]);
    }
}
