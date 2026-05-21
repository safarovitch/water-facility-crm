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

  /**
   * Setup-phone gate: shown to authenticated users whose phone_verified_at
   * is null (typically legacy email+password accounts on first login post-
   * migration). Same OTP loop as login.
   */
  public function showSetupPhone(Request $request): Response
  {
    $user = $request->user();
    $existingPhone = $user?->phones()->orderByDesc('is_default')->first()?->phone;

    return Inertia::render('auth/SetupPhone', [
      'userName'      => $user?->name,
      'existingPhone' => $existingPhone,
    ]);
  }

  public function requestSetupOtp(Request $request): RedirectResponse
  {
    $request->validate([
      'phone' => ['required', 'string', 'max:32'],
    ]);

    $info = $this->otp->requestLogin($request->phone);

    return back()->with([
      'phone'        => $info['phone'],
      'deep_link'    => $info['deep_link'],
      'awaiting_otp' => true,
    ]);
  }

  public function verifySetupOtp(Request $request): RedirectResponse
  {
    $data = $request->validate([
      'phone' => ['required', 'string', 'max:32'],
      'code'  => ['required', 'string', 'size:6'],
    ]);

    $user = $request->user();
    $normalized = $this->otp->normalizePhone($data['phone']);

    $otpUser = $this->otp->verifyOtp($normalized, $data['code']);
    if (!$otpUser) {
      throw ValidationException::withMessages([
        'code' => 'Invalid or expired code. Open the Telegram link again for a fresh one.',
      ]);
    }

    // The Telegram-side flow may have created/used a stub user for this
    // phone. If it's a DIFFERENT row from the currently signed-in account,
    // attach the phone to the signed-in account instead and remove the stub.
    if ($otpUser->id !== $user->id) {
      // Move the phone over and discard the stub if it's truly empty (no
      // orders / no profile). Otherwise just attach the phone and leave the
      // stub for the admin to merge via the existing Transfer Profile UI.
      $stub = $otpUser;
      $stubHasData = $stub->orders()->exists() || $stub->userProfile()->exists();

      // Reassign the phone row to the signed-in user.
      \App\Models\UserPhone::where('phone', $normalized)
        ->where('user_id', $stub->id)
        ->update(['user_id' => $user->id]);

      if (!$stubHasData && $stub->claimed_at === null) {
        $stub->roles()->detach();
        $stub->delete();
      }
    } else {
      // Same user — make sure the phone is actually saved on them.
      if (!$user->phones()->where('phone', $normalized)->exists()) {
        $user->phones()->create([
          'phone'      => $normalized,
          'label'      => 'Primary',
          'is_default' => !$user->phones()->exists(),
        ]);
      }
    }

    $user->forceFill(['phone_verified_at' => now()])->save();

    return redirect()->intended(route('dashboard'));
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
