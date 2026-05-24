<?php

namespace App\Enums;

enum SubscriptionFrequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Custom = 'custom';

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Every week',
            self::Biweekly => 'Every 2 weeks',
            self::Monthly => 'Every month',
            self::Custom => 'Custom interval',
        };
    }
}
