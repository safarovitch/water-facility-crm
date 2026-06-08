<?php

namespace Safarovitch\TelegramBot\Keyboards;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

class PostOrderKeyboard
{
    public static function make(int $orderId): Keyboard
    {
        return Keyboard::make()
            ->row([
                Button::make('📦 Статус заказа')
                    ->action('actionOrderDetail')
                    ->param('id', (string) $orderId),
            ])
            ->row([
                Button::make('🆕 Новый заказ')->action('actionNewOrder'),
                Button::make('🏠 Главное меню')->action('actionMainMenu'),
            ]);
    }
}
