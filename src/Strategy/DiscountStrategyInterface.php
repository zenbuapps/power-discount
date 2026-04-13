<?php
declare(strict_types=1);

namespace PowerDiscount\Strategy;

use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Domain\Rule;

interface DiscountStrategyInterface
{
    /**
     * Which rule type this strategy handles (e.g. "simple", "bulk").
     */
    public function type(): string;

    /**
     * Compute the discount for the given rule and cart context.
     * Return null if the rule does not apply or yields zero discount.
     */
    public function apply(Rule $rule, CartContext $context): ?DiscountResult;
}
