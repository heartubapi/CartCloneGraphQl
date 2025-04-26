<?php

declare(strict_types=1);

/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

namespace Heartub\CartCloneGraphQl\Enum;

enum ProductTypeEnum: string
{
    case SIMPLE = 'simple';
    case SIMPLE_LABEL = 'Simple Product';
    case VIRTUAL = 'virtual';
    case VIRTUAL_LABEL = 'Virtual Product';
    case BUNDLE = 'bundle';
    case BUNDLE_LABEL = 'Bundle Product';
    case CONFIGURABLE = 'configurable';
    case CONFIGURABLE_LABEL = 'Configurable Product';
    case DOWNLOADABLE = 'downloadable';
    case DOWNLOADABLE_LABEL = 'Downloadable Product';
    case GIFTCARD = 'giftcard';
    case GIFTCARD_LABEL = 'Gift Card';
    case GROUPED = 'grouped';
    case GROUPED_LABEL = 'Grouped Product';
}
