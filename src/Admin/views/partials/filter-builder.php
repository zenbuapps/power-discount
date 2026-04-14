<?php
/** @var array<int, array<string, mixed>> $filterItems */
if (!defined('ABSPATH')) exit;
?>
<div class="pd-repeater" data-pd-repeater="filter-row">
    <?php foreach ($filterItems as $i => $item):
        $type = (string) ($item['type'] ?? 'all_products');
        $method = (string) ($item['method'] ?? 'in');
        $ids = (array) ($item['ids'] ?? []);
    ?>
        <div class="pd-repeater-row pd-filter-row">
            <select name="filters[items][<?php echo (int) $i; ?>][type]" class="pd-filter-type">
                <option value="all_products"<?php selected($type, 'all_products'); ?>><?php esc_html_e('All products', 'power-discount'); ?></option>
                <option value="products"<?php selected($type, 'products'); ?>><?php esc_html_e('Specific products', 'power-discount'); ?></option>
                <option value="categories"<?php selected($type, 'categories'); ?>><?php esc_html_e('Categories', 'power-discount'); ?></option>
                <option value="tags"<?php selected($type, 'tags'); ?>><?php esc_html_e('Tags', 'power-discount'); ?></option>
                <option value="attributes"<?php selected($type, 'attributes'); ?>><?php esc_html_e('Attributes', 'power-discount'); ?></option>
                <option value="on_sale"<?php selected($type, 'on_sale'); ?>><?php esc_html_e('On sale', 'power-discount'); ?></option>
            </select>

            <select name="filters[items][<?php echo (int) $i; ?>][method]" class="pd-filter-method">
                <option value="in"<?php selected($method, 'in'); ?>><?php esc_html_e('in list', 'power-discount'); ?></option>
                <option value="not_in"<?php selected($method, 'not_in'); ?>><?php esc_html_e('not in list', 'power-discount'); ?></option>
            </select>

            <span class="pd-filter-value pd-filter-value-products"<?php echo $type === 'products' ? '' : ' style="display:none"'; ?>>
                <select name="filters[items][<?php echo (int) $i; ?>][ids][]" class="wc-product-search" multiple data-placeholder="<?php esc_attr_e('Search products', 'power-discount'); ?>" data-action="woocommerce_json_search_products_and_variations" style="min-width:300px;">
                    <?php if ($type === 'products') foreach ($ids as $pid): $pid = (int) $pid; $prod = function_exists('wc_get_product') ? wc_get_product($pid) : null; if ($prod): ?>
                        <option value="<?php echo $pid; ?>" selected><?php echo esc_html($prod->get_formatted_name()); ?></option>
                    <?php endif; endforeach; ?>
                </select>
            </span>

            <span class="pd-filter-value pd-filter-value-categories"<?php echo $type === 'categories' ? '' : ' style="display:none"'; ?>>
                <select name="filters[items][<?php echo (int) $i; ?>][ids][]" class="pd-category-select" multiple data-placeholder="<?php esc_attr_e('Select categories', 'power-discount'); ?>" style="min-width:300px;">
                    <?php if ($type === 'categories') foreach ($ids as $cid): $cid = (int) $cid; $term = get_term($cid, 'product_cat'); if ($term && !is_wp_error($term)): ?>
                        <option value="<?php echo $cid; ?>" selected><?php echo esc_html($term->name); ?></option>
                    <?php endif; endforeach; ?>
                </select>
            </span>

            <span class="pd-filter-value pd-filter-value-tags"<?php echo $type === 'tags' ? '' : ' style="display:none"'; ?>>
                <select name="filters[items][<?php echo (int) $i; ?>][ids][]" class="pd-tag-select" multiple data-placeholder="<?php esc_attr_e('Select tags', 'power-discount'); ?>" style="min-width:300px;">
                    <?php if ($type === 'tags') foreach ($ids as $tid): $tid = (int) $tid; $term = get_term($tid, 'product_tag'); if ($term && !is_wp_error($term)): ?>
                        <option value="<?php echo $tid; ?>" selected><?php echo esc_html($term->name); ?></option>
                    <?php endif; endforeach; ?>
                </select>
            </span>

            <button type="button" class="button button-small pd-repeater-remove">×</button>
        </div>
    <?php endforeach; ?>
</div>
<p><button type="button" class="button pd-repeater-add" data-pd-add="filter-row">+ <?php esc_html_e('Add filter', 'power-discount'); ?></button></p>
