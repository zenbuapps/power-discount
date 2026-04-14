<?php
declare(strict_types=1);

namespace PowerDiscount\Frontend;

use PowerDiscount\Integration\CartContextBuilder;
use PowerDiscount\Repository\RuleRepository;

final class FreeShippingBar
{
    private RuleRepository $rules;
    private CartContextBuilder $builder;
    private FreeShippingProgressHelper $helper;

    public function __construct(RuleRepository $rules, CartContextBuilder $builder, FreeShippingProgressHelper $helper)
    {
        $this->rules = $rules;
        $this->builder = $builder;
        $this->helper = $helper;
    }

    public function register(): void
    {
        add_action('woocommerce_before_cart', [$this, 'render']);
        add_action('woocommerce_before_checkout_form', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        if (function_exists('is_cart') && function_exists('is_checkout') && (is_cart() || is_checkout())) {
            wp_enqueue_style(
                'power-discount-frontend',
                POWER_DISCOUNT_URL . 'assets/frontend/frontend.css',
                [],
                POWER_DISCOUNT_VERSION
            );
        }
    }

    public function render(): void
    {
        if (!function_exists('WC') || WC()->cart === null) {
            return;
        }

        $context = $this->builder->fromWcCart(WC()->cart);
        $allRules = $this->rules->findAll();
        $progress = $this->helper->compute($context, $allRules);

        if (!$progress->hasFreeShippingRule) {
            return;
        }

        if ($progress->achieved) {
            $message = '🎉 ' . esc_html__('You qualify for free shipping!', 'power-discount');
            $percent = 100;
        } elseif ($progress->threshold !== null && $progress->remaining !== null) {
            $remainingFormatted = function_exists('wc_price')
                ? wp_strip_all_tags(wc_price($progress->remaining))
                : number_format($progress->remaining, 2);
            $message = sprintf(
                /* translators: %s = remaining amount */
                esc_html__('Add %s more to qualify for free shipping', 'power-discount'),
                esc_html($remainingFormatted)
            );
            $achieved = $progress->threshold - $progress->remaining;
            $percent = (int) max(0, min(100, ($achieved / $progress->threshold) * 100));
        } else {
            $message = esc_html__('Free shipping promotions available — see checkout for details.', 'power-discount');
            $percent = 0;
        }

        echo '<div class="pd-shipping-bar">';
        echo '<div class="pd-shipping-bar__message">' . $message . '</div>';
        if ($percent > 0) {
            echo '<div class="pd-shipping-bar__track"><div class="pd-shipping-bar__fill" style="width:' . (int) $percent . '%"></div></div>';
        }
        echo '</div>';
    }
}
