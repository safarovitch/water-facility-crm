<?php

namespace Safarovitch\TelegramBot\Handlers;

use App\Enums\OrderStatus;
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

        $buttons = [];
        $text = "Ваши последние заказы:\n\n";
        foreach ($orders as $order) {
            $text .= "📦 Заказ #{$order->order_number}\n"
                . "Дата: {$order->created_at->format('d.m.Y H:i')}\n"
                . "Сумма: {$order->total_amount} TJS\n"
                . "Статус: " . OrderStatus::label($order->status->value) . "\n\n";

            $buttons[] = Button::make("📦 #{$order->order_number}")
                ->action('actionOrderDetail')
                ->param('id', (string) $order->id);
        }

        $this->chat->message($text)
            ->keyboard(Keyboard::make()->buttons($buttons))
            ->send();
    }

    public function handleOrderDetail(int $orderId): void
    {
        $order = \App\Models\Order::where('user_id', $this->chat->user_id)
            ->with('items.product')
            ->find($orderId);

        if (!$order) {
            $this->chat->message("Заказ не найден.")->send();
            return;
        }

        $lines = "📦 Заказ #{$order->order_number}\n"
            . "Статус: " . OrderStatus::label($order->status->value) . "\n"
            . "Дата: {$order->created_at->format('d.m.Y H:i')}\n";

        if ($order->scheduled_delivery_at) {
            $lines .= "Доставка: {$order->scheduled_delivery_at->format('d.m.Y H:i')}\n";
        }
        if ($order->delivery_address) {
            $lines .= "Адрес: {$order->delivery_address}\n";
        }

        $lines .= "\nТовары:\n";
        foreach ($order->items as $item) {
            $lines .= "• " . $this->productName($item->product) . " × {$item->quantity}\n";
        }
        $lines .= "\nИтого: {$order->total_amount} TJS";

        $this->chat->message($lines)
            ->keyboard(Keyboard::make()->buttons([
                Button::make('🔄 Повторить')->action('actionRepeatOrder'),
                Button::make('🏠 Главное меню')->action('actionMainMenu'),
            ]))
            ->send();
    }

    protected function productName($product): string
    {
        if (!$product) {
            return 'Товар';
        }
        $name = $product->name;
        if (is_array($name)) {
            return $name['ru'] ?? $name['en'] ?? array_values($name)[0] ?? 'Товар';
        }
        return (string) $name;
    }
}
