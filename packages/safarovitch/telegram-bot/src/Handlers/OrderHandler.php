<?php

namespace Safarovitch\TelegramBot\Handlers;

use Safarovitch\TelegramBot\Models\TelegraphChat;
use DefStudio\Telegraph\Models\TelegraphBot;
use Safarovitch\TelegramBot\Keyboards\OrderFlowKeyboard;
use Safarovitch\TelegramBot\Keyboards\PostOrderKeyboard;
use Illuminate\Support\Stringable;

class OrderHandler
{
    public function __construct(
        protected TelegraphChat $chat,
        protected TelegraphBot $bot
    ) {}

    public function handleNewOrder(): void
    {
        $products = \App\Models\Product::where('status', 'active')->get();
        if ($products->isEmpty()) {
            $this->chat->message('Нет доступных товаров для заказа.')->send();
            return;
        }

        $this->chat->message("Выберите товар для заказа:")
            ->keyboard(OrderFlowKeyboard::products($products))->send();
    }

    public function handleSelectProduct(int $productId): void
    {
        // Offer quick-pick quantities; "Другое количество" falls back to typing.
        $this->chat->message("Сколько бутылей? Выберите или введите своё количество:")
            ->keyboard(OrderFlowKeyboard::quantities($productId))->send();
    }

    /**
     * A quantity was picked via a quick button — jump straight to the address.
     */
    public function applyQuantity(int $productId, int $qty): void
    {
        if ($qty <= 0) {
            $this->chat->message("Пожалуйста, введите корректное число.")->send();
            return;
        }

        $this->chat->updateState('order_waiting_address', ['product_id' => $productId, 'quantity' => $qty]);
        $this->chat->message("Количество: {$qty}. Теперь отправьте ваш адрес доставки (улица, дом, кв) текстом:")->send();
    }

    /**
     * User chose "Другое количество" — switch to the typed-input path.
     */
    public function promptCustomQuantity(int $productId): void
    {
        $this->chat->updateState('order_waiting_quantity', ['product_id' => $productId]);
        $this->chat->message("Введите количество бутылей (например: 4):")->send();
    }

    public function processQuantityInput(Stringable $text, array $data): void
    {
        $qty = (int) $text->toString();
        if ($qty <= 0) {
            $this->chat->message("Пожалуйста, введите корректное число.")->send();
            return;
        }

        $this->applyQuantity($data['product_id'], $qty);
    }

    public function processAddressInput(Stringable $text, array $data): void
    {
        $address = $text->toString();
        
        $product = \App\Models\Product::find($data['product_id']);
        $total = $product->price * $data['quantity'];

        // Resolve translatable name
        $productName = $product->name;
        if (is_array($productName)) {
            $productName = $productName['ru'] ?? $productName['en'] ?? array_values($productName)[0] ?? 'Product';
        }

        $summary = "📝 Проверьте ваш заказ:\n" .
                   "Товар: {$productName}\n" .
                   "Кол-во: {$data['quantity']}\n" .
                   "Сумма: {$total} TJS\n" .
                   "Адрес: {$address}\n\nПодтверждаете?";

        $this->chat->updateState('order_confirming', [
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'address' => $address,
            'total' => $total,
        ]);

        $this->chat->message($summary)->keyboard(OrderFlowKeyboard::confirm())->send();
    }

    public function confirmOrder(array $data): void
    {
        try {
            $order = \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
                $order = \App\Models\Order::create([
                    'user_id'               => $this->chat->user_id,
                    'delivery_address'      => $data['address'],
                    'total_amount'          => $data['total'],
                    'created_by'            => $this->chat->user_id,
                    'status'                => 'pending',
                    'scheduled_delivery_at' => now()->addHours(2),
                ]);

                $order->items()->create([
                    'product_id' => $data['product_id'],
                    'quantity'   => $data['quantity'],
                    'unit_price' => $data['total'] / $data['quantity'],
                    'subtotal'   => $data['total'],
                ]);

                return $order;
            });

            $this->chat->clearState();
            $this->chat->message("✅ Заказ #{$order->order_number} создан! Ожидайте доставку.")
                ->keyboard(PostOrderKeyboard::make($order->id))->send();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Telegram bot order creation failed', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);
            $this->chat->message("❌ Не удалось создать заказ, попробуйте позже.")->send();
        }
    }

    public function cancelOrder(): void
    {
        $this->chat->clearState();
        $this->chat->message("Заказ отменен.")->send();
    }

    public function handleRepeatOrder(): void
    {
        $order = \App\Models\Order::where('user_id', $this->chat->user_id)->latest()->first();

        if (!$order) {
            $this->chat->message("У вас еще нет завершенных заказов для повторения.")->send();
            return;
        }

        $this->chat->updateState('order_confirming', [
            'product_id' => $order->items->first()->product_id,
            'quantity' => $order->items->first()->quantity,
            'address' => $order->delivery_address,
            'total' => $order->total_amount,
        ]);

        $summary = "🔄 Повторить последний заказ?\n\n" .
                   "Сумма: {$order->total_amount} TJS\n" .
                   "Адрес: {$order->delivery_address}\n\nПодтверждаете?";

        $this->chat->message($summary)->keyboard(OrderFlowKeyboard::confirm())->send();
    }
}
