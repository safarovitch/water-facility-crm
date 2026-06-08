<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPhone;
use App\Services\TelegramOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
   * Direct registration: name + phone + PIN, no Telegram OTP. The account is
   * created (or an unclaimed shell/bot row is adopted) and the user is logged
   * in immediately. Phone verification is deferred — it's required only before
   * the first order (see OrderController::clientStore / EnsurePhoneVerified).
   */
  public function register(Request $request): RedirectResponse
  {
    $data = $request->validate([
      'name'  => ['required', 'string', 'max:255'],
      'phone' => ['required', 'string', 'max:32'],
      'pin'   => ['required', 'string', 'min:4', 'max:6', 'regex:/^\d+$/', 'confirmed'],
    ]);

    $normalized = $this->otp->normalizePhone($data['phone']);

    $existingPhone = UserPhone::where('phone', $normalized)->first();

    // Already-claimed accounts can't be re-registered — send them to sign in.
    if ($existingPhone && $existingPhone->user && $existingPhone->user->claimed_at !== null) {
      throw ValidationException::withMessages([
        'phone' => 'This phone is already registered. Sign in instead.',
      ]);
    }

    if ($existingPhone && $existingPhone->user) {
      // Adopt the unclaimed shell/bot row: name it, set the PIN, claim it.
      $user = $existingPhone->user;
      $user->forceFill([
        'name'       => $data['name'],
        'pin'        => Hash::make($data['pin']),
        'claimed_at' => now(),
      ])->save();
    } else {
      $user = User::create([
        'name'     => $data['name'],
        'email'    => null,
        'pin'      => Hash::make($data['pin']),
        'status'   => 'active',
        'claimed_at' => now(),
      ]);
      $user->assignRole('Client');

      UserPhone::create([
        'user_id'    => $user->id,
        'phone'      => $normalized,
        'label'      => 'Primary',
        'is_default' => true,
      ]);
    }

    Auth::login($user, remember: true);
    $request->session()->regenerate();

    return redirect()->intended(route('dashboard'));
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

  /**
   * Login with phone + PIN (no OTP required for returning users).
   */
  public function loginWithPin(Request $request): RedirectResponse
  {
    $data = $request->validate([
      'phone' => ['required', 'string', 'max:32'],
      'pin'   => ['required', 'string', 'min:4', 'max:6'],
    ]);

    $normalized = $this->otp->normalizePhone($data['phone']);

    $userPhone = UserPhone::where('phone', $normalized)->first();
    if (!$userPhone || !$userPhone->user) {
      throw ValidationException::withMessages([
        'phone' => 'No account found with this phone number.',
      ]);
    }

    $user = $userPhone->user;

    if (!$user->pin || !Hash::check($data['pin'], $user->pin)) {
      throw ValidationException::withMessages([
        'pin' => 'Incorrect PIN.',
      ]);
    }

    Auth::login($user, remember: true);
    $request->session()->regenerate();

    return redirect()->intended(route('dashboard'));
  }

  /**
   * Reset PIN via Telegram OTP (forgot PIN flow).
   */
  public function resetPin(Request $request): RedirectResponse
  {
    $data = $request->validate([
      'phone' => ['required', 'string', 'max:32'],
      'code'  => ['required', 'string', 'size:6'],
      'pin'   => ['required', 'string', 'min:4', 'max:6', 'regex:/^\d+$/', 'confirmed'],
    ]);

    $user = $this->otp->verifyOtp($data['phone'], $data['code']);
    if (!$user) {
      throw ValidationException::withMessages([
        'code' => 'Invalid or expired code.',
      ]);
    }

    $user->forceFill(['pin' => Hash::make($data['pin'])])->save();

    Auth::login($user, remember: true);
    $request->session()->regenerate();

    return redirect()->intended(route('dashboard'));
  }
}
