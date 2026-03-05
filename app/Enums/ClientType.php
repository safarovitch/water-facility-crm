<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Individual()
 * @method static static Company()
 */
final class ClientType extends Enum
{
    const Individual = 'individual';
    const Company    = 'company';
}
