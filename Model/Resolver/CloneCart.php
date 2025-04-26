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

class CloneCart implements ResolverInterface
{
    /**
     * @param NewCart $newCart
     * @param AddProducts $addProducts
     * @param GetCartForUser $getCartForUser
     */
    public function __construct(
        private readonly NewCart $newCart,
        private readonly AddProducts $addProducts,
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

        } catch (\Exception $exception) {
            throw new GraphQlNoSuchEntityException(
                __('Could not find a cart with ID "%masked_cart_id"', ['masked_cart_id' => ''])
            );
        }

        return $maskedQuoteIdFromClone;
    }
}
