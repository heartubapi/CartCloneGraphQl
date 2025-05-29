<?php

declare(strict_types=1);

/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

namespace Heartub\CartCloneGraphQl\Service\Guest;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Quote\Model\Quote;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Framework\Validator\EmailAddress as EmailAddressValidator;
use Magento\QuoteGraphQl\Model\Cart\CheckCartCheckoutAllowance;
use Magento\Quote\Api\CartRepositoryInterface;

class Email
{

    /**
     * @param GetCartForUser $getCartForUser
     * @param EmailAddressValidator $emailValidator
     * @param CheckCartCheckoutAllowance $checkCartCheckoutAllowance
     * @param CartRepositoryInterface $cartRepository
     * @param Quote|null $currentCart
     * @param array|null $args
     * @param ContextInterface|null $context
     */
    public function __construct(
        private readonly GetCartForUser $getCartForUser,
        private readonly EmailAddressValidator $emailValidator,
        private readonly CheckCartCheckoutAllowance $checkCartCheckoutAllowance,
        private readonly CartRepositoryInterface $cartRepository,
        private ?Quote $currentCart,
        private ?array $args,
        private ?ContextInterface $context
    ) {
    }

    /**
     * Set the email address from the current cart to the cloned cart
     *
     * @return array[]
     * @throws GraphQlInputException
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException
     */
    public function setEmailToCart(): array
    {
        $maskedCartId = $this->args['input']['cart_id'];
        $currentUserId = $this->context->getUserId();
        $storeId = (int)$this->context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);
        $currentEmail = $this->getCartEmail();
        if (empty($currentEmail)) {
            return [
                'cart' => [
                    'model' => $cart,
                ],
            ];
        }

        if (false === $this->emailValidator->isValid($currentEmail)) {
            throw new GraphQlInputException(__('Invalid email format'));
        }
        if ($currentUserId !== 0) {
            throw new GraphQlInputException(__('The request is not allowed for logged in customers'));
        }
        $this->checkCartCheckoutAllowance->execute($cart);
        $cart->setCustomerEmail($currentEmail);
        try {
            $this->cartRepository->save($cart);
        } catch (CouldNotSaveException $e) {
            throw new LocalizedException(__($e->getMessage()), $e);
        }

        return [
            'cart' => [
                'model' => $cart,
            ],
        ];
    }

    /**
     * Retrieve the email address from the current cart
     *
     * @return string|null
     */
    private function getCartEmail(): ?string
    {
        return $this->currentCart->getCustomerEmail() ?? null;
    }

    /**
     * Retrieve the current cart
     *
     * @return Quote|null
     */
    public function getCurrentCart(): ?Quote
    {
        return $this->currentCart;
    }

    /**
     * Set the current cart
     *
     * @param Quote|null $currentCart
     * @return Email
     */
    public function setCurrentCart(?Quote $currentCart): self
    {
        $this->currentCart = $currentCart;
        return $this;
    }

    /**
     * Retrieve argument data from the resolver
     *
     * @return array|null
     */
    public function getArgs(): ?array
    {
        return $this->args;
    }

    /**
     * Set argument data coming in the resolver
     *
     * @param array|null $args
     * @return Email
     */
    public function setArgs(?array $args): self
    {
        $this->args = $args;
        return $this;
    }

    /**
     * Retrieve values from the resolver context
     *
     * @return ContextInterface|null
     */
    public function getContext(): ?ContextInterface
    {
        return $this->context;
    }

    /**
     * Set values from the resolver context
     *
     * @param ContextInterface|null $context
     * @return $this
     */
    public function setContext(?ContextInterface $context): self
    {
        $this->context = $context;
        return $this;
    }
}
