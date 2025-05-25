<?php

declare(strict_types=1);

/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

namespace Heartub\CartCloneGraphQl\Service\Guest;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Api\CouponManagementInterface;
use Magento\Multicoupon\Api\CouponManagementInterface as MultiCouponManagement;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

class Coupon
{

    /**
     * @param CouponManagementInterface $couponManagement
     * @param MultiCouponManagement $multiCouponManagement
     * @param GetCartForUser $getCartForUser
     * @param Quote|null $currentCart
     * @param array|null $args
     * @param $context
     */
    public function __construct(
        private readonly CouponManagementInterface $couponManagement,
        private readonly MultiCouponManagement $multiCouponManagement,
        private readonly GetCartForUser $getCartForUser,
        private ?Quote $currentCart,
        private ?array $args,
        private $context
    ) {
    }

    /**
     * @return array[]
     * @throws GraphQlNoSuchEntityException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlInputException
     */
    public function applyCoupons(): array
    {
        $appliedCoupons = $this->getAppliedCoupons();
        $maskedCartId = $this->args['input']['cart_id'];
        $currentUserId = $this->context->getUserId();
        $storeId = (int)$this->context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);
        $cartId = $cart->getId();

        foreach ($appliedCoupons ?? [] as $coupon) {
            $couponCode = $coupon['code'] ?? null;
            $appliedCouponCode = $this->couponManagement->get($cartId);
            if (!empty($appliedCouponCode)) {
                continue;
            }

            try {
                $this->multiCouponManagement->append((int) $cart->getId(), $couponCode);
            } catch (NoSuchEntityException $e) {
                $message = $e->getMessage();
                if (preg_match('/The "\d+" Cart doesn\'t contain products/', $message)) {
                    $message = 'Cart does not contain products.';
                }
                throw new GraphQlNoSuchEntityException(__($message), $e);
            } catch (\Exception $e) {
                throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
            }
        }


        return [
            'cart' => [
                'model' => $cart,
            ]
        ];
    }

    /**
     * @return array|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getAppliedCoupons(): ?array
    {
        $cartId = $this->currentCart->getId() ?? null;
        $appliedCoupons = [];
        $appliedCoupon = $this->couponManagement->get($cartId);
        if ($appliedCoupon) {
            $appliedCoupons[] = [ 'code' => $appliedCoupon ];
        }
        return !empty($appliedCoupons) ? $appliedCoupons : null;
    }

    /**
     * @return Quote|null
     */
    public function getCurrentCart(): ?Quote
    {
        return $this->currentCart;
    }

    /**
     * @param Quote|null $currentCart
     * @return Coupon
     */
    public function setCurrentCart(?Quote $currentCart): self
    {
        $this->currentCart = $currentCart;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getArgs(): ?array
    {
        return $this->args;
    }

    /**
     * @param array|null $args
     * @return Coupon
     */
    public function setArgs(?array $args): self
    {
        $this->args = $args;
        return $this;
    }

    /**
     * @return ContextInterface|null
     */
    public function getContext(): ?ContextInterface
    {
        return $this->context;
    }

    /**
     * @param ContextInterface|null $context
     * @return $this
     */
    public function setContext(?ContextInterface $context): self
    {
        $this->context = $context;
        return $this;
    }
}
