<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Pending()
 * @method static static Completed()
 * @method static static Failed()
 */
final class TransactionStatus extends Enum
{
    const Pending   = 'pending';
    const Completed = 'completed';
    const Failed    = 'failed';
}
