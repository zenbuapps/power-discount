<?php
declare(strict_types=1);

namespace PowerDiscount\Integration;

use PowerDiscount\Repository\OrderDiscountRepository;
use PowerDiscount\Repository\RuleRepository;

final class OrderDiscountLogger
{
    private RuleRepository $rules;
    private OrderDiscountRepository $orderDiscounts;
    private CartHooks $cartHooks;

    public function __construct(
        RuleRepository $rules,
        OrderDiscountRepository $orderDiscounts,
        CartHooks $cartHooks
    ) {
        $this->rules = $rules;
        $this->orderDiscounts = $orderDiscounts;
        $this->cartHooks = $cartHooks;
    }

    public function register(): void
    {
        add_action('woocommerce_checkout_order_processed', [$this, 'logOrder'], 20, 1);
    }

    public function logOrder(int $orderId): void
    {
        if (!function_exists('WC') || WC()->cart === null) {
            return;
        }

        $results = $this->cartHooks->getLastResultsForCart(WC()->cart);
        if ($results === null || $results === []) {
            return;
        }

        $titles = [];
        $activeRules = $this->rules->getActiveRules();
        foreach ($activeRules as $rule) {
            $titles[$rule->getId()] = $rule->getTitle();
        }

        $this->orderDiscounts->record($orderId, $results, $titles);

        foreach ($results as $result) {
            $this->rules->incrementUsedCount($result->getRuleId());
        }

        $this->cartHooks->clearResultsForCart(WC()->cart);
    }
}
