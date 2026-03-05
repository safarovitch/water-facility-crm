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
  const Delivered    = 'delivered';
  const Cancelled    = 'cancelled';
}
