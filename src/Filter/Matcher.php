<?php
declare(strict_types=1);

namespace PowerDiscount\Filter;

use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;

final class Matcher
{
    private FilterRegistry $registry;

    public function __construct(FilterRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param array<string, mixed> $filters  { items: [...] }
     * @return CartItem[]
     */
    public function matches(array $filters, CartContext $context): array
    {
        $items = $filters['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return $context->getItems();
        }

        $matched = [];
        foreach ($context->getItems() as $cartItem) {
            if ($this->itemPassesAll($items, $cartItem)) {
                $matched[] = $cartItem;
            }
        }
        return $matched;
    }

    /**
     * @param array<int, array<string, mixed>> $filterItems
     */
    private function itemPassesAll(array $filterItems, CartItem $item): bool
    {
        foreach ($filterItems as $filterConfig) {
            if (!is_array($filterConfig) || !isset($filterConfig['type'])) {
                return false;
            }
            $filter = $this->registry->resolve((string) $filterConfig['type']);
            if ($filter === null) {
                return false;
            }
            if (!$filter->matches($filterConfig, $item)) {
                return false;
            }
        }
        return true;
    }
}
