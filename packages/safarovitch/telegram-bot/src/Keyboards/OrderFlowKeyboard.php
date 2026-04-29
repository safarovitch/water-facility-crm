<?php

namespace Safarovitch\TelegramBot\Keyboards;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

class OrderFlowKeyboard
{
    public static function products($products): Keyboard
    {
        $buttons = [];
        foreach ($products as $product) {
            // name is a JSON-cast array — extract ru locale or fallback to first value
            $name = $product->name;
            if (is_array($name)) {
                $name = $name['ru'] ?? $name['en'] ?? array_values($name)[0] ?? 'Product';
            }
            $buttons[] = Button::make("{$name} - {$product->price} TJS")
                ->action('actionSelectProduct')->param('id', (string) $product->id);
        }
        return Keyboard::make()->row($buttons);
    }

    public static function confirm(): Keyboard
    {
        return Keyboard::make()->buttons([
            Button::make('✅ Подтвердить')->action('actionConfirmOrder'),
            Button::make('❌ Отменить')->action('actionCancelOrder'),
        ]);
    }
}
