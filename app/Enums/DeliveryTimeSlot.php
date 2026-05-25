<?php

namespace App\Enums;

enum DeliveryTimeSlot: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Evening = 'evening';

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Morning (8:00–12:00)',
            self::Afternoon => 'Afternoon (12:00–17:00)',
            self::Evening => 'Evening (17:00–21:00)',
        };
    }

    public function hour(): int
    {
        return match ($this) {
            self::Morning => 9,
            self::Afternoon => 14,
            self::Evening => 18,
        };
    }
}
