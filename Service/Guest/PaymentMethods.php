<?php

declare(strict_types=1);

/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

namespace Heartub\CartCloneGraphQl\Service\Guest;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Magento\QuoteGraphQl\Model\Cart\CheckCartCheckoutAllowance;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\QuoteGraphQl\Model\Cart\SetPaymentMethodOnCart as SetPaymentMethodOnCartModel;
use Magento\Quote\Model\Quote;
use Heartub\CartCloneGraphQl\Enum\AvailablePaymentMethodsEnum;

class PaymentMethods
{

    /**
     * @param PaymentInformationManagementInterface $informationManagement
     * @param ShippingMethodManagementInterface $informationShipping
     * @param GetCartForUser $getCartForUser
     * @param CheckCartCheckoutAllowance $checkCartCheckoutAllowance
     * @param SetPaymentMethodOnCartModel $setPaymentMethodOnCart
     * @param Quote|null $currentCart
     * @param array|null $args
     * @param ContextInterface|null $context
     */
    public function __construct(
        private readonly PaymentInformationManagementInterface $informationManagement,
        private readonly ShippingMethodManagementInterface $informationShipping,
        private readonly GetCartForUser $getCartForUser,
        private readonly CheckCartCheckoutAllowance $checkCartCheckoutAllowance,
        private readonly SetPaymentMethodOnCartModel $setPaymentMethodOnCart,
        private ?Quote $currentCart,
        private ?array $args,
        private ?ContextInterface $context
    ) {
    }

    /**
     * Set the payment method from the current cart to the cloned cart
     *
     * @return $this
     * @throws GraphQlInputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException
     */
    public function setPaymentMethods(): self
    {
        $availablePaymentMethods = $this->getPaymentMethodsData($this->currentCart);
        if (empty($availablePaymentMethods)) {
            throw new GraphQlInputException(__('Required parameter "code" for "payment_method" is missing.'));
        }
        $selectedPaymentMethod = $this->getSelectedPaymentMethod();
        if (empty($selectedPaymentMethod)) {
            return $this;
        }
        $code = $selectedPaymentMethod['code'] ?? '';
        if (empty($code)) {
            return $this;
        }
        $existPayment = $this->checkIfExistPayment($availablePaymentMethods, $selectedPaymentMethod);
        if (empty($existPayment)) {
            throw new GraphQlInputException(
                __('Required parameter "code" for "payment_method" to clone is missing.')
            );
        }
        $storeId = (int)$this->context->getExtensionAttributes()->getStore()->getId();
        $maskedCartId = $this->args['input']['cart_id'] ?? '';
        $newCart = $this->getCartForUser->execute($maskedCartId, $this->context->getUserId(), $storeId);
        $this->checkCartCheckoutAllowance->execute($newCart);
        $this->setPaymentMethodOnCart->execute($newCart, $selectedPaymentMethod);

        return $this;
    }

    /**
     * Verify if the selected payment method is included in the list of available payment methods
     *
     * @param array $availablePaymentMethods
     * @param array $selectedPaymentMethod
     * @return bool
     */
    private function checkIfExistPayment(array $availablePaymentMethods, array $selectedPaymentMethod): bool
    {
        foreach ($availablePaymentMethods as $availablePayment) {
            $code = $availablePayment['code'] ?? '';
            $availabilityForPayment = $selectedPaymentMethod['code'] === $code ?? '';
            if (!empty($availabilityForPayment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve selected payment method
     *
     * @return array
     */
    private function getSelectedPaymentMethod(): array
    {
        $payment = $this->currentCart->getPayment();
        if (!$payment) {
            return [];
        }

        try {
            $methodTitle = $payment->getMethodInstance()->getTitle();
        } catch (LocalizedException $e) {
            $methodTitle = '';
        }

        return [
            'code' => $payment->getMethod() ?? '',
            'title' => $methodTitle,
            'purchase_order_number' => $payment->getPoNumber(),
        ];
    }

    /**
     * Collect and return information about available payment methods
     *
     * @param CartInterface $cart
     * @return array
     * @throws GraphQlInputException
     */
    private function getPaymentMethodsData(CartInterface $cart): array
    {
        $paymentInformation = $this->informationManagement->getPaymentInformation($cart->getId());
        $paymentMethods = $paymentInformation->getPaymentMethods();
        $shippingData = $this->getShippingData((string)$cart->getId());
        $carrierCode = $shippingData['carrier_code'] ?? null;
        $grandTotal = $shippingData['grand_total'] ?? 0;
        $paymentMethodsData = [];
        foreach ($paymentMethods as $paymentMethod) {
            /**
             * Checking payment method and shipping method for zero price product
             */
            if ((int)$grandTotal === 0 && $carrierCode === AvailablePaymentMethodsEnum::FREE_SHIPPING_METHOD->value &&
                $paymentMethod->getCode() === AvailablePaymentMethodsEnum::FREE_PAYMENT_METHOD_CODE->value
            ) {
                return [
                    [
                        'title' => $paymentMethod->getTitle(),
                        'code' => $paymentMethod->getCode()
                    ]
                ];
            } elseif ((int)$grandTotal >= 0) {
                $paymentMethodsData[] = [
                    'title' => $paymentMethod->getTitle(),
                    'code' => $paymentMethod->getCode()
                ];
            }
        }
        return $paymentMethodsData;
    }

    /**
     * Retrieve selected shipping method
     *
     * @param string $cartId
     * @return array
     */
    private function getShippingData(string $cartId): array
    {
        $shippingData = [];
        try {
            $shippingMethod = $this->informationShipping->get($cartId);
            if ($shippingMethod) {
                $shippingData['carrier_code'] = $shippingMethod->getCarrierCode();
                $shippingData['grand_total'] = $shippingMethod->getBaseAmount();
            }
        } catch (LocalizedException $exception) {
            $shippingData = [];
        }
        return $shippingData;
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
     * @return PaymentMethods
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
     * @return PaymentMethods
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
     * @return PaymentMethods
     */
    public function setContext(?ContextInterface $context): self
    {
        $this->context = $context;
        return $this;
    }
}
