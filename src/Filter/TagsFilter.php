<?php
declare(strict_types=1);

namespace PowerDiscount\Filter;

use PowerDiscount\Domain\CartItem;

final class TagsFilter implements FilterInterface
{
    public function type(): string
    {
        return 'tags';
    }

    public function matches(array $config, CartItem $item): bool
    {
        $method = strtolower((string) ($config['method'] ?? 'in'));
        $ids = array_map('intval', (array) ($config['ids'] ?? []));
        if ($ids === []) {
            return false;
        }
        $hit = $item->isInTags($ids);
        return $method === 'not_in' ? !$hit : $hit;
    }
}
