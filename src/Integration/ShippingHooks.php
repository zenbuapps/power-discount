<?php
declare(strict_types=1);

namespace PowerDiscount\Integration;

use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Engine\Aggregator;
use PowerDiscount\Engine\Calculator;
use PowerDiscount\Repository\RuleRepository;

final class ShippingHooks
{
    private RuleRepository $rules;
    private Calculator $calculator;
    private Aggregator $aggregator;
    private CartContextBuilder $builder;

    public function __construct(
        RuleRepository $rules,
        Calculator $calculator,
        Aggregator $aggregator,
        CartContextBuilder $builder
    ) {
        $this->rules = $rules;
        $this->calculator = $calculator;
        $this->aggregator = $aggregator;
        $this->builder = $builder;
    }

    public function register(): void
    {
        add_filter('woocommerce_package_rates', [$this, 'filterRates'], 20, 2);
    }

    /**
     * @param array<string, mixed> $rates
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    public function filterRates(array $rates, array $package): array
    {
        if (!function_exists('WC') || WC()->cart === null) {
            return $rates;
        }

        $context = $this->builder->fromWcCart(WC()->cart);
        $activeRules = $this->rules->getActiveRules();
        $results = $this->calculator->run($activeRules, $context);
        $summary = $this->aggregator->aggregate($results);

        $shippingResults = $summary->shippingResults();
        if ($shippingResults === []) {
            return $rates;
        }

        foreach ($shippingResults as $shippingResult) {
            $this->applyShippingResult($rates, $shippingResult);
        }

        return $rates;
    }

    /**
     * @param array<string, mixed> $rates  (passed by reference through parent return)
     */
    private function applyShippingResult(array &$rates, DiscountResult $result): void
    {
        $meta = $result->getMeta();
        $method = (string) ($meta['method'] ?? '');
        $value = (float) ($meta['value'] ?? 0);

        foreach ($rates as $key => $rate) {
            if (!is_object($rate)) {
                continue;
            }
            if (!method_exists($rate, 'get_cost') || !method_exists($rate, 'set_cost')) {
                continue;
            }
            $currentCost = (float) $rate->get_cost();

            if ($method === 'remove_shipping') {
                $rate->set_cost(0.0);
            } elseif ($method === 'percentage_off_shipping') {
                $discount = $currentCost * ($value / 100);
                $rate->set_cost(max(0.0, $currentCost - $discount));
            }
        }
    }
}
