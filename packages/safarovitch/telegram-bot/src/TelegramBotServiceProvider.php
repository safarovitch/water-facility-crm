<?php

namespace Safarovitch\TelegramBot;

use Illuminate\Support\ServiceProvider;
use App\Enums\OrderStatus;
use App\Models\Order;
use Safarovitch\TelegramBot\Models\TelegraphChat;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

class TelegramBotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Order::updated(function (Order $order) {
            if ($order->wasChanged('status')) {
                // Find associated native chat model locally
                $chat = TelegraphChat::where('user_id', $order->user_id)->first();
                if ($chat) {
                    $status = OrderStatus::label($order->status->value);
                    $chat->message("🔔 Статус заказа #{$order->order_number} обновлён!\nНовый статус: {$status}")
                         ->keyboard(Keyboard::make()->buttons([
                             Button::make('📦 Подробнее')->action('actionOrderDetail')->param('id', (string) $order->id)
                         ]))
                         ->send();
                }
            }
        });
    }
}
