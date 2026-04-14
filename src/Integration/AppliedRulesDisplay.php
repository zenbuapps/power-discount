<?php
declare(strict_types=1);

namespace PowerDiscount\Integration;

use PowerDiscount\Domain\DiscountResult;

/**
 * Surface applied power-discount rules in the cart and checkout UI:
 *
 * 1. Per-item annotation under each affected line item (works in both
 *    classic shortcode cart and the modern block cart, because both call
 *    `woocommerce_get_item_data`).
 * 2. A summary panel above the cart total (classic cart only — block cart
 *    needs a Store API extension which is post-MVP).
 */
final class AppliedRulesDisplay
{
    private CartHooks $cartHooks;

    public function __construct(CartHooks $cartHooks)
    {
        $this->cartHooks = $cartHooks;
    }

    public function register(): void
    {
        add_filter('woocommerce_get_item_data', [$this, 'annotateCartItem'], 20, 2);
        add_action('woocommerce_cart_totals_before_order_total', [$this, 'renderAppliedPanel']);
        add_action('woocommerce_review_order_before_order_total', [$this, 'renderAppliedPanel']);
    }

    /**
     * @param array<int, array<string, string>> $itemData
     * @param array<string, mixed> $cartItem
     * @return array<int, array<string, string>>
     */
    public function annotateCartItem($itemData, $cartItem): array
    {
        if (!is_array($itemData)) {
            $itemData = [];
        }
        if (!function_exists('WC') || WC()->cart === null) {
            return $itemData;
        }

        $results = $this->cartHooks->getLastResultsForCart(WC()->cart);
        if ($results === null || $results === []) {
            return $itemData;
        }

        $product = $cartItem['data'] ?? null;
        if (!$product || !method_exists($product, 'get_id')) {
            return $itemData;
        }

        $productId = (int) $product->get_id();
        $parentId = method_exists($product, 'get_parent_id') ? (int) $product->get_parent_id() : 0;

        $applied = [];
        foreach ($results as $result) {
            if (!$result instanceof DiscountResult || !$result->hasDiscount()) {
                continue;
            }
            if ($result->getScope() !== DiscountResult::SCOPE_PRODUCT) {
                continue;
            }
            $affected = $result->getAffectedProductIds();
            $hits = in_array($productId, $affected, true)
                || ($parentId > 0 && in_array($parentId, $affected, true));
            if (!$hits) {
                continue;
            }
            $label = $result->getLabel();
            if ($label === null || $label === '') {
                $label = __('Discount', 'power-discount');
            }
            // De-duplicate: same label only once per line item.
            $applied[$label] = true;
        }

        foreach (array_keys($applied) as $label) {
            $itemData[] = [
                'key'     => '🎯 ' . __('Applied', 'power-discount'),
                'value'   => (string) $label,
                'display' => '',
            ];
        }

        return $itemData;
    }

    public function renderAppliedPanel(): void
    {
        if (!function_exists('WC') || WC()->cart === null) {
            return;
        }
        $results = $this->cartHooks->getLastResultsForCart(WC()->cart);
        if ($results === null || $results === []) {
            return;
        }

        // Aggregate all applied (positive-amount) results.
        $entries = [];
        foreach ($results as $result) {
            if (!$result instanceof DiscountResult || !$result->hasDiscount()) {
                continue;
            }
            $label = $result->getLabel();
            if ($label === null || $label === '') {
                $label = __('Discount', 'power-discount');
            }
            $entries[] = [
                'label'  => (string) $label,
                'amount' => (float) $result->getAmount(),
                'scope'  => $result->getScope(),
            ];
        }

        if ($entries === []) {
            return;
        }

        $priceFn = function (float $amount): string {
            if (function_exists('wc_price')) {
                return wp_strip_all_tags(wc_price($amount));
            }
            return number_format($amount, 2);
        };

        echo '<tr class="pd-applied-rules-row">';
        echo '<th>' . esc_html__('Applied discounts', 'power-discount') . '</th>';
        echo '<td>';
        echo '<ul class="pd-applied-rules-list">';
        foreach ($entries as $entry) {
            $icon = $entry['scope'] === DiscountResult::SCOPE_SHIPPING ? '🚚' : '🎯';
            echo '<li>';
            echo '<span class="pd-applied-rule-icon">' . $icon . '</span> ';
            echo '<span class="pd-applied-rule-label">' . esc_html($entry['label']) . '</span>';
            if ($entry['amount'] > 0 && $entry['scope'] !== DiscountResult::SCOPE_SHIPPING) {
                echo ' <span class="pd-applied-rule-amount">−' . esc_html($priceFn($entry['amount'])) . '</span>';
            }
            echo '</li>';
        }
        echo '</ul>';
        echo '</td>';
        echo '</tr>';
    }
}
