<?php
declare(strict_types=1);

namespace PowerDiscount\Integration;

use PowerDiscount\Engine\Aggregator;
use PowerDiscount\Engine\Calculator;
use PowerDiscount\Repository\RuleRepository;
use WC_Cart;

final class CartHooks
{
    private RuleRepository $rules;
    private Calculator $calculator;
    private Aggregator $aggregator;
    private CartContextBuilder $builder;

    /** @var array<int, \PowerDiscount\Domain\DiscountResult[]> */
    private array $lastResultsByHash = [];

    private const SESSION_SHIPPING_SAVINGS = 'pd_shipping_savings';

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
        add_action('woocommerce_before_calculate_totals', [$this, 'applyProductDiscounts'], 20, 1);
        add_action('woocommerce_cart_calculate_fees', [$this, 'applyCartFees'], 20, 1);
    }

    public function applyProductDiscounts(WC_Cart $cart): void
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $context = $this->builder->fromWcCart($cart);
        $rules = $this->rules->getActiveRules();
        $results = $this->calculator->run($rules, $context);
        $this->lastResultsByHash[spl_object_id($cart)] = $results;

        $summary = $this->aggregator->aggregate($results);

        foreach ($summary->results() as $result) {
            if ($result->getScope() !== \PowerDiscount\Domain\DiscountResult::SCOPE_PRODUCT) {
                continue;
            }
            $this->distributeProductDiscount($cart, $result);
        }
    }

    /**
     * Expose last computed results for a cart object, used by OrderDiscountLogger
     * to avoid recomputing against the already-mutated cart at checkout.
     *
     * @return \PowerDiscount\Domain\DiscountResult[]|null
     */
    public function getLastResultsForCart(\WC_Cart $cart): ?array
    {
        return $this->lastResultsByHash[spl_object_id($cart)] ?? null;
    }

    public function clearResultsForCart(\WC_Cart $cart): void
    {
        unset($this->lastResultsByHash[spl_object_id($cart)]);
    }

    /**
     * Reset shipping savings to an empty map tagged with the cart's current
     * contents hash. Call at the start of each filterRates pass so per-package
     * calls accumulate cleanly and stale entries (from prior cart state) are
     * discarded.
     */
    public function resetShippingSavings(\WC_Cart $cart): void
    {
        if (!function_exists('WC') || WC()->session === null) {
            return;
        }
        WC()->session->set(self::SESSION_SHIPPING_SAVINGS, [
            'cart_hash' => $this->cartHash($cart),
            'savings'   => [],
        ]);
    }

    public function addShippingSaving(\WC_Cart $cart, int $ruleId, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }
        if (!function_exists('WC') || WC()->session === null) {
            return;
        }
        $current = WC()->session->get(self::SESSION_SHIPPING_SAVINGS);
        $hash = $this->cartHash($cart);
        if (!is_array($current) || ($current['cart_hash'] ?? null) !== $hash) {
            $current = ['cart_hash' => $hash, 'savings' => []];
        }
        $savings = is_array($current['savings'] ?? null) ? $current['savings'] : [];
        $savings[$ruleId] = ($savings[$ruleId] ?? 0.0) + $amount;
        $current['savings'] = $savings;
        WC()->session->set(self::SESSION_SHIPPING_SAVINGS, $current);
    }

    /**
     * @return array<int, float>
     */
    public function getShippingSavingsForCart(\WC_Cart $cart): array
    {
        if (!function_exists('WC') || WC()->session === null) {
            return [];
        }
        $current = WC()->session->get(self::SESSION_SHIPPING_SAVINGS);
        if (!is_array($current)) {
            return [];
        }
        if (($current['cart_hash'] ?? null) !== $this->cartHash($cart)) {
            return [];
        }
        $savings = $current['savings'] ?? [];
        if (!is_array($savings)) {
            return [];
        }
        $out = [];
        foreach ($savings as $ruleId => $amount) {
            $out[(int) $ruleId] = (float) $amount;
        }
        return $out;
    }

    /**
     * Hash of cart items + current rule revision. Excludes chosen shipping
     * method and fees so the saving persists across the add-to-cart /
     * cart-render request pair (chosen shipping is populated lazily by WC).
     * Includes the rule revision so any rule CRUD in admin invalidates the
     * stored savings immediately.
     */
    private function cartHash(\WC_Cart $cart): string
    {
        $signature = [
            'revision' => RuleCacheBuster::current(),
            'items'    => [],
        ];
        foreach ($cart->get_cart_contents() as $key => $item) {
            $signature['items'][] = [
                (int) ($item['product_id'] ?? 0),
                (int) ($item['variation_id'] ?? 0),
                (int) ($item['quantity'] ?? 0),
            ];
        }
        return md5((string) wp_json_encode($signature));
    }

    public function applyCartFees(WC_Cart $cart): void
    {
        $results = $this->lastResultsByHash[spl_object_id($cart)] ?? null;
        if ($results === null) {
            return;
        }
        $summary = $this->aggregator->aggregate($results);
        foreach ($summary->results() as $result) {
            if ($result->getScope() !== \PowerDiscount\Domain\DiscountResult::SCOPE_CART) {
                continue;
            }
            $label = $result->getLabel() ?: __('Discount', 'power-discount');
            $cart->add_fee($label, -$result->getAmount(), false);
        }
    }

    private function distributeProductDiscount(WC_Cart $cart, \PowerDiscount\Domain\DiscountResult $result): void
    {
        $affectedIds = $result->getAffectedProductIds();
        if ($affectedIds === []) {
            return;
        }

        $eligible = [];
        $eligibleTotal = 0.0;
        foreach ($cart->get_cart() as $key => $cartItem) {
            $product = $cartItem['data'] ?? null;
            if (!$product || !method_exists($product, 'get_id')) {
                continue;
            }
            $pid = (int) $product->get_id();
            if (!in_array($pid, $affectedIds, true)) {
                if (!method_exists($product, 'get_parent_id') || !in_array((int) $product->get_parent_id(), $affectedIds, true)) {
                    continue;
                }
            }
            $price = (float) $product->get_price();
            $qty = (int) ($cartItem['quantity'] ?? 0);
            if ($price <= 0 || $qty <= 0) {
                continue;
            }
            $line = $price * $qty;
            $eligible[$key] = ['product' => $product, 'price' => $price, 'qty' => $qty, 'line' => $line];
            $eligibleTotal += $line;
        }

        if ($eligibleTotal <= 0) {
            return;
        }

        $discountToDistribute = $result->getAmount();

        $distributed = 0.0;
        $keys = array_keys($eligible);
        $lastKey = end($keys);
        foreach ($eligible as $key => $entry) {
            if ($key === $lastKey) {
                $share = $discountToDistribute - $distributed;
            } else {
                $share = round($discountToDistribute * ($entry['line'] / $eligibleTotal), 2);
                $distributed += $share;
            }
            if ($share <= 0) {
                continue;
            }
            $perUnit = $share / $entry['qty'];
            $newPrice = max(0.0, $entry['price'] - $perUnit);
            $entry['product']->set_price($newPrice);
        }
    }
}
