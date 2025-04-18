<?php

declare(strict_types=1);

/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

namespace Heartub\CartCloneGraphQl\Service\Guest;

use Magento\Framework\App\ObjectManager;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\CartItem\PrecursorInterface;
use Heartub\CartCloneGraphQl\Model\Provider\CartItems;

class AddProducts
{

    /**
     * @param CartItems $cartItems
     * @param PrecursorInterface|null $cartItemPrecursor
     * @param Quote|null $currentCart
     * @param $context
     * @param array|null $args
     * @param float|null $qty
     * @param array|null $itemData
     */
    public function __construct(
        private readonly CartItems $cartItems,
        private ?PrecursorInterface $cartItemPrecursor,
        private ?Quote $currentCart,
        private $context,
        private ?array $args,
        private ?float $qty,
        private ?array $itemData
    ) {
        $this->cartItemPrecursor = $cartItemPrecursor ?: ObjectManager::getInstance()->get(PrecursorInterface::class);
    }
}
