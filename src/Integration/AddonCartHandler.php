<?php
declare(strict_types=1);

namespace PowerDiscount\Integration;

use PowerDiscount\Admin\AddonMenu;
use PowerDiscount\Repository\AddonRuleRepository;

final class AddonCartHandler
{
    private AddonRuleRepository $rules;

    public function __construct(AddonRuleRepository $rules)
    {
        $this->rules = $rules;
    }

    public function register(): void
    {
        if (!AddonMenu::isEnabled()) {
            return;
        }
        add_action('woocommerce_add_to_cart', [$this, 'onAddToCart'], 10, 6);
        add_action('woocommerce_before_calculate_totals', [$this, 'applySpecialPrices'], 5);
        add_filter('woocommerce_get_item_data', [$this, 'renderCartItemMeta'], 10, 2);
    }

    /**
     * Hook: fires after a product has been added to the cart. We read
     * $_POST['pd_addon_ids'] to see if any addons were ticked on the
     * single product form, look them up in the rules, and add each as
     * its own cart line at the rule-specific special price.
     *
     * @param string $cartItemKey
     * @param int    $productId
     * @param int    $quantity
     * @param int    $variationId
     * @param array  $variation
     * @param array  $cartItemData
     */
    public function onAddToCart(
        string $cartItemKey,
        int $productId,
        int $quantity,
        int $variationId,
        array $variation,
        array $cartItemData
    ): void {
        // Re-entrance guard: skip when the current add_to_cart call is itself an addon
        if (isset($cartItemData['_pd_addon_from'])) {
            return;
        }
        $addonIds = isset($_POST['pd_addon_ids']) && is_array($_POST['pd_addon_ids'])
            ? array_map('intval', $_POST['pd_addon_ids'])
            : [];
        if ($addonIds === []) {
            return;
        }

        foreach ($addonIds as $addonId) {
            if ($addonId <= 0) {
                continue;
            }
            $rules = $this->rules->findContainingAddon($addonId);
            $specialPrice = null;
            $ruleId = 0;
            $exclude = false;
            foreach ($rules as $rule) {
                if (!$rule->isEnabled() || !$rule->matchesTarget($productId)) {
                    continue;
                }
                $specialPrice = $rule->getSpecialPriceFor($addonId);
                $ruleId = $rule->getId();
                $exclude = $rule->isExcludeFromDiscounts();
                break;
            }
            if ($specialPrice === null) {
                continue;
            }

            WC()->cart->add_to_cart($addonId, 1, 0, [], [
                '_pd_addon_from'                    => $productId,
                '_pd_addon_rule_id'                 => $ruleId,
                '_pd_addon_special_price'           => $specialPrice,
                '_pd_addon_excluded_from_discounts' => $exclude ? 1 : 0,
            ]);
        }
    }

    /**
     * Override the cart item price on every totals recalculation.
     * Runs at priority 5 so it fires before our discount engine (which
     * uses priority 20 via CartHooks).
     */
    public function applySpecialPrices(\WC_Cart $cart): void
    {
        foreach ($cart->get_cart() as $cartItemKey => $item) {
            if (!isset($item['_pd_addon_special_price'])) {
                continue;
            }
            $price = (float) $item['_pd_addon_special_price'];
            if ($price < 0) {
                continue;
            }
            if (isset($item['data']) && is_object($item['data']) && method_exists($item['data'], 'set_price')) {
                $item['data']->set_price($price);
            }
        }
    }

    /**
     * Show a "加購" badge under the addon item in cart / checkout display.
     *
     * @param array $itemData
     * @param array $cartItem
     * @return array
     */
    public function renderCartItemMeta(array $itemData, array $cartItem): array
    {
        if (!isset($cartItem['_pd_addon_from'])) {
            return $itemData;
        }
        $itemData[] = [
            'key'     => __('加購', 'power-discount'),
            'value'   => '✓',
            'display' => '',
        ];
        return $itemData;
    }
}
