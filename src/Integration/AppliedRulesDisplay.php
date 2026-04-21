<?php
declare(strict_types=1);

namespace PowerDiscount\Integration;

use PowerDiscount\Domain\DiscountResult;

/**
 * Render an "Applied discounts" summary panel under the cart / checkout totals.
 *
 * Strategy: we cannot rely on classic-cart-only hooks
 * (woocommerce_cart_totals_before_order_total) because the dev / typical
 * modern stores use the block cart. Instead we emit the panel in `wp_footer`
 * inside a hidden source container and a tiny inline script moves it to
 * the appropriate place under the cart totals on cart / checkout pages —
 * works for both classic shortcode and block-based cart.
 *
 * Each entry shows: rule label · affected product names · -amount
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
        add_action('wp_footer', [$this, 'renderFooterPanel']);
    }

    public function renderFooterPanel(): void
    {
        if (is_admin()) {
            return;
        }
        if (!function_exists('WC') || WC()->cart === null) {
            return;
        }
        // Only on cart and checkout pages.
        $isCart = function_exists('is_cart') && is_cart();
        $isCheckout = function_exists('is_checkout') && is_checkout();
        if (!$isCart && !$isCheckout) {
            return;
        }

        $results = $this->cartHooks->getLastResultsForCart(WC()->cart);
        if ($results === null || $results === []) {
            return;
        }

        $shippingSavings = $this->cartHooks->getShippingSavingsForCart(WC()->cart);
        $entries = $this->buildEntries($results, $shippingSavings);
        if ($entries === []) {
            return;
        }

        $priceFn = static function (float $amount): string {
            if (function_exists('wc_price')) {
                return wp_strip_all_tags(wc_price($amount));
            }
            return number_format($amount, 2);
        };

        ob_start();
        ?>
        <div class="pd-applied-summary">
            <h3 class="pd-applied-summary__title"><?php esc_html_e('Applied discounts', 'power-discount'); ?></h3>
            <ul class="pd-applied-summary__list">
                <?php foreach ($entries as $entry): ?>
                    <li class="pd-applied-summary__item">
                        <div class="pd-applied-summary__row">
                            <span class="pd-applied-summary__label"><?php echo esc_html($entry['label']); ?></span>
                            <?php if ($entry['amount'] > 0): ?>
                                <span class="pd-applied-summary__amount">−<?php echo esc_html($priceFn($entry['amount'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($entry['scope_text'] !== ''): ?>
                            <div class="pd-applied-summary__scope"><?php echo esc_html($entry['scope_text']); ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        $html = (string) ob_get_clean();
        ?>
        <template id="pd-applied-summary-src"><?php echo $html; // phpcs:ignore ?></template>
        <script>
        (function () {
            function tryInject() {
                var src = document.getElementById('pd-applied-summary-src');
                if (!src) return false;
                if (document.querySelector('.pd-applied-summary')) return true;

                var anchors = [
                    '.wc-block-cart__totals-title',
                    '.wp-block-woocommerce-cart-order-summary-block',
                    '.wp-block-woocommerce-checkout-order-summary-block',
                    '.cart_totals',
                    '.woocommerce-checkout-review-order',
                    '#order_review'
                ];
                var target = null;
                for (var i = 0; i < anchors.length; i++) {
                    var el = document.querySelector(anchors[i]);
                    if (el) { target = el; break; }
                }
                if (!target) return false;

                var wrapper = document.createElement('div');
                wrapper.innerHTML = src.innerHTML;
                var node = wrapper.firstElementChild;
                if (!node) return false;
                target.parentNode.insertBefore(node, target.nextSibling);
                return true;
            }

            function watch() {
                if (tryInject()) return;
                var attempts = 0;
                var iv = setInterval(function () {
                    attempts++;
                    if (tryInject() || attempts > 20) {
                        clearInterval(iv);
                    }
                }, 250);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', watch);
            } else {
                watch();
            }

            // Re-inject when block cart re-renders after qty/item changes.
            if (typeof MutationObserver !== 'undefined') {
                var mo = new MutationObserver(function () {
                    if (!document.querySelector('.pd-applied-summary')) {
                        tryInject();
                    }
                });
                mo.observe(document.body, { childList: true, subtree: true });
            }
        })();
        </script>
        <?php
    }

    /**
     * @param DiscountResult[]     $results
     * @param array<int, float>    $shippingSavings ruleId => actual shipping saving
     * @return array<int, array{label:string, amount:float, scope_text:string}>
     */
    private function buildEntries(array $results, array $shippingSavings = []): array
    {
        $entries = [];
        foreach ($results as $result) {
            if (!$result instanceof DiscountResult || !$result->hasDiscount()) {
                continue;
            }
            $label = $result->getLabel();
            if ($label === null || $label === '') {
                $label = __('Discount', 'power-discount');
            }

            // For shipping scope, the strategy returns a sentinel amount (1.0); the
            // real saving is tracked by ShippingHooks against the chosen rate.
            if ($result->getScope() === DiscountResult::SCOPE_SHIPPING) {
                $amount = (float) ($shippingSavings[$result->getRuleId()] ?? 0.0);
            } else {
                $amount = (float) $result->getAmount();
            }

            $entries[] = [
                'label'      => (string) $label,
                'amount'     => $amount,
                'scope_text' => $this->scopeText($result),
            ];
        }
        return $entries;
    }

    private function scopeText(DiscountResult $result): string
    {
        $scope = $result->getScope();

        if ($scope === DiscountResult::SCOPE_CART) {
            return __('Applies to: whole cart', 'power-discount');
        }
        if ($scope === DiscountResult::SCOPE_SHIPPING) {
            return __('Applies to: shipping', 'power-discount');
        }

        // Product scope — list affected product names
        $ids = $result->getAffectedProductIds();
        if ($ids === [] || !function_exists('wc_get_product')) {
            return '';
        }
        $names = [];
        foreach ($ids as $pid) {
            $product = wc_get_product((int) $pid);
            if ($product) {
                $names[] = $product->get_name();
            }
            if (count($names) >= 5) {
                $names[] = '…';
                break;
            }
        }
        if ($names === []) {
            return '';
        }
        return sprintf(__('Applies to: %s', 'power-discount'), implode('、', $names));
    }
}
