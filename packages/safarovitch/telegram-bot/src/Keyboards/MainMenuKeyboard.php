<?php

namespace Safarovitch\TelegramBot\Keyboards;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

class MainMenuKeyboard
{
    public static function make(): Keyboard
    {
        return Keyboard::make()->buttons([
            Button::make('🆕 Новый заказ')->action('actionNewOrder'),
            Button::make('🔄 Повторить заказ')->action('actionRepeatOrder'),
            Button::make('📦 Мои заказы / Статус')->action('actionMyOrders'),
            Button::make('📅 Предзаказ')->action('actionNewOrder')->param('preorder', '1'),
            Button::make('👤 Профиль / Адреса')->action('actionProfile'),
        ]);
    }
}
