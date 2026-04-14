<?php
declare(strict_types=1);

namespace PowerDiscount\Filter;

use PowerDiscount\Domain\CartItem;

final class ProductsFilter implements FilterInterface
{
    public function type(): string
    {
        return 'products';
    }

    public function matches(array $config, CartItem $item): bool
    {
        $method = strtolower((string) ($config['method'] ?? 'in'));
        $ids = array_map('intval', (array) ($config['ids'] ?? []));
        $hit = in_array($item->getProductId(), $ids, true);
        return $method === 'not_in' ? !$hit : $hit;
    }
}
