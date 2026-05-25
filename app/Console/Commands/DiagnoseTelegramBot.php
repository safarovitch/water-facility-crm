<?php

namespace App\Console\Commands;

use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DiagnoseTelegramBot extends Command
{
    protected $signature = 'app:diagnose-telegram-bot';

    protected $description = 'Check Telegram OTP bot config, database row, username, cache, and webhook registration';

    public function handle(): int
    {
        $token = config('telegraph.bot_token');
        if (empty($token)) {
            $this->error('TELEGRAM_ORDER_BOT_TOKEN is not set (or missing from config:cache).');
            return 1;
        }
        $this->info('Bot token: configured (' . strlen($token) . ' chars)');

        $bot = TelegraphBot::where('token', $token)->first();
        if (!$bot) {
            $this->warn('No telegraph_bots row for this token. Run: php artisan app:setup-telegram-bot');
        } else {
            $this->info("telegraph_bots row: id={$bot->id}, name={$bot->name}");
        }

        $username = config('telegraph.bot_username');
        if ($username) {
            $this->info("Bot username (env): @{$username}");
        } elseif ($bot) {
            try {
                $info = $bot->info();
                $username = is_array($info) ? ($info['username'] ?? null) : null;
                $this->info($username ? "Bot username (getMe): @{$username}" : 'Bot username: getMe() returned empty');
            } catch (\Throwable $e) {
                $this->error('getMe() failed: ' . $e->getMessage());
            }
        }

        $cacheDriver = config('cache.default');
        $this->info("Cache driver: {$cacheDriver}");
        if ($cacheDriver === 'array') {
            $this->error('CACHE_STORE=array breaks OTP login tokens (web and webhook use different requests). Use redis or database.');
            return 1;
        }

        $testKey = 'phone_otp:login_token:diagnose_' . Str::random(8);
        Cache::put($testKey, '+10000000000', 60);
        $pulled = Cache::pull($testKey);
        if ($pulled !== '+10000000000') {
            $this->error('Cache read/write test failed — OTP deep links will expire immediately for the bot.');
            return 1;
        }
        $this->info('Cache read/write: OK');

        if ($bot) {
            try {
                $response = $bot->getWebhookDebugInfo()->send();
                if ($response->telegraphOk()) {
                    $url = $response->json('result.url') ?? '(none)';
                    $this->info("Webhook URL: {$url}");
                    $expected = rtrim(config('app.url'), '/') . '/telegraph/' . $bot->token . '/webhook';
                    if ($url !== $expected) {
                        $this->warn("Expected: {$expected}");
                        $this->warn('Run: php artisan app:setup-telegram-bot');
                    }
                } else {
                    $this->error('getWebhookDebugInfo failed: ' . $response->body());
                }
            } catch (\Throwable $e) {
                $this->error('getWebhookDebugInfo error: ' . $e->getMessage());
            }
        }

        $this->newLine();
        $this->comment('Production checklist:');
        $this->line('  1. APP_URL must be your public HTTPS URL');
        $this->line('  2. php artisan app:setup-telegram-bot (after deploy)');
        $this->line('  3. TELEGRAM_ORDER_BOT_USERNAME=@YourBot (optional but recommended)');
        $this->line('  4. php artisan config:clear && php artisan config:cache (after env changes)');

        return 0;
    }
}
