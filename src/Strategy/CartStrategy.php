<?php
declare(strict_types=1);

namespace PowerDiscount\Strategy;

use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Domain\Rule;

final class CartStrategy implements DiscountStrategyInterface
{
    public function type(): string
    {
        return 'cart';
    }

    public function apply(Rule $rule, CartContext $context): ?DiscountResult
    {
        if ($context->isEmpty()) {
            return null;
        }

        $config = $rule->getConfig();
        $method = (string) ($config['method'] ?? '');
        $value  = (float) ($config['value'] ?? 0);
        if ($value <= 0) {
            return null;
        }

        $subtotal = $context->getSubtotal();
        if ($subtotal <= 0) {
            return null;
        }

        $amount = 0.0;
        switch ($method) {
            case 'percentage':
                $amount = $subtotal * ($value / 100);
                break;
            case 'flat_total':
                $amount = min($value, $subtotal);
                break;
            case 'flat_per_item':
                $amount = $value * $context->getTotalQuantity();
                $amount = min($amount, $subtotal);
                break;
            default:
                return null;
        }

        if ($amount <= 0) {
            return null;
        }

        $affected = array_map(
            static function ($item): int {
                return $item->getProductId();
            },
            $context->getItems()
        );

        return new DiscountResult(
            $rule->getId(),
            $rule->getType(),
            DiscountResult::SCOPE_CART,
            $amount,
            $affected,
            $rule->getLabel(),
            ['method' => $method]
        );
    }
}
