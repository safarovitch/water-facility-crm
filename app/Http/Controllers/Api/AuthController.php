<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPhone;
use App\Services\TelegramOtpService;
use Illuminate\Http\Request;

/**
 * Courier mobile app auth. Couriers receive their 6-digit code via the same
 * Telegram bot (TELEGRAM_ORDER_BOT_TOKEN) used by the web flow — there is no
 * SMS gateway, so the courier must have the bot opened at least once with
 * their phone shared as a contact.
 */
class AuthController extends Controller
{
  public function __construct(private TelegramOtpService $otp) {}

  /**
   * Stage an OTP for a courier phone. Returns a Telegram deep-link the app
   * can open — once the courier opens it the bot DMs the code, OR (if the
   * courier already linked their phone earlier) the bot will DM the code
   * the moment they tap the link.
   */
  public function login(Request $request)
  {
    $request->validate(['identifier' => 'required|string']);

    $phone = $this->otp->normalizePhone($request->identifier);

    // Block non-couriers. We require the courier to already exist; the
    // courier app is not a self-signup channel.
    $user = User::whereHas('phones', fn ($q) => $q->where('phone', $phone))->first();
    if (!$user || !$user->hasRole('Currier')) {
      return response()->json(['message' => 'User not registered.'], 404);
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
    if (!$user || !$user->hasRole('Currier')) {
      return response()->json(['message' => 'Invalid or expired OTP.'], 422);
    }

    $token = $user->createToken('courier-app')->plainTextToken;

    return response()->json([
      'token' => $token,
      'user'  => [
        'id'    => $user->id,
        'name'  => $user->name,
        'phone' => $user->phone,
        'role'  => $user->getRoleNames()->first(),
      ],
    ]);
  }

  public function logout(Request $request)
  {
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => 'Logged out successfully.']);
  }
}
