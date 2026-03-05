<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Online()
 * @method static static Offline()
 * @method static static Away()
 * @method static static Busy()
 */
final class UserActivityStatus extends Enum
{
    const Online = 'online';
    const Offline = 'offline';
    const Away = 'away';
    const Busy = 'busy';
}
