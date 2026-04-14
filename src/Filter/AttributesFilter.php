<?php
declare(strict_types=1);

namespace PowerDiscount\Filter;

use PowerDiscount\Domain\CartItem;

final class AttributesFilter implements FilterInterface
{
    public function type(): string
    {
        return 'attributes';
    }

    public function matches(array $config, CartItem $item): bool
    {
        if (!isset($config['attribute'], $config['values'])) {
            return false;
        }
        $method = strtolower((string) ($config['method'] ?? 'in'));
        $attribute = (string) $config['attribute'];
        $values = (array) $config['values'];

        $hit = $item->hasAttribute($attribute, $values);
        return $method === 'not_in' ? !$hit : $hit;
    }
}
