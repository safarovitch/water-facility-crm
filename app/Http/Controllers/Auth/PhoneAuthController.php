<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPhone;
use App\Services\TelegramOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PhoneAuthController extends Controller
{
  public function __construct(private TelegramOtpService $otp) {}

  /**
   * Render the phone-first login screen (Inertia page).
   * The page handles all three steps: enter phone → open Telegram → enter OTP.
   */
  public function showLogin(): Response
  {
    return Inertia::render('auth/PhoneLogin');
  }

  public function showRegister(): Response
  {
    return Inertia::render('auth/PhoneRegister');
  }

  /**
   * Stage a login attempt for a phone and return the Telegram deep link.
   * The OTP itself is sent by the bot once the user opens the link.
   */
  public function requestLoginOtp(Request $request): RedirectResponse
  {
    $request->validate([
      'phone' => ['required', 'string', 'max:32'],
    ]);

    $info = $this->otp->requestLogin($request->phone);

    return back()->with([
      'phone'     => $info['phone'],
      'deep_link' => $info['deep_link'],
      'awaiting_otp' => true,
    ]);
  }

  /**
   * Verify the OTP and log the user in via session. If the phone has no
   * user yet, that's a registration attempt — bounce them with a hint.
   */
  public function verifyLoginOtp(Request $request): RedirectResponse
  {
    $request->validate([
      'phone' => ['required', 'string', 'max:32'],
      'code'  => ['required', 'string', 'size:6'],
    ]);

    $user = $this->otp->verifyOtp($request->phone, $request->code);
    if (!$user) {
      throw ValidationException::withMessages([
        'code' => 'Invalid or expired code. Open the Telegram link again for a fresh one.',
      ]);
    }

    Auth::login($user, remember: true);
    $request->session()->regenerate();

    return redirect()->intended(route('dashboard'));
  }

  /**
   * Stage a registration. Same OTP loop as login, but if the user hasn't
   * verified yet we'll create the shell row when the bot dispatches the OTP
   * (the OTP service stubs a user keyed by phone). On verifyRegistration()
   * we update the user's name + claim the row.
   */
  public function requestRegisterOtp(Request $request): RedirectResponse
  {
    $data = $request->validate([
      'name'  => ['required', 'string', 'max:255'],
      'phone' => ['required', 'string', 'max:32'],
    ]);

    $normalized = $this->otp->normalizePhone($data['phone']);

    // Block already-claimed accounts from being re-registered. Shell users
    // (claimed_at IS NULL) are adoptable.
    $existingPhone = UserPhone::where('phone', $normalized)->first();
    if ($existingPhone && $existingPhone->user && $existingPhone->user->claimed_at !== null) {
      throw ValidationException::withMessages([
        'phone' => 'This phone is already registered. Use it to sign in.',
      ]);
    }

    $info = $this->otp->requestLogin($normalized);
    session()->flash('pending_register_name', $data['name']);

    return back()->with([
      'phone'        => $info['phone'],
      'deep_link'    => $info['deep_link'],
      'name'         => $data['name'],
      'awaiting_otp' => true,
    ]);
  }

  public function verifyRegisterOtp(Request $request): RedirectResponse
  {
    $data = $request->validate([
      'name'  => ['required', 'string', 'max:255'],
      'phone' => ['required', 'string', 'max:32'],
      'code'  => ['required', 'string', 'size:6'],
    ]);

    $user = $this->otp->verifyOtp($data['phone'], $data['code']);
    if (!$user) {
      throw ValidationException::withMessages([
        'code' => 'Invalid or expired code.',
      ]);
    }

    // First-time registration: update the auto-created shell name to what
    // the user just typed in. If the row already had a name from a prior
    // walk-in entry, we keep it unless the user overrides.
    if ($user->name !== $data['name']) {
      $user->forceFill(['name' => $data['name']])->save();
    }

    Auth::login($user, remember: true);
    $request->session()->regenerate();

    return redirect()->intended(route('dashboard'));
  }
}
