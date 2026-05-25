<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DefStudio\Telegraph\Models\TelegraphBot;

class SetupTelegramBot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:setup-telegram-bot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Registers the Telegram Bot using the .env token and binds the webhook to the current APP_URL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Forge caches config, so env() will return null. We pull from config facade instead.
        $token = config('telegraph.bot_token');
        
        // Fallback: Manually parse .env if cache is drifting or config:cache was missed
        if (empty($token) && file_exists(base_path('.env'))) {
            $envString = file_get_contents(base_path('.env'));
            preg_match('/^TELEGRAM_ORDER_BOT_TOKEN=(.*)$/m', $envString, $matches);
            if (!empty($matches[1])) {
                $token = trim($matches[1], "\"' \t\n\r\0\x0B");
            }
        }
        
        if (empty($token)) {
            $this->error('TELEGRAM_ORDER_BOT_TOKEN is missing in .env!');
            return 1;
        }

        $bot = TelegraphBot::updateOrCreate(
            ['name' => 'Water Order Bot'],
            ['token' => $token]
        );

        // Ensure app.url does not end with a trailing slash
        $baseUrl = rtrim(config('app.url'), '/');
        $webhookUrl = $baseUrl . '/telegraph/' . $bot->token . '/webhook';
        
        $this->info("Registering webhook out to: {$webhookUrl}");
        
        try {
            $response = $bot->registerWebhook($webhookUrl)->send();

            if ($response->telegraphOk()) {
                $this->info('✅ Telegram Bot Webhook successfully registered and bound to Laravel Forge environment!');

                try {
                    $info = $bot->info();
                    $username = is_array($info) ? ($info['username'] ?? null) : null;
                    if ($username) {
                        $this->info("Bot username: @{$username}");
                        $this->comment('Set TELEGRAM_ORDER_BOT_USERNAME=' . $username . ' in production .env');
                    }
                } catch (\Exception $e) {
                    $this->warn('Could not fetch bot username: ' . $e->getMessage());
                }
            } else {
                $this->error('❌ Failed to register Webhook. Telegram response: ' . $response->body());
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('❌ Critical Error: ' . $e->getMessage());
            $this->error('Are you sure you have deployed this to Forge over HTTPS? Telegram requires a valid SSL certificate for webhooks.');
            return 1;
        }

        return 0;
    }
}
