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

        foreach ($eligible as $entry) {
            $share = $discountToDistribute * ($entry['line'] / $eligibleTotal);
            $perUnit = $share / $entry['qty'];
            $newPrice = max(0.0, $entry['price'] - $perUnit);
            $entry['product']->set_price($newPrice);
        }
    }
}
