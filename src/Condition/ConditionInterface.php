<?php
declare(strict_types=1);

namespace PowerDiscount\Condition;

use PowerDiscount\Domain\CartContext;

interface ConditionInterface
{
    /** Which condition type this handles, e.g. "cart_subtotal". */
    public function type(): string;

    /**
     * Evaluate this condition against the cart.
     *
     * @param array<string, mixed> $config  Condition config from rule JSON.
     */
    public function evaluate(array $config, CartContext $context): bool;
}
