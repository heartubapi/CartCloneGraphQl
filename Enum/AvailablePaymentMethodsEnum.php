<?php

declare(strict_types=1);

/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

namespace Heartub\CartCloneGraphQl\Enum;

enum AvailablePaymentMethodsEnum: string
{
    case FREE_SHIPPING_METHOD = 'freeshipping';
    case FREE_PAYMENT_METHOD_CODE = 'Simple free';
}
