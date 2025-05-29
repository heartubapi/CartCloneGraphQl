<?php

declare(strict_types=1);

/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

namespace Heartub\CartCloneGraphQl\Service\Guest;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Quote\Model\Cart\Data\AddProductsToCartOutput;
use Magento\Quote\Model\Cart\Data\CartItemFactory;
use Magento\Quote\Model\Cart\Data\Error;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\CartItem\PrecursorInterface;
use Magento\Quote\Model\QuoteMutexInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Quote\Model\Cart\AddProductsToCart as AddProductsToCartService;
use Heartub\CartCloneGraphQl\Model\Provider\CartItems;
use Heartub\CartCloneGraphQl\Enum\ProductTypeEnum;

class AddProducts
{
    /**
     * @var array
     */
    private const PRODUCT_DEFAULT_KEY_VALUES = [
        'entered_options' => [
            [
                'uid' => '',
                'value' => ''
            ]
        ],
        'parent_sku' => '',
        'quantity' => 0,
        'selected_options' => [],
        'sku' => '',
    ];

    /**
     * @param CartItems $cartItems
     * @param GetCartForUser $getCartForUser
     * @param AddProductsToCartService $addProductsToCartService
     * @param PrecursorInterface|null $cartItemPrecursor
     * @param ProductInterface|null $product
     * @param QuoteMutexInterface $quoteMutex
     * @param Quote|null $currentCart
     * @param ContextInterface|null $context
     * @param array|null $args
     * @param float|null $qty
     * @param array|null $itemData
     */
    public function __construct(
        private readonly CartItems $cartItems,
        private readonly GetCartForUser $getCartForUser,
        private readonly AddProductsToCartService $addProductsToCartService,
        private ?PrecursorInterface $cartItemPrecursor,
        private ?ProductInterface $product,
        private QuoteMutexInterface $quoteMutex,
        private ?Quote $currentCart,
        private ?ContextInterface $context,
        private ?array $args,
        private ?float $qty,
        private ?array $itemData
    ) {
        $this->cartItemPrecursor = $cartItemPrecursor ?: ObjectManager::getInstance()->get(PrecursorInterface::class);
    }

    /**
     * Copy product items from the current cart to the new cloned cart
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setProductsOnNewCart(): self
    {
        $cartItems = $this->cartItems->setCurrentCart($this->currentCart)
            ->getItemsData();
        foreach ($cartItems['items'] ?? [] as $itemData) {
            $productData = $itemData['product'] ?? [];
            $cartItem = $itemData['model'] ?? null;
            $qty = $itemData['quantity'] ?? 0.0;
            $this->setQty($qty)
                ->setItemData($productData)
                ->setProduct($cartItem->getProduct() ?? null)
                ->buildByProductType()
                ->addProduct();
        }

        return $this;
    }

    /**
     * Build product information to add by product type
     *
     * @return $this
     */
    public function buildByProductType(): self
    {
        $currentProductType = $this->product->getTypeId() ?? '';
        $cartItems = match (true) {
            ProductTypeEnum::SIMPLE->value === $currentProductType => $this->simpleProduct(),
            ProductTypeEnum::CONFIGURABLE->value === $currentProductType => $this->configurableProduct(),
            //TODO: this product types:
            ProductTypeEnum::GROUPED->value === $currentProductType => '',
            ProductTypeEnum::BUNDLE->value === $currentProductType => '',
            ProductTypeEnum::DOWNLOADABLE->value === $currentProductType => '',
            ProductTypeEnum::GIFTCARD->value === $currentProductType => '',
            ProductTypeEnum::VIRTUAL->value === $currentProductType => '',
        };

        return $this;
    }

    /**
     * Retrieve parameters for simple product requests
     *
     * @return array[]
     */
    private function simpleProduct(): array
    {
        $newValues = self::PRODUCT_DEFAULT_KEY_VALUES;
        $newValues['quantity'] = $this->qty ?? 0.0;
        $newValues['sku'] = $this->product->getSku() ?? '';
        return $this->args['cartItems'] = [$newValues];
    }

    /**
     * Retrieve parameters for configurable product requests
     *
     * @return array
     */
    private function configurableProduct(): array
    {
        $newValues = self::PRODUCT_DEFAULT_KEY_VALUES;
        $newValues['quantity'] = $this->qty ?? 0.0;
        $newValues['parent_sku'] = $this->product->getData('sku') ?? '';
        $newValues['sku'] = $this->product->getSku() ?? '';
        return $this->args['cartItems'][] = $newValues;
    }

    /**
     * Add a new product to the cloned cart
     *
     * @return array|null
     */
    public function addProduct(): ?array
    {
        return $this->quoteMutex->execute(
            [$this->args['cartId']],
            \Closure::fromCallable([$this, 'run']),
            [$this->context, $this->args]
        );
    }

    /**
     * Execute the add-to-cart function
     *
     * @param ContextInterface $context
     * @param array|null $args
     * @return array
     * @throws GraphQlInputException
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function run($context, ?array $args): array
    {
        $maskedCartId = $args['cartId'];
        $cartItemsData = $args['cartItems'];
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);
        $cartItemsData = $this->cartItemPrecursor->process($cartItemsData, $context);
        $cartItems = [];
        foreach ($cartItemsData as $cartItemData) {
            $cartItems[] = (new CartItemFactory())->create($cartItemData);
        }
        /** @var AddProductsToCartOutput $addProductsToCartOutput */
        $addProductsToCartOutput = $this->addProductsToCartService->execute($maskedCartId, $cartItems);

        return [
            'cart' => [
                'model' => $addProductsToCartOutput->getCart(),
            ],
            'user_errors' => array_map(
                function (Error $error) {
                    return [
                        'code' => $error->getCode(),
                        'message' => $error->getMessage(),
                        'path' => [$error->getCartItemPosition()]
                    ];
                },
                array_merge($addProductsToCartOutput->getErrors(), $this->cartItemPrecursor->getErrors())
            )
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
     * @return AddProducts
     */
    public function setCurrentCart(?Quote $currentCart): self
    {
        $this->currentCart = $currentCart;
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
     * @return AddProducts
     */
    public function setArgs(?array $args): self
    {
        $this->args = $args;
        return $this;
    }

    /**
     * Retrieve the quantity of the current item
     *
     * @return float|null
     */
    public function getQty(): ?float
    {
        return $this->qty;
    }

    /**
     * Set the quantity for the current item
     *
     * @param float|null $qty
     * @return AddProducts
     */
    public function setQty(?float $qty): self
    {
        $this->qty = $qty;
        return $this;
    }

    /**
     * Retrieve data for the current item
     *
     * @return array|null
     */
    public function getItemData(): ?array
    {
        return $this->itemData;
    }

    /**
     * Set data for the current item in the current cart
     *
     * @param array|null $itemData
     * @return AddProducts
     */
    public function setItemData(?array $itemData): self
    {
        $this->itemData = $itemData;
        return $this;
    }

    /**
     * Retrieve the product
     *
     * @return ProductInterface|null
     */
    public function getProduct(): ?ProductInterface
    {
        return $this->product;
    }

    /**
     * Set the product
     *
     * @param ProductInterface|null $product
     * @return AddProducts
     */
    public function setProduct(?ProductInterface $product): self
    {
        $this->product = $product;
        return $this;
    }
}
