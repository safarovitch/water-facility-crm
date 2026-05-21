<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use App\Models\UserPhone;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Phone+Telegram OTP service.
 *
 * Flow:
 *   1. requestLogin($phone) → caches phone↔token (10 min), returns a Telegram
 *      deep link `t.me/<bot>?start=login_<token>`.
 *   2. User opens link → bot's /start handler calls resolveLoginToken($token)
 *      to find the phone, link the chat, and dispatchOtp() the code via DM.
 *   3. User reads code in Telegram, returns to web → verifyOtp($phone, $code)
 *      → success returns the User row, ready to be Auth::login()'d.
 */
class TelegramOtpService
{
  private const LOGIN_TOKEN_TTL_SECONDS = 600; // 10 minutes
  private const OTP_TTL_MINUTES = 5;
  private const CACHE_PREFIX = 'phone_otp:login_token:';

  /**
   * Stage a login attempt for $phone and return the Telegram deep link the
   * user should open. We do NOT send the OTP yet — that happens once the bot
   * receives /start with the token and we know which chat to DM.
   */
  public function requestLogin(string $phone): array
  {
    $normalized = $this->normalizePhone($phone);

    $token = Str::random(32);
    Cache::put(self::CACHE_PREFIX . $token, $normalized, self::LOGIN_TOKEN_TTL_SECONDS);

    $bot = $this->getBot();
    $username = $this->getBotUsername($bot);

    return [
      'phone'     => $normalized,
      'token'     => $token,
      'deep_link' => $username
        ? "https://t.me/{$username}?start=login_{$token}"
        : null,
    ];
  }

  /**
   * Called from the bot's /start handler. Returns the phone for the token
   * (and consumes it), or null if expired.
   */
  public function resolveLoginToken(string $token): ?string
  {
    $key = self::CACHE_PREFIX . $token;
    $phone = Cache::pull($key);
    return is_string($phone) ? $phone : null;
  }

  /**
   * Bind the Telegram chat to a user (creating a shell user if needed),
   * generate an OTP, persist it for the given phone, and DM it via the bot.
   *
   * Returns the OtpCode row so callers can echo a confirmation if desired.
   */
  public function dispatchOtp(TelegraphChat $chat, string $phone): OtpCode
  {
    $normalized = $this->normalizePhone($phone);
    $user = $this->resolveOrStubUser($normalized, $chat);

    // Link the chat to the user so future logins can DM without a deep-link.
    if ((int) $chat->user_id !== (int) $user->id) {
      $chat->user_id = $user->id;
      $chat->save();
    }

    $code = (string) random_int(100000, 999999);
    $otp = OtpCode::updateOrCreate(
      ['identifier' => $normalized],
      ['code' => $code, 'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES)],
    );

    try {
      $chat->message("🔐 Ваш код входа: *{$code}*\n\nКод действителен 5 минут. Никому его не сообщайте.")
        ->send();
    } catch (\Throwable $e) {
      // Failing to send is logged but not fatal — the OTP is still in DB and
      // we can show it to the user via the fallback log path during dev.
      Log::warning('TelegramOtpService: failed to send OTP DM', [
        'chat_id' => $chat->chat_id,
        'phone'   => $normalized,
        'error'   => $e->getMessage(),
      ]);
    }

    return $otp;
  }

  /**
   * Validate a submitted OTP for $phone. On success consumes the code and
   * returns the User; otherwise null.
   */
  public function verifyOtp(string $phone, string $code): ?User
  {
    $normalized = $this->normalizePhone($phone);

    $otp = OtpCode::where('identifier', $normalized)
      ->where('code', trim($code))
      ->valid()
      ->first();
    if (!$otp) return null;

    $userPhone = UserPhone::where('phone', $normalized)->first();
    if (!$userPhone) return null;

    $user = $userPhone->user;
    if (!$user) return null;

    $otp->delete();

    // First successful login claims the shell row, if applicable.
    if ($user->claimed_at === null) {
      $user->forceFill(['claimed_at' => now()])->save();
    }

    return $user;
  }

  /**
   * Find an existing user with the phone or, if none, create a Client-shell
   * user we can bind to. The Telegram contact name (when shared) is used as a
   * sensible default name.
   */
  private function resolveOrStubUser(string $phone, TelegraphChat $chat): User
  {
    $userPhone = UserPhone::where('phone', $phone)->first();
    if ($userPhone) {
      return $userPhone->user;
    }

    $user = User::create([
      'name'     => $chat->name ?: 'Telegram client',
      'email'    => null,
      'password' => bcrypt(Str::random(32)),
      'status'   => 'active',
      // claimed_at stays null until verifyOtp() succeeds — shell user.
    ]);
    $user->assignRole('Client');

    UserPhone::create([
      'user_id'    => $user->id,
      'phone'      => $phone,
      'label'      => 'Telegram',
      'is_default' => true,
    ]);

    return $user;
  }

  private function getBot(): ?TelegraphBot
  {
    return TelegraphBot::query()->first();
  }

  /**
   * Telegraph stores the bot username on `info->username` after a getMe()
   * call. We don't strictly require it — config('telegraph.bot_username')
   * can override.
   */
  private function getBotUsername(?TelegraphBot $bot): ?string
  {
    if (config('telegraph.bot_username')) {
      return config('telegraph.bot_username');
    }
    if (!$bot) return null;
    try {
      return $bot->info()?->username ?? null;
    } catch (\Throwable) {
      return null;
    }
  }

  public function normalizePhone(string $phone): string
  {
    $trimmed = trim($phone);
    try {
      return (string) phone($trimmed, ['TJ', 'RU', 'UZ', 'AZ', 'US'], 'E164');
    } catch (\Throwable) {
      return preg_replace('/[^\d+]/', '', $trimmed);
    }
  }
}
