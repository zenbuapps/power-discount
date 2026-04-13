<?php
declare(strict_types=1);

namespace PowerDiscount\Integration;

use PowerDiscount\Engine\Aggregator;
use PowerDiscount\Engine\Calculator;
use PowerDiscount\Repository\OrderDiscountRepository;
use PowerDiscount\Repository\RuleRepository;

final class OrderDiscountLogger
{
    private RuleRepository $rules;
    private OrderDiscountRepository $orderDiscounts;
    private Calculator $calculator;
    private Aggregator $aggregator;
    private CartContextBuilder $builder;

    public function __construct(
        RuleRepository $rules,
        OrderDiscountRepository $orderDiscounts,
        Calculator $calculator,
        Aggregator $aggregator,
        CartContextBuilder $builder
    ) {
        $this->rules = $rules;
        $this->orderDiscounts = $orderDiscounts;
        $this->calculator = $calculator;
        $this->aggregator = $aggregator;
        $this->builder = $builder;
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
        $context = $this->builder->fromWcCart(WC()->cart);
        $activeRules = $this->rules->getActiveRules();
        $results = $this->calculator->run($activeRules, $context);

        $titles = [];
        foreach ($activeRules as $rule) {
            $titles[$rule->getId()] = $rule->getTitle();
        }

        $this->orderDiscounts->record($orderId, $results, $titles);

        foreach ($results as $result) {
            $this->rules->incrementUsedCount($result->getRuleId());
        }
    }
}
