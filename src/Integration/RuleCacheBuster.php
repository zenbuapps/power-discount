<?php
declare(strict_types=1);

namespace PowerDiscount\Integration;

/**
 * Tracks a monotonic revision number that increments every time a rule is
 * created / updated / deleted / reordered. Consumers use this to invalidate
 * cached state so rule changes take effect immediately without customers
 * having to clear their cart and re-add items.
 *
 * Two things happen on bump():
 *  1. `power_discount_rule_revision` option is incremented. CartHooks folds
 *     this into the cart hash it uses to tag `pd_shipping_savings`, so the
 *     display map invalidates automatically.
 *  2. WooCommerce's `shipping` transient version is rotated, which flushes
 *     every customer's pre-computed shipping rate session cache — the
 *     critical piece so that actual runtime shipping pricing re-evaluates
 *     against the new rule on next cart/checkout page load.
 */
final class RuleCacheBuster
{
    private const OPTION = 'power_discount_rule_revision';

    public static function bump(): void
    {
        if (function_exists('get_option') && function_exists('update_option')) {
            $current = (int) get_option(self::OPTION, 0);
            update_option(self::OPTION, $current + 1, false);
        }
        if (class_exists('\\WC_Cache_Helper')) {
            \WC_Cache_Helper::get_transient_version('shipping', true);
        }
    }

    public static function current(): int
    {
        if (function_exists('get_option')) {
            return (int) get_option(self::OPTION, 0);
        }
        return 0;
    }
}
