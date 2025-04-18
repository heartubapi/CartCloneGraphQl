<?php

declare(strict_types=1);

/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

namespace Heartub\CartCloneGraphQl\Enum;

enum CartItemsPaginatedEnum: string
{
    case SORT_ORDER_BY = 'item_id';
    case SORT_ORDER = 'ASC';
}
