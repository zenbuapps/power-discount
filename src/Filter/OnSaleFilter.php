<?php
declare(strict_types=1);

namespace PowerDiscount\Filter;

use PowerDiscount\Domain\CartItem;

final class OnSaleFilter implements FilterInterface
{
    public function type(): string
    {
        return 'on_sale';
    }

    public function matches(array $config, CartItem $item): bool
    {
        return $item->isOnSale();
    }
}
