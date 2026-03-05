<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static OptionOne()
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class ProductLowStockAction extends Enum
{
    const None = 'none';
    const NotifyAndAllowPurchases = 'notify_and_allow_purchases';
    const NotifyAndDisable = 'notify_and_disable_purchases';
    const DisablePurchases = 'disable_purchases';
    const RemovePurchases = 'remove_purchases';
    const NotifyAdminAndRemovePurchases = 'notify_admin_and_remove_purchases';
}
