<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AsteriskAmiService;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function call(Request $request, AsteriskAmiService $amiService)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::with('client.phones')->findOrFail($request->order_id);
        $clientPhone = $order->client->phone;

        if (!$clientPhone) {
            return response()->json(['message' => 'Client phone number not found'], 422);
        }

        $user = $request->user();

        if (empty($user->sip_extension)) {
            return response()->json(['message' => 'Courier SIP extension not configured'], 422);
        }

        try {
            $amiService->connect();
            $amiService->login();

            $amiService->originate(
                "PJSIP/{$user->sip_extension}",
                $clientPhone,
                'from-internal',
                1,
                "Courier <{$user->sip_extension}>"
            );

            $amiService->logoff();

            return response()->json([
                'message' => 'Call initiated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to initiate call',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
