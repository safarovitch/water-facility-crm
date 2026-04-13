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
            $buttons[] = Button::make("{$product->name} - {$product->price} TJS")
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
