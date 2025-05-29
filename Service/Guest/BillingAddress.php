<?php

declare(strict_types=1);

/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

namespace Heartub\CartCloneGraphQl\Service\Guest;

use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\Cart\ExtractQuoteAddressData;
use Magento\QuoteGraphQl\Model\Cart\ValidateAddressFromSchema;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\QuoteGraphQl\Model\Cart\CheckCartCheckoutAllowance;
use Magento\QuoteGraphQl\Model\Cart\SetBillingAddressOnCart as SetBillingAddressOnCartModel;

class BillingAddress
{
    /**
     * @param ExtractQuoteAddressData $extractQuoteAddressData
     * @param ValidateAddressFromSchema $validateAddressFromSchema
     * @param GetCartForUser $getCartForUser
     * @param CheckCartCheckoutAllowance $checkCartCheckoutAllowance
     * @param SetBillingAddressOnCartModel $setBillingAddressOnCart
     * @param Quote|null $currentCart
     * @param array|null $args
     * @param ContextInterface|null $context
     */
    public function __construct(
        private readonly ExtractQuoteAddressData $extractQuoteAddressData,
        private readonly ValidateAddressFromSchema $validateAddressFromSchema,
        private readonly GetCartForUser $getCartForUser,
        private readonly CheckCartCheckoutAllowance $checkCartCheckoutAllowance,
        private readonly SetBillingAddressOnCartModel $setBillingAddressOnCart,
        private ?Quote $currentCart,
        private ?array $args,
        private ?ContextInterface $context
    ) {
    }

    /**
     * Construct the billing address from the current cart
     *
     * @return $this
     */
    public function buildByBillingAddress(): self
    {
        $cartBillingAddressData = $this->checkBillingAddresses();
        if (empty($cartBillingAddressData)) {
            $this->args['input']['billing_address'] = [];
            return $this;
        }

        $cartBillingAddress = $this->currentCart->getBillingAddress() ?? [];
        if (empty($cartBillingAddress->getData('entity_id'))) {
            $this->args['input']['billing_address'] = [];
            return $this;
        }

        $this->args['input']['billing_address'] = [
            'address' => [
                'city' => $cartBillingAddressData['city'] ?? '',
                'company' => $cartBillingAddressData['company'] ?? '',
                'country_code' => $cartBillingAddressData['country_id'] ?? '',
                "custom_attributes" => [
                ],
                "fax" => $cartBillingAddressData['fax'] ?? '',
                'firstname' => $cartBillingAddressData['firstname'] ?? '',
                'lastname' => $cartBillingAddressData['lastname'] ?? '',
                'middlename' => $cartBillingAddressData['middlename'] ?? '',
                'postcode' => $cartBillingAddressData['postcode'] ?? '',
                'prefix' => $cartBillingAddressData['prefix'] ?? '',
                'region' => $cartBillingAddressData['region_code'] ?? '',
                'region_id' => (int)$cartBillingAddressData['region_id'] ?? 0,
                'save_in_address_book' => (bool)$cartBillingAddressData['save_in_address_book'] ?? false,
                'street' => $cartBillingAddressData['street'] ?? [],
                'telephone' => $cartBillingAddressData['telephone'] ?? '',
                'vat_id' => $cartBillingAddressData['vat_id'] ?? ''
            ],
            'customer_address_id' => $cartBillingAddressData['model']?->getData('customer_address_id') ?? null,
            'same_as_shipping' => $this->getSameAsShipping($cartBillingAddressData),
            'use_for_shipping' => false
        ];

        return $this;
    }

    /**
     * Retrieve the "same as shipping" flag from the current cart
     *
     * @param array|null $cartBillingAddressData
     * @return bool
     */
    private function getSameAsShipping(?array $cartBillingAddressData): bool
    {
        $sameAsShipping = false;
        $model = $cartBillingAddressData['model'] ?? [];
        if ($model->getId()) {
            $sameAsBilling = (bool)$model->getData('same_as_billing') ?? false;
            $sameAsShipping = !$sameAsBilling;
        }

        return $sameAsShipping;
    }

    /**
     * Verify the validity of the billing address
     *
     * @return array|null
     */
    public function checkBillingAddresses(): ?array
    {
        $billingAddress = $this->currentCart->getBillingAddress();
        $addressData = $this->extractQuoteAddressData->execute($billingAddress);
        if (!$this->validateAddressFromSchema->execute($addressData)) {
            return null;
        }
        return $addressData;
    }

    /**
     * Set a new billing address for the cloned cart
     *
     * @return array[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlInputException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException
     */
    public function setBillingAddress(): array
    {
        $maskedCartId = $this->args['input']['cart_id'];
        $billingAddress = $this->args['input']['billing_address'];
        $storeId = (int)$this->context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $this->context->getUserId(), $storeId);
        if (!empty($this->args['input']['billing_address'])) {
            $this->checkCartCheckoutAllowance->execute($cart);
            $this->setBillingAddressOnCart->execute($this->context, $cart, $billingAddress);
        }

        return [
            'cart' => [
                'model' => $cart,
            ],
        ];
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
     * @return BillingAddress
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
     * @return BillingAddress
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
