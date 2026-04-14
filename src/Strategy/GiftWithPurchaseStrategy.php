<?php
declare(strict_types=1);

namespace PowerDiscount\Strategy;

use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Domain\Rule;

/**
 * Gift with purchase (滿額贈).
 *
 * Trigger: cart subtotal >= threshold
 * Reward: discount the gift product(s) to NT$0 (must already be in cart)
 *
 * v1: customer must add the gift to the cart manually. The cart label and a
 * future Frontend hint should tell them what's available. Auto-add (v2) would
 * require a separate Integration class hooked to woocommerce_check_cart_items.
 */
final class GiftWithPurchaseStrategy implements DiscountStrategyInterface
{
    public function type(): string
    {
        return 'gift_with_purchase';
    }

    public function apply(Rule $rule, CartContext $context): ?DiscountResult
    {
        if ($context->isEmpty()) {
            return null;
        }

        $config = $rule->getConfig();
        $threshold = (float) ($config['threshold'] ?? 0);
        $giftIds = array_map('intval', (array) ($config['gift_product_ids'] ?? []));
        $giftQty = max(1, (int) ($config['gift_qty'] ?? 1));

        if ($threshold <= 0 || $giftIds === []) {
            return null;
        }
        if ($context->getSubtotal() < $threshold) {
            return null;
        }

        // Flatten cart items that match a gift product, sorted by price desc
        // so we always free the most expensive gift unit first.
        $candidates = [];
        foreach ($context->getItems() as $item) {
            if (!in_array($item->getProductId(), $giftIds, true)) {
                continue;
            }
            for ($i = 0; $i < $item->getQuantity(); $i++) {
                $candidates[] = [
                    'product_id' => $item->getProductId(),
                    'price'      => $item->getPrice(),
                ];
            }
        }
        if ($candidates === []) {
            return null;
        }
        usort($candidates, static function (array $a, array $b): int {
            return $b['price'] <=> $a['price'];
        });

        $taken = array_slice($candidates, 0, $giftQty);

        $totalDiscount = 0.0;
        $affected = [];
        foreach ($taken as $unit) {
            $totalDiscount += $unit['price'];
            $affected[$unit['product_id']] = true;
        }

        if ($totalDiscount <= 0) {
            return null;
        }

        return new DiscountResult(
            $rule->getId(),
            $rule->getType(),
            DiscountResult::SCOPE_PRODUCT,
            $totalDiscount,
            array_keys($affected),
            $rule->getLabel(),
            ['threshold' => $threshold, 'gift_qty' => $giftQty]
        );
    }
}
