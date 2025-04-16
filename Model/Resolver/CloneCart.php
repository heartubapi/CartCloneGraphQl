<?php

declare(strict_types=1);

/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

namespace Heartub\CartCloneGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Heartub\CartCloneGraphQl\Service\Guest\NewCart;

class CloneCart implements ResolverInterface
{
    /**
     * @param NewCart $newCart
     */
    public function __construct(
        private readonly NewCart $newCart
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (empty($args['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }

        $maskedCartId = $args['cart_id'];
        $currentUserId = $context->getUserId();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();

        $maskedQuoteId = $this->newCart->createEmptyCartForGuest();

        try {
            $argsCartId = [
                'cartId' => $maskedCartId
            ];

        } catch (\Exception $exception) {
            throw new GraphQlNoSuchEntityException(
                __('Could not find a cart with ID "%masked_cart_id"', ['masked_cart_id' => ''])
            );
        }

        return '';
    }
}
