<?php
/** @var array<string, mixed> $config */
if (!defined('ABSPATH')) exit;
$threshold = $config['threshold'] ?? '';
$giftIds = (array) ($config['gift_product_ids'] ?? []);
$giftQty = (int) ($config['gift_qty'] ?? 1);
?>
<table class="form-table">
    <tr>
        <th><label><?php esc_html_e('Spend threshold', 'power-discount'); ?></label></th>
        <td>
            <input type="number" step="0.01" min="0" name="config_by_type[gift_with_purchase][threshold]" value="<?php echo esc_attr((string) $threshold); ?>" class="small-text"> NT$
            <p class="description"><?php esc_html_e('When cart subtotal reaches this amount, the gift becomes free.', 'power-discount'); ?></p>
        </td>
    </tr>
    <tr>
        <th><label><?php esc_html_e('Gift products', 'power-discount'); ?></label></th>
        <td>
            <select
                name="config_by_type[gift_with_purchase][gift_product_ids][]"
                class="wc-product-search"
                multiple
                data-placeholder="<?php esc_attr_e('Search gift products', 'power-discount'); ?>"
                data-action="woocommerce_json_search_products_and_variations"
                style="min-width:360px;">
                <?php foreach ($giftIds as $pid):
                    $pid = (int) $pid;
                    $product = function_exists('wc_get_product') ? wc_get_product($pid) : null;
                    if ($product): ?>
                        <option value="<?php echo $pid; ?>" selected><?php echo esc_html($product->get_formatted_name()); ?></option>
                <?php endif; endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e('Customers must add the gift to the cart themselves; the plugin will discount it to NT$0 once the threshold is met. If multiple gifts are eligible, the most expensive one is freed.', 'power-discount'); ?></p>
        </td>
    </tr>
    <tr>
        <th><label><?php esc_html_e('Gift quantity', 'power-discount'); ?></label></th>
        <td>
            <input type="number" min="1" name="config_by_type[gift_with_purchase][gift_qty]" value="<?php echo esc_attr((string) $giftQty); ?>" class="small-text">
        </td>
    </tr>
</table>
