<?php

declare(strict_types=1);

/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

namespace Heartub\CartCloneGraphQl\Service\Guest;

use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\QuoteGraphQl\Model\Cart\CheckCartCheckoutAllowance;
use Magento\QuoteGraphQl\Model\Cart\SetShippingAddressesOnCartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\QuoteGraphQl\Model\Cart\ExtractQuoteAddressData;
use Magento\QuoteGraphQl\Model\Cart\ValidateAddressFromSchema;

class ShippingAddress
{

    /**
     * @param GetCartForUser $getCartForUser
     * @param CheckCartCheckoutAllowance $checkCartCheckoutAllowance
     * @param SetShippingAddressesOnCartInterface $setShippingAddressesOnCart
     * @param ExtractQuoteAddressData $extractQuoteAddressData
     * @param ValidateAddressFromSchema $validateAddressFromSchema
     * @param Quote|null $currentCart
     * @param array|null $args
     * @param $context
     */
    public function __construct(
        private readonly GetCartForUser $getCartForUser,
        private readonly CheckCartCheckoutAllowance $checkCartCheckoutAllowance,
        private readonly SetShippingAddressesOnCartInterface $setShippingAddressesOnCart,
        private readonly ExtractQuoteAddressData $extractQuoteAddressData,
        private readonly ValidateAddressFromSchema $validateAddressFromSchema,
        private ?Quote $currentCart,
        private ?array $args,
        private $context
    ) {
    }

    /**
     * @return array[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlInputException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException
     */
    public function setShippingAddress(): array
    {
        $maskedCartId = $this->args['input']['cart_id'] ?? '';
        $shippingAddresses = $this->args['input']['shipping_addresses'] ?? [];
        $storeId = (int)$this->context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $this->context->getUserId(), $storeId);
        if (!empty($this->args['input']['shipping_addresses'])) {
            $this->checkCartCheckoutAllowance->execute($cart);
            $this->setShippingAddressesOnCart->execute($this->context, $cart, $shippingAddresses);
        }

        return [
            'cart' => [
                'model' => $cart,
            ],
        ];
    }

    /**
     * @return $this
     */
    public function buildByShippingAddress(): self
    {
        $cartShippingAddressData = $this->checkShippingAddresses();
        if (empty($cartShippingAddressData)) {
            $this->args['input']['shipping_addresses'] = [];
            return $this;
        }

        $cartShippingAddress = $this->currentCart->getShippingAddress() ?? [];
        if (empty($cartShippingAddress->getData('entity_id'))) {
            $this->args['input']['shipping_addresses'] = [];
            return $this;
        }

        $this->args['input']['shipping_addresses'] = [
            [
                'address' => [
                    'city' => $cartShippingAddress->getData('city') ?? '',
                    'company' => $cartShippingAddress->getData('company') ?? '',
                    'country_code' => $cartShippingAddress->getData('country_code') ??
                        $cartShippingAddress->getData('country_id'),
                    "custom_attributes" => [
                    ],
                    "fax" => $cartShippingAddress->getData('fax') ?? '',
                    'firstname' => $cartShippingAddress->getData('firstname') ?? '',
                    'lastname' => $cartShippingAddress->getData('lastname') ?? '',
                    'middlename' => $cartShippingAddress->getData('middlename') ?? '',
                    'postcode' => $cartShippingAddress->getData('postcode') ?? '',
                    'prefix' => $cartShippingAddress->getData('prefix') ?? '',
                    'region' => $this->getRegionCode($cartShippingAddress),
                    'region_id' => (int)$cartShippingAddress->getData('region_id') ?? 0,
                    'save_in_address_book' => (bool)$cartShippingAddress->getData('save_in_address_book') ?? false,
                    'street' => $this->getStreet($cartShippingAddress),
                    'telephone' => $cartShippingAddress->getData('telephone') ?? '',
                    'vat_id' => $cartShippingAddress->getData('vat_id') ?? '',
                ],
                'customer_address_id' => $cartShippingAddress->getData('customer_address_id') ?? null,
                'customer_notes' => $cartShippingAddress->getData('customer_notes') ?? null,
                'pickup_location_code' => $cartShippingAddress->getData('pickup_location_code') ?? null
            ]
        ];

        return $this;
    }

    /**
     * @param Address $cartShippingAddress
     * @return string
     */
    private function getRegionCode(Address $cartShippingAddress): string
    {
        $region = $cartShippingAddress->getData('region') ?? '';
        $regionCode = $cartShippingAddress->getData('region_code') ?? '';
        $regionCodeFall = !empty($regionCode) ? $regionCode : $cartShippingAddress->getRegionModel()?->getCode();

        return $regionCodeFall ?? $region;
    }

    /**
     * @param Address $cartShippingAddress
     * @return array|string[]
     */
    private function getStreet(Address $cartShippingAddress): array
    {
        $street = $cartShippingAddress->getData('street') ?? [''];
        return is_array($street) ? $street : explode(PHP_EOL, $street ?? '');
    }

    /**
     * @return array
     */
    public function checkShippingAddresses(): array
    {
        $addressesData = [];
        if ($this->currentCart->getIsVirtual()) {
            return $addressesData;
        }
        $shippingAddresses = $this->currentCart->getAllShippingAddresses();
        if (count($shippingAddresses)) {
            foreach ($shippingAddresses as $shippingAddress) {
                $address = $this->extractQuoteAddressData->execute($shippingAddress);

                if ($this->validateAddressFromSchema->execute($address)) {
                    $addressesData[] = $address;
                }
            }
        }

        return $addressesData;
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
     * @return ShippingAddress
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
     * @return ShippingAddress
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
