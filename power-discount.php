<?php
/**
 * Plugin Name: Power Discount 折扣規則外掛
 * Plugin URI:  https://powerhouse.cloud
 * Description: 專為台灣電商打造的 WooCommerce 折扣規則引擎。支援商品折扣、數量階梯、整車折扣、任選 N 件、買 X 送 Y、第 N 件 X 折、紅配綠（跨類組合）、條件免運、滿額贈共 9 種策略，內建 13 種觸發條件與 6 種商品篩選，並提供可視化規則編輯器、拖拉排序、每月重複排程、折扣統計報表等功能。
 * Version:     1.0.0
 * Author:      Powerhouse
 * Author URI:  https://powerhouse.cloud
 * License:     GPL-2.0-or-later
 * Text Domain: power-discount
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('POWER_DISCOUNT_VERSION', '1.0.0');
define('POWER_DISCOUNT_FILE', __FILE__);
define('POWER_DISCOUNT_DIR', plugin_dir_path(__FILE__));
define('POWER_DISCOUNT_URL', plugin_dir_url(__FILE__));
define('POWER_DISCOUNT_BASENAME', plugin_basename(__FILE__));

$autoload = POWER_DISCOUNT_DIR . 'vendor/autoload.php';
if (!file_exists($autoload)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Power Discount: composer install has not been run.', 'power-discount');
        echo '</p></div>';
    });
    return;
}
require_once $autoload;

register_activation_hook(__FILE__, [\PowerDiscount\Install\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [\PowerDiscount\Install\Deactivator::class, 'deactivate']);

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

add_action('plugins_loaded', static function (): void {
    \PowerDiscount\Plugin::instance()->boot();
}, 5);
