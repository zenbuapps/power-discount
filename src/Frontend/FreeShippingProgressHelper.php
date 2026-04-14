<?php
declare(strict_types=1);

namespace PowerDiscount\Frontend;

use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\Rule;

final class FreeShippingProgressHelper
{
    /**
     * @param Rule[] $allRules
     */
    public function compute(CartContext $context, array $allRules): FreeShippingProgress
    {
        $shippingRules = array_filter(
            $allRules,
            static function (Rule $r): bool {
                return $r->getType() === 'free_shipping' && $r->isEnabled();
            }
        );

        if ($shippingRules === []) {
            return new FreeShippingProgress(false, false, null, null);
        }

        $subtotal = $context->getSubtotal();
        $achieved = false;
        $bestUnachieved = null;

        foreach ($shippingRules as $rule) {
            $threshold = $this->extractCartSubtotalThreshold($rule);
            if ($threshold === null) {
                // Rule has no cart_subtotal condition; treat as "available but no progress to display"
                continue;
            }
            if ($subtotal >= $threshold) {
                $achieved = true;
                continue;
            }
            if ($bestUnachieved === null || $threshold < $bestUnachieved) {
                $bestUnachieved = $threshold;
            }
        }

        // Design note: when at least one threshold is already achieved, we report
        // achieved=true and stop showing progress toward higher tiers. Stores with
        // multi-tier free shipping (e.g. standard NT$500, express NT$2000) will only
        // see the lowest met threshold celebrated. A future enhancement could surface
        // "remaining to next tier" once the first is met.
        if ($achieved) {
            return new FreeShippingProgress(true, true, null, null);
        }
        if ($bestUnachieved === null) {
            // Rules exist but none expressed via cart_subtotal
            return new FreeShippingProgress(true, false, null, null);
        }
        return new FreeShippingProgress(true, false, $bestUnachieved, $bestUnachieved - $subtotal);
    }

    private function extractCartSubtotalThreshold(Rule $rule): ?float
    {
        $conditions = $rule->getConditions();
        $items = $conditions['items'] ?? [];
        if (!is_array($items)) {
            return null;
        }
        foreach ($items as $item) {
            if (!is_array($item) || ($item['type'] ?? '') !== 'cart_subtotal') {
                continue;
            }
            $op = (string) ($item['operator'] ?? '');
            if (!in_array($op, ['>=', '>'], true)) {
                continue;
            }
            return (float) ($item['value'] ?? 0);
        }
        return null;
    }
}
