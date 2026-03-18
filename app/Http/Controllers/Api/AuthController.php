<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
        ]);

        $identifier = $request->identifier;
        
        // Normalize identifier if it looks like a phone number
        try {
            if (str_contains($identifier, '@')) {
                // Keep as is for email
            } else {
                $identifier = (string) phone($identifier, 'AZ', 'E164');
            }
        } catch (\Exception $e) {
            // Fallback to original if not a valid phone either
        }

        $code = (string) rand(100000, 999999);
        
        // In a real app, we would send this via SMS/Email
        Log::info("OTP for {$identifier}: {$code}");

        OtpCode::updateOrCreate(
            ['identifier' => $identifier],
            [
                'code' => $code,
                'expires_at' => \Illuminate\Support\Facades\DB::raw('DATE_ADD(NOW(), INTERVAL 15 MINUTE)'),
            ]
        );

        return response()->json([
            'message' => 'OTP sent successfully (check logs).',
            'identifier' => $identifier, // Return normalized identifier for client consistency
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'code' => 'required|string',
        ]);

        $identifier = $request->identifier;

        $otp = OtpCode::where('identifier', $identifier)
            ->where('code', $request->code)
            ->valid()
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        // Find user by phone (identifier) or email
        $user = User::whereHas('phones', function($q) use ($identifier) {
            $q->where('phone', $identifier);
        })->orWhere('email', $identifier)->first();

        if (!$user) {
             return response()->json(['message' => 'User not found.'], 404);
        }

        $otp->delete();

        $token = $user->createToken('courier-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'role' => $user->getRoleNames()->first(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
