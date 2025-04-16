<?php

declare(strict_types=1);

/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

namespace Heartub\CartCloneGraphQl\Service\Guest;

use Magento\QuoteGraphQl\Model\Cart\CreateEmptyCartForGuest;

class NewCart
{

    /**
     * @param CreateEmptyCartForGuest $createEmptyCartForGuest
     */
    public function __construct(
        private readonly CreateEmptyCartForGuest $createEmptyCartForGuest
    ) {
    }

    /**
     * Method creates an empty guest cart
     *
     * @return string|null
     */
    public function createEmptyCartForGuest(): ?string
    {
        $predefinedMaskedQuoteId = null;
        return $this->createEmptyCartForGuest->execute($predefinedMaskedQuoteId);
    }
}
