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

        $identifier = $this->normalizeIdentifier($request->identifier);
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
            'identifier' => $identifier,
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'code' => 'required|string',
        ]);

        $identifier = $this->normalizeIdentifier($request->identifier);

        $otp = OtpCode::where('identifier', $identifier)
            ->where('code', $request->code)
            ->valid()
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        // Find user by normalized identifier
        if (str_contains($identifier, '@')) {
            $user = User::where('email', $identifier)->first();
        } else {
            $user = User::whereHas('phones', function($q) use ($identifier) {
                $q->where('phone', $identifier);
            })->first();
        }

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

    private function normalizeIdentifier(string $identifier): string
    {
        if (str_contains($identifier, '@')) {
            return strtolower(trim($identifier));
        }

        try {
            // Use laravel-phone to normalize. If + is present, it auto-detects.
            // Default to AZ if no plus, but if + is present it will correctly handle US (+1) etc.
            return (string) phone($identifier, ['AZ', 'US', 'RU'], 'E164');
        } catch (\Exception $e) {
            // Strip everything but numbers and + for a basic cleanup if library fails
            return preg_replace('/[^\d+]/', '', $identifier);
        }
    }
}
