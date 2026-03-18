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
        $code = (string) rand(100000, 999999);
        
        // In a real app, we would send this via SMS/Email
        Log::info("OTP for {$identifier}: {$code}");

        OtpCode::updateOrCreate(
            ['identifier' => $identifier],
            [
                'code' => $code, // Ideally hashed, but for simple OTP string is fine if short-lived
                'expires_at' => now()->addMinutes(10),
            ]
        );

        return response()->json([
            'message' => 'OTP sent successfully (check logs).',
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'code' => 'required|string',
        ]);

        $otp = OtpCode::where('identifier', $request->identifier)
            ->where('code', $request->code)
            ->valid()
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        // Find user by phone (identifier) or create if phone matches a pattern
        // For couriers, we expect them to exist in the system already
        $user = User::whereHas('phones', function($q) use ($request) {
            $q->where('phone', $request->identifier);
        })->orWhere('email', $request->identifier)->first();

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
