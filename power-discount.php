<?php
/**
 * Plugin Name: Power Discount
 * Plugin URI:  https://github.com/luke/power-discount
 * Description: WooCommerce discount rules engine - Taiwan-first.
 * Version:     0.1.0
 * Author:      Luke
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

define('POWER_DISCOUNT_VERSION', '0.1.0');
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
