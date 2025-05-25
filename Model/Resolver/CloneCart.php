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
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Heartub\CartCloneGraphQl\Service\Guest\NewCart;
use Heartub\CartCloneGraphQl\Service\Guest\AddProducts;
use Heartub\CartCloneGraphQl\Service\Guest\ShippingAddress;
use Heartub\CartCloneGraphQl\Service\Guest\BillingAddress;
use Heartub\CartCloneGraphQl\Service\Guest\ShippingMethods;

class CloneCart implements ResolverInterface
{
    /**
     * @param NewCart $newCart
     * @param AddProducts $addProducts
     * @param ShippingAddress $setShippingAddress
     * @param BillingAddress $setBillingAddress
     * @param ShippingMethods $setShippingMethods
     * @param GetCartForUser $getCartForUser
     */
    public function __construct(
        private readonly NewCart $newCart,
        private readonly AddProducts $addProducts,
        private readonly ShippingAddress $setShippingAddress,
        private readonly BillingAddress $setBillingAddress,
        private readonly ShippingMethods $setShippingMethods,
        private readonly GetCartForUser $getCartForUser
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
        $currentCart = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);
        $maskedQuoteIdFromClone = $this->newCart->createEmptyCartForGuest();

        try {
            $argsCartId = [
                'cartId' => $maskedQuoteIdFromClone
            ];
            $this->addProducts->setContext($context)->setArgs($argsCartId)
                ->setCurrentCart($currentCart)
                ->setProductsOnNewCart();
            $shippingCartId = [
                'input' => [
                    'cart_id' => $maskedQuoteIdFromClone
                ]
            ];
            $this->setShippingAddress->setContext($context)->setArgs($shippingCartId)->setCurrentCart($currentCart)
                ->buildByShippingAddress()
                ->setShippingAddress();
            $this->setBillingAddress->setContext($context)->setArgs($shippingCartId)->setCurrentCart($currentCart)
                ->buildByBillingAddress()
                ->setBillingAddress();
            $this->setShippingMethods->setContext($context)->setArgs($shippingCartId)->setCurrentCart($currentCart)
                ->buildByShippingAddress()
                ->setMethodsOnCart();

        } catch (\Exception $exception) {
            throw new GraphQlNoSuchEntityException(
                __('Could not find a cart with ID "%masked_cart_id"', ['masked_cart_id' => ''])
            );
        }

        return $maskedQuoteIdFromClone;
    }
}
