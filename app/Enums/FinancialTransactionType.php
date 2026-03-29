<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Income()
 * @method static static Expense()
 */
final class FinancialTransactionType extends Enum
{
    const Income  = 'income';
    const Expense = 'expense';
}
