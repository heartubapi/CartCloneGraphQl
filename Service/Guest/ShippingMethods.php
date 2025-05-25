<?php

declare(strict_types=1);

/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

namespace Heartub\CartCloneGraphQl\Service\Guest;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\QuoteGraphQl\Model\Cart\CheckCartCheckoutAllowance;
use Magento\QuoteGraphQl\Model\Cart\SetShippingMethodsOnCartInterface;

class ShippingMethods
{

    /**
     * @param ShippingMethodConverter $shippingMethodConverter
     * @param GetCartForUser $getCartForUser
     * @param CheckCartCheckoutAllowance $checkCartCheckoutAllowance
     * @param SetShippingMethodsOnCartInterface $setShippingMethodsOnCart
     * @param ShippingAddress $addressesOnCart
     * @param Quote|null $currentCart
     * @param array|null $args
     * @param $context
     */
    public function __construct(
        private readonly ShippingMethodConverter $shippingMethodConverter,
        private readonly GetCartForUser $getCartForUser,
        private readonly CheckCartCheckoutAllowance $checkCartCheckoutAllowance,
        private readonly SetShippingMethodsOnCartInterface $setShippingMethodsOnCart,
        private readonly ShippingAddress $addressesOnCart,
        private ?Quote $currentCart,
        private ?array $args,
        private $context
    ) {
    }

    /**
     * @return $this
     * @throws LocalizedException
     */
    public function buildByShippingAddress(): self
    {
        $addressData = $this->addressesOnCart->setCurrentCart($this->currentCart)
            ->checkShippingAddresses();
        $addressSelected = count($addressData) ? reset($addressData) : [];
        if (empty($addressSelected)) {
            $this->args['input']['shipping_methods'] = [];
            return $this;
        }
        $selectedShippingMethods = $this->selectShippingMethodToClone($addressSelected);
        if (empty($selectedShippingMethods)) {
            $this->args['input']['shipping_methods'] = [];
            return $this;
        }
        $this->args['input']['shipping_methods'] = [
            [
                'carrier_code' => $selectedShippingMethods['carrier_code'] ?? '',
                'method_code' => $selectedShippingMethods['method_code'] ?? ''
            ]
        ];

        return $this;
    }

    /**
     * @return \Magento\Quote\Model\Quote[][]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlInputException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException
     */
    public function setMethodsOnCart(): array
    {
        $maskedCartId = $this->args['input']['cart_id'] ?? '';
        $shippingMethods = $this->args['input']['shipping_methods'];
        $storeId = (int)$this->context->getExtensionAttributes()?->getStore()?->getId();
        /** @var Quote $cart */
        $cart = $this->getCartForUser->execute($maskedCartId, $this->context->getUserId(), $storeId);
        if (!empty($this->args['input']['shipping_methods'])) {
            $this->checkCartCheckoutAllowance->execute($cart);
            $this->setShippingMethodsOnCart->execute($this->context, $cart, $shippingMethods);
        }

        return [
            'cart' => [
                'model' => $cart,
            ],
        ];
    }

    /**
     * @param array|null $addressArray
     * @return array|null
     * @throws LocalizedException
     */
    public function selectShippingMethodToClone(?array $addressArray): ?array
    {
        if (empty($addressArray) || !isset($addressArray['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        /** @var Address $address */
        $address = $addressArray['model'];
        $rates = $address ? $address->getAllShippingRates() : [];
        if (!count($rates) || empty($address->getShippingMethod())) {
            return null;
        }
        list($carrierCode, $methodCode) = explode('_', $address->getShippingMethod(), 2);
        /** @var Rate $rate */
        foreach ($rates as $rate) {
            if ($rate->getCode() === $address->getShippingMethod()) {
                break;
            }
        }
        $cart = $address->getQuote();
        $selectedShippingMethod = $this->shippingMethodConverter->modelToDataObject(
            $rate,
            $cart->getQuoteCurrencyCode()
        );

        return [
            'carrier_code' => $carrierCode,
            'method_code' => $methodCode,
            'carrier_title' => $selectedShippingMethod->getCarrierTitle() ?? '',
            'method_title' => $selectedShippingMethod->getMethodTitle() ?? '',
            'amount' => [
                'value' => $address->getShippingAmount(),
                'currency' => $cart->getQuoteCurrencyCode(),
            ],
            'price_excl_tax' => [
                'value' => $selectedShippingMethod->getPriceExclTax(),
                'currency' => $cart->getQuoteCurrencyCode(),
            ],
            'price_incl_tax' => [
                'value' => $selectedShippingMethod->getPriceInclTax(),
                'currency' => $cart->getQuoteCurrencyCode(),
            ],
            'base_amount' => null,
        ];
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
     * @return ShippingMethods
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
     * @return ShippingMethods
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
