<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Pending()
 * @method static static Confirmed()
 * @method static static InProduction()
 * @method static static Ready()
 * @method static static Delivered()
 * @method static static Cancelled()
 */
final class OrderStatus extends Enum
{
  const Pending      = 'pending';
  const Confirmed    = 'confirmed';
  const InProduction = 'in_production';
  const Ready        = 'ready';
  const Accepted     = 'accepted';
  const InTransit    = 'in_transit';
  const Delivered    = 'delivered';
  const Cancelled    = 'cancelled';

  /**
   * Russian label for a status value, used by the Telegram bot (which is
   * Russian-only). Falls back to the raw value for unknown statuses.
   */
  public static function label(string $value): string
  {
    return [
      self::Pending      => 'Ожидает подтверждения',
      self::Confirmed    => 'Подтверждён',
      self::InProduction => 'Готовится',
      self::Ready        => 'Готов к доставке',
      self::Accepted     => 'Принят курьером',
      self::InTransit    => 'В пути',
      self::Delivered    => 'Доставлен',
      self::Cancelled    => 'Отменён',
    ][$value] ?? $value;
  }
}
