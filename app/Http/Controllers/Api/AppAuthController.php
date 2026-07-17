<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramOtpService;
use App\Support\MobileMenu;
use App\Support\UserAbilities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Auth for the staff management app (any staff role, not just couriers).
 *
 * Two entry points:
 *   - email + password  → immediate Sanctum token (back-office staff)
 *   - phone (identifier) → Telegram OTP flow, same as the courier app
 *
 * Clients are rejected: the management app is a staff tool; what each staff
 * member can do afterwards is driven by /me (roles, abilities, menu).
 */
class AppAuthController extends Controller
{
    public function __construct(private TelegramOtpService $otp) {}

    public function login(Request $request)
    {
        // Password branch — back-office staff sign in with email + password.
        if ($request->filled('email')) {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            if (! $user || ! $user->password || ! Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
            }

            if (! $user->status->is(\App\Enums\UserStatus::Active())) {
                throw ValidationException::withMessages(['email' => 'Sorry. Your account is not active.']);
            }

            $this->ensureStaff($user);

            return $this->tokenResponse($user);
        }

        // OTP branch — phone via Telegram, same transport the courier app uses.
        $request->validate(['identifier' => 'required|string']);

        $phone = $this->otp->normalizePhone($request->identifier);

        $user = User::whereHas('phones', fn ($q) => $q->where('phone', $phone))->first();
        if (! $user || ! $user->isStaff()) {
            return response()->json(['message' => 'User is not registered as staff.'], 404);
        }

        $info = $this->otp->requestLogin($phone);

        return response()->json([
            'message'    => 'OTP requested. Open Telegram to receive your code.',
            'identifier' => $info['phone'],
            'deep_link'  => $info['deep_link'],
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'code'       => 'required|string',
        ]);

        $phone = $this->otp->normalizePhone($request->identifier);

        $user = $this->otp->verifyOtp($phone, $request->code);
        if (! $user || ! $user->isStaff()) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        return $this->tokenResponse($user);
    }

    /**
     * Everything the app needs to build itself for the signed-in user:
     * profile, roles, ability flags and the server-driven menu.
     */
    public function me(Request $request)
    {
        return response()->json($this->payload($request->user()));
    }

    /**
     * Menu alone — cheap to poll after actions that may change badges.
     */
    public function menu(Request $request)
    {
        return response()->json([
            'menu'     => MobileMenu::for($request->user()),
            'sections' => MobileMenu::sections(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    private function ensureStaff(User $user): void
    {
        if (! $user->isStaff()) {
            throw ValidationException::withMessages([
                'email' => 'This account has no staff access. Please use the website.',
            ]);
        }
    }

    private function tokenResponse(User $user)
    {
        $token = $user->createToken('management-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            ...$this->payload($user),
        ]);
    }

    private function payload(User $user): array
    {
        return [
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->getRoleNames(),
            ],
            'abilities' => UserAbilities::for($user),
            'menu'      => MobileMenu::for($user),
            'sections'  => MobileMenu::sections(),
        ];
    }
}
