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

    if (!$username) {
      Log::error('TelegramOtpService: bot username unavailable — deep link cannot be built. Set TELEGRAM_ORDER_BOT_USERNAME or run app:setup-telegram-bot.', [
        'phone' => $normalized,
      ]);
      $this->generateAndLogOtp($normalized);
    }

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
      $chat->message(
        "🔐 Ваш код входа: <b>{$code}</b>\n\nКод действителен 5 минут. Никому его не сообщайте."
      )->send();
    } catch (\Throwable $e) {
      // Failing to send is logged but not fatal — the OTP is still in DB and
      // we can show it to the user via the fallback log path during dev.
      Log::error('TelegramOtpService: failed to send OTP DM', [
        'chat_id' => $chat->chat_id,
        'phone'   => $normalized,
        'error'   => $e->getMessage(),
        'class'   => $e::class,
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

    // First successful OTP verification claims the shell row and marks the
    // phone verified — clears the migration nudge for legacy email accounts.
    $updates = [];
    if ($user->claimed_at === null) $updates['claimed_at'] = now();
    if ($user->phone_verified_at === null) $updates['phone_verified_at'] = now();
    if ($updates) $user->forceFill($updates)->save();

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

  /**
   * Resolve the TelegraphBot row matching the configured order bot token
   * (TELEGRAM_ORDER_BOT_TOKEN). Falls back to the first bot if the token is
   * missing or no exact match is found, but logs a warning so the issue is
   * visible during setup.
   */
  private function getBot(): ?TelegraphBot
  {
    $token = config('telegraph.bot_token');
    if ($token) {
      $bot = TelegraphBot::where('token', $token)->first();
      if ($bot) return $bot;
      Log::warning('TelegramOtpService: TELEGRAM_ORDER_BOT_TOKEN does not match any telegraph_bots row. Run `php artisan app:setup-telegram-bot`.');
    }
    return TelegraphBot::query()->first();
  }

  /**
   * Bot username (for building t.me/<username>?start=… links). Cached for 24h
   * because it almost never changes. Order of resolution:
   *   1. config('telegraph.bot_username') / TELEGRAM_ORDER_BOT_USERNAME env
   *   2. Cached value from a prior getMe() call
   *   3. Live getMe() call to the Telegram API, then cache the result
   */
  private function getBotUsername(?TelegraphBot $bot): ?string
  {
    $configured = config('telegraph.bot_username');
    if ($configured) return $configured;

    if (!$bot) return null;

    $cacheKey = 'telegraph:bot_username:' . $bot->id;
    $cached = Cache::get($cacheKey);
    if ($cached) return $cached;

    try {
      $info = $bot->info();
      $username = is_array($info) ? ($info['username'] ?? null) : null;
    } catch (\Throwable $e) {
      Log::warning('TelegramOtpService: getMe() failed', ['error' => $e->getMessage()]);
      $username = null;
    }

    if ($username) {
      // Only cache successful lookups so a one-off Telegram outage doesn't
      // pin us to null for 24 hours.
      Cache::put($cacheKey, $username, now()->addDay());
    }

    return $username;
  }

  /**
   * When Telegram bot is not configured, generate an OTP directly and log it.
   * Also creates a stub user if none exists for this phone.
   */
  private function generateAndLogOtp(string $phone): void
  {
    $userPhone = UserPhone::where('phone', $phone)->first();
    if (!$userPhone) {
      $user = User::create([
        'name'     => 'New client',
        'email'    => null,
        'password' => bcrypt(Str::random(32)),
        'status'   => 'active',
      ]);
      $user->assignRole('Client');

      UserPhone::create([
        'user_id'    => $user->id,
        'phone'      => $phone,
        'label'      => 'Primary',
        'is_default' => true,
      ]);
    }

    $code = (string) random_int(100000, 999999);
    OtpCode::updateOrCreate(
      ['identifier' => $phone],
      ['code' => $code, 'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES)],
    );

    Log::info("OTP code for {$phone}: {$code}");
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
