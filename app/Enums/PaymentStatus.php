<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Unpaid()
 * @method static static Partial()
 * @method static static Paid()
 */
final class PaymentStatus extends Enum
{
  const Unpaid  = 'unpaid';
  const Partial = 'partial';
  const Paid    = 'paid';
}
