<?php

namespace Safarovitch\TelegramBot\Handlers;

use Safarovitch\TelegramBot\Models\TelegraphChat;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

class TrackingHandler
{
    public function __construct(
        protected TelegraphChat $chat,
        protected TelegraphBot $bot
    ) {}

    public function handleListOrders(): void
    {
        $orders = \App\Models\Order::where('user_id', $this->chat->user_id)
            ->latest()
            ->limit(5)
            ->get();

        if ($orders->isEmpty()) {
            $this->chat->message("У вас пока нет заказов.")->send();
            return;
        }

        $text = "Ваши последние заказы:\n\n";
        foreach ($orders as $order) {
            $statusStr = $order->status->name ?? $order->status ?? 'Неизвестно';
            $text .= "📦 Заказ #{$order->order_number}\nДата: {$order->created_at->format('d.m.Y H:i')}\nСумма: {$order->total_amount} TJS\nСтатус: {$statusStr}\n\n";
        }

        $this->chat->message($text)->send();
    }
}
