<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Active()
 * @method static static Inactive()
 * @method static static Pending()
 * @method static static Blocked()
 */
final class UserStatus extends Enum
{
    const Active = 'active';
    const Inactive = 'inactive';
    const Pending = 'pending';
    const Blocked = 'blocked';
}
