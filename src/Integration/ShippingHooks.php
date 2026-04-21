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
    private CartHooks $cartHooks;
    private bool $resetSavingsThisRequest = false;

    public function __construct(
        RuleRepository $rules,
        Calculator $calculator,
        Aggregator $aggregator,
        CartContextBuilder $builder,
        CartHooks $cartHooks
    ) {
        $this->rules = $rules;
        $this->calculator = $calculator;
        $this->aggregator = $aggregator;
        $this->builder = $builder;
        $this->cartHooks = $cartHooks;
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

        // Reset savings tracker once per request before the first package is
        // processed. Subsequent packages in the same request accumulate.
        if (!$this->resetSavingsThisRequest) {
            $this->cartHooks->resetShippingSavings(WC()->cart);
            $this->resetSavingsThisRequest = true;
        }

        // Prefer the CartHooks cache so we evaluate against the same snapshot
        // used for product/cart discounts (and don't see post-mutation prices).
        $cached = $this->cartHooks->getLastResultsForCart(WC()->cart);
        if ($cached !== null) {
            $results = $cached;
        } else {
            // Fallback: shipping rate calc fired before cart totals. Compute fresh.
            $context = $this->builder->fromWcCart(WC()->cart);
            $activeRules = $this->rules->getActiveRules();
            $results = $this->calculator->run($activeRules, $context);
        }

        $summary = $this->aggregator->aggregate($results);
        $shippingResults = $summary->shippingResults();
        if ($shippingResults === []) {
            return $rates;
        }

        $chosen = $this->getChosenRateIds();
        // First calculate_shipping pass: session hasn't been populated yet
        // (WC sets a default chosen only after filters run). Fall back to the
        // first rate in the package so we still record a saving.
        $firstRateId = '';
        foreach ($rates as $rKey => $r) {
            if (is_object($r) && isset($r->id)) {
                $firstRateId = (string) $r->id;
                break;
            }
            if ($firstRateId === '') {
                $firstRateId = (string) $rKey;
            }
        }

        foreach ($shippingResults as $shippingResult) {
            $this->applyShippingResult($rates, $shippingResult, $chosen, $firstRateId);
        }

        return $rates;
    }

    /**
     * @return string[] chosen shipping rate instance ids (e.g. ["flat_rate:1"])
     */
    private function getChosenRateIds(): array
    {
        if (!function_exists('WC') || WC()->session === null) {
            return [];
        }
        $chosen = WC()->session->get('chosen_shipping_methods');
        if (!is_array($chosen)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $chosen), static function (string $id): bool {
            return $id !== '';
        }));
    }

    /**
     * @param array<string, mixed> $rates
     * @param string[]             $chosenRateIds
     */
    private function applyShippingResult(array &$rates, DiscountResult $result, array $chosenRateIds, string $fallbackRateId = ''): void
    {
        $meta = $result->getMeta();
        $method = (string) ($meta['method'] ?? '');
        $value = (float) ($meta['value'] ?? 0);
        $allowedMethodIds = array_values(array_filter(
            array_map('strval', (array) ($meta['shipping_method_ids'] ?? [])),
            static function (string $id): bool { return $id !== ''; }
        ));

        foreach ($rates as $key => $rate) {
            if (!is_object($rate)) {
                continue;
            }
            if (!method_exists($rate, 'get_cost') || !method_exists($rate, 'set_cost')) {
                continue;
            }

            // If the rule restricts to specific shipping methods, skip rates that don't match.
            // $rate->id is the full instance id (e.g. "flat_rate:1"), $rate->method_id is just the slug.
            if ($allowedMethodIds !== []) {
                $rateInstanceId = isset($rate->id) ? (string) $rate->id : '';
                $rateMethodId = isset($rate->method_id) ? (string) $rate->method_id : '';
                $matches = in_array($rateInstanceId, $allowedMethodIds, true)
                    || ($rateMethodId !== '' && in_array($rateMethodId, $allowedMethodIds, true));
                if (!$matches) {
                    continue;
                }
            }

            $currentCost = (float) $rate->get_cost();

            if ($method === 'remove_shipping') {
                $rate->set_cost(0.0);
            } elseif ($method === 'percentage_off_shipping') {
                $discount = $currentCost * ($value / 100);
                $rate->set_cost(max(0.0, $currentCost - $discount));
            } elseif ($method === 'flat_off_shipping') {
                $rate->set_cost(max(0.0, $currentCost - $value));
            }

            // Record actual saving for display — only for the chosen rate(s),
            // so customers see what they're actually saving on the selected option.
            // If no chosen rate exists yet (first page load), fall back to the first
            // rate of the package — that matches WC's own default selection.
            $newCost = (float) $rate->get_cost();
            $saved = $currentCost - $newCost;
            $rateInstanceId = isset($rate->id) ? (string) $rate->id : (string) $key;
            $isTrackable = $chosenRateIds !== []
                ? in_array($rateInstanceId, $chosenRateIds, true)
                : ($fallbackRateId !== '' && $rateInstanceId === $fallbackRateId);
            if ($saved > 0 && $isTrackable && WC()->cart !== null) {
                $this->cartHooks->addShippingSaving(WC()->cart, $result->getRuleId(), $saved);
            }
        }
    }
}
