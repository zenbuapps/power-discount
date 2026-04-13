<?php
declare(strict_types=1);

namespace PowerDiscount\Strategy;

use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Domain\Rule;

final class FreeShippingStrategy implements DiscountStrategyInterface
{
    public function type(): string
    {
        return 'free_shipping';
    }

    public function apply(Rule $rule, CartContext $context): ?DiscountResult
    {
        if ($context->isEmpty()) {
            return null;
        }

        $config = $rule->getConfig();
        $method = (string) ($config['method'] ?? '');
        $value = (float) ($config['value'] ?? 0);

        if (!in_array($method, ['remove_shipping', 'percentage_off_shipping'], true)) {
            return null;
        }

        if ($method === 'percentage_off_shipping') {
            if ($value <= 0 || $value > 100) {
                return null;
            }
        }

        // Sentinel amount: the real shipping-line subtraction lives in Phase 4 ShippingHooks.
        // Amount > 0 is required for DiscountResult::hasDiscount() to pass aggregation.
        $sentinel = 1.0;

        return new DiscountResult(
            $rule->getId(),
            $rule->getType(),
            DiscountResult::SCOPE_SHIPPING,
            $sentinel,
            [],
            $rule->getLabel(),
            [
                'method' => $method,
                'value'  => $value,
            ]
        );
    }
}
