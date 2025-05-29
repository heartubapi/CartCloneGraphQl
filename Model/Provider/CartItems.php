<?php

declare(strict_types=1);

/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

namespace Heartub\CartCloneGraphQl\Model\Provider;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\CartItem\GetItemsData;
use Magento\QuoteGraphQl\Model\CartItem\GetPaginatedCartItems;
use Heartub\CartCloneGraphQl\Enum\CartItemsPaginatedEnum;

class CartItems
{

    /**
     * @param GetItemsData $getItemsData
     * @param GetPaginatedCartItems $pagination
     * @param bool $hasPagination
     * @param int $pageSize
     * @param int $currentPage
     * @param array|null $args
     * @param array|null $sort
     * @param Quote|null $currentCart
     */
    public function __construct(
        private readonly GetItemsData $getItemsData,
        private readonly GetPaginatedCartItems $pagination,
        private bool $hasPagination = false,
        private int $pageSize = 20,
        private int $currentPage = 1,
        private ?array $args = null,
        private ?array $sort = null,
        private ?Quote $currentCart = null
    ) {
    }

    /**
     * Retrieve all product data from the current cart
     *
     * @return array
     * @throws LocalizedException
     */
    public function getItemsData(): array
    {
        if (!isset($this->currentCart)) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        $order = CartItemsPaginatedEnum::SORT_ORDER->value;
        $orderBy = CartItemsPaginatedEnum::SORT_ORDER_BY->value;
        if (!empty($this->sort)) {
            $order = $this->sort['order'] ?? $order;
            $orderBy = $this->sort['field'] ?? $orderBy;
            $orderBy = mb_strtolower($orderBy);
        }
        $allVisibleItems = $this->currentCart->getAllVisibleItems();
        $cartItems = [];
        $paginatedCartItems = [];
        if ($this->hasPagination) {
            $this->pageSize = 20;
            $this->currentPage = 1;
            $offset = ($this->currentPage - 1) * $this->pageSize;
            $paginatedCartItems = $this->pagination->execute(
                $this->currentCart,
                $this->pageSize,
                (int) $offset,
                $orderBy,
                $order
            );
            /** @var CartItemInterface $cartItem */
            foreach ($paginatedCartItems['items'] as $cartItem) {
                foreach ($allVisibleItems as $item) {
                    if ($cartItem->getId() == $item->getId()) {
                        $cartItems[] = $item;
                    }
                }
            }
        } else {
            foreach ($allVisibleItems as $item) {
                if ($item->getId()) {
                    $cartItems[] = $item;
                }
            }
        }
        $itemsData = $this->getItemsData->execute($cartItems);

        return [
            'items' => $itemsData,
            'total_count' => $paginatedCartItems['total'] ?? count($itemsData),
            'page_info' => [
                'page_size' => $this->pageSize,
                'current_page' => $this->currentPage,
                'total_pages' => (int) ceil($paginatedCartItems['total'] ?? 1 / $this->pageSize)
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
     * @return CartItems
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
     * Set argument data coming in the resolver.
     *
     * @param array|null $args
     * @return CartItems
     */
    public function setArgs(?array $args): self
    {
        $this->args = $args;
        return $this;
    }

    /**
     * Check if pagination is set for the products in the cart
     *
     * @return bool
     */
    public function isHasPagination(): bool
    {
        return $this->hasPagination;
    }

    /**
     * Set pagination for products in the cart
     *
     * @param bool $hasPagination
     * @return CartItems
     */
    public function setHasPagination(bool $hasPagination): self
    {
        $this->hasPagination = $hasPagination;
        return $this;
    }

    /**
     * Retrieve the pagination size
     *
     * @return int
     */
    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Set the pagination size
     *
     * @param int $pageSize
     * @return CartItems
     */
    public function setPageSize(int $pageSize): self
    {
        $this->pageSize = $pageSize;
        return $this;
    }

    /**
     * Retrieve the current pagination page
     *
     * @return int
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Set the current pagination page
     *
     * @param int $currentPage
     * @return CartItems
     */
    public function setCurrentPage(int $currentPage): self
    {
        $this->currentPage = $currentPage;
        return $this;
    }

    /**
     * Retrieve the sort order
     *
     * @return array|null
     */
    public function getSort(): ?array
    {
        return $this->sort;
    }

    /**
     * Set the sort order
     *
     * @param array|null $sort
     * @return CartItems
     */
    public function setSort(?array $sort): self
    {
        $this->sort = $sort;
        return $this;
    }
}
