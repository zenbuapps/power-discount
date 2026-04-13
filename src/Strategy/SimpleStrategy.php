<?php
declare(strict_types=1);

namespace PowerDiscount\Strategy;

use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Domain\Rule;

final class SimpleStrategy implements DiscountStrategyInterface
{
    public function type(): string
    {
        return 'simple';
    }

    public function apply(Rule $rule, CartContext $context): ?DiscountResult
    {
        if ($context->isEmpty()) {
            return null;
        }

        $config = $rule->getConfig();
        $method = (string) ($config['method'] ?? '');
        $value  = (float) ($config['value'] ?? 0);

        if (!in_array($method, ['percentage', 'flat', 'fixed_price'], true)) {
            return null;
        }
        if ($value <= 0) {
            return null;
        }

        $totalDiscount = 0.0;
        $affected = [];

        foreach ($context->getItems() as $item) {
            $perItem = $this->perItemDiscount($method, $value, $item);
            if ($perItem > 0) {
                $totalDiscount += $perItem * $item->getQuantity();
                $affected[] = $item->getProductId();
            }
        }

        if ($totalDiscount <= 0) {
            return null;
        }

        return new DiscountResult(
            $rule->getId(),
            $rule->getType(),
            DiscountResult::SCOPE_PRODUCT,
            $totalDiscount,
            $affected,
            $rule->getLabel(),
            []
        );
    }

    private function perItemDiscount(string $method, float $value, CartItem $item): float
    {
        $price = $item->getPrice();
        switch ($method) {
            case 'percentage':
                return $price * ($value / 100);
            case 'flat':
                return min($price, $value);
            case 'fixed_price':
                return $price > $value ? $price - $value : 0.0;
        }
        return 0.0;
    }
}
