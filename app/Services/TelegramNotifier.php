<?php

namespace App\Services;

use App\Models\Order;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Log;

/**
 * Sends operational notifications to the configured Telegram group
 * (TELEGRAM_GROUP_ID). Used for new-order and order-delivered alerts so
 * staff don't need to refresh the CRM.
 *
 * The group is registered as a TelegraphChat row on first use so the rest
 * of the Telegraph fluent API just works. Make sure the bot has been
 * added to the group beforehand.
 */
class TelegramNotifier
{
    public function notifyOrderCreated(Order $order): void
    {
        $order->loadMissing(['client', 'items.product']);

        $client = $order->contact_name ?: ($order->client?->name ?? 'Walk-in client');
        $items = $order->items
            ->map(fn ($i) => "• {$i->quantity}× " . $this->productName($i->product?->name))
            ->implode("\n");

        $scheduled = $order->scheduled_delivery_at
            ? $order->scheduled_delivery_at->format('M j, Y H:i')
            : 'Not scheduled';

        $message = "🆕 <b>New order #{$order->order_number}</b>\n"
            . "Client: {$client}\n"
            . "Address: " . ($order->delivery_address ?: '—') . "\n"
            . "Scheduled: {$scheduled}\n"
            . "Total: " . number_format((float) $order->total_amount, 2) . "\n\n"
            . "<b>Items</b>\n{$items}";

        $this->send($message, $this->orderButton($order, '🔍 Open order'));
    }

    public function notifyOrderDelivered(Order $order): void
    {
        $order->loadMissing(['client', 'courier']);

        $client = $order->contact_name ?: ($order->client?->name ?? 'Client');
        $courier = $order->courier?->name ?? 'Unknown';
        $when = $order->actual_delivery_at?->format('M j, Y H:i') ?? now()->format('M j, Y H:i');
        $deposit = (float) $order->deposit_charge;

        $message = "✅ <b>Order #{$order->order_number} delivered</b>\n"
            . "Client: {$client}\n"
            . "Courier: {$courier}\n"
            . "Delivered: {$when}\n"
            . "Total: " . number_format((float) $order->total_amount + $deposit, 2);

        if ($deposit > 0) {
            $message .= "\n💰 Includes bottle deposit: " . number_format($deposit, 2);
        }

        $this->send($message, $this->orderButton($order, '🔍 Open order'));
    }

    private function send(string $message, ?Keyboard $keyboard = null): void
    {
        $chatId = config('telegraph.notifications_chat_id');
        if (!$chatId) {
            return;
        }

        $bot = $this->getBot();
        if (!$bot) {
            Log::warning('TelegramNotifier: no TelegraphBot row found — run `php artisan app:setup-telegram-bot`.');
            return;
        }

        try {
            $chat = TelegraphChat::firstOrCreate(
                [
                    'chat_id' => (string) $chatId,
                    'telegraph_bot_id' => $bot->id,
                ],
                [
                    'name' => 'Operations notifications',
                ],
            );

            $builder = $chat->message($message);
            if ($keyboard) {
                $builder->keyboard($keyboard);
            }
            $builder->send();
        } catch (\Throwable $e) {
            Log::warning('TelegramNotifier: failed to send group message', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function orderButton(Order $order, string $label): Keyboard
    {
        $url = rtrim(config('app.url'), '/') . "/admin/orders/{$order->id}";
        return Keyboard::make()->buttons([
            Button::make($label)->url($url),
        ]);
    }

    private function productName(mixed $name): string
    {
        if (is_string($name)) return $name;
        if (is_array($name)) {
            return $name['en'] ?? $name['ru'] ?? $name['uz'] ?? reset($name) ?: '—';
        }
        return '—';
    }

    private function getBot(): ?TelegraphBot
    {
        $token = config('telegraph.bot_token');
        if ($token) {
            $bot = TelegraphBot::where('token', $token)->first();
            if ($bot) {
                return $bot;
            }
        }
        return TelegraphBot::query()->first();
    }
}
