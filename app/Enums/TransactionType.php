<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Deposit()
 * @method static static Withdrawal()
 * @method static static Payment()
 * @method static static Refund()
 */
final class TransactionType extends Enum
{
    const Deposit    = 'deposit';
    const Withdrawal = 'withdrawal';
    const Payment    = 'payment';
    const Refund     = 'refund';
}
