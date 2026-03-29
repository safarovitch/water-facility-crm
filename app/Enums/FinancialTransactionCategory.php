<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Wages()
 * @method static static Rent()
 * @method static static PartnerPayoff()
 * @method static static Sales()
 * @method static static Maintenance()
 * @method static static Utilities()
 * @method static static Other()
 */
final class FinancialTransactionCategory extends Enum
{
    const Wages         = 'wages';
    const Rent          = 'rent';
    const PartnerPayoff = 'partner_payoff';
    const Sales         = 'sales';
    const Maintenance   = 'maintenance';
    const Utilities     = 'utilities';
    const Other         = 'other';
}
