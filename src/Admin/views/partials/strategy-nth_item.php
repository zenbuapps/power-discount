<?php
/** @var array<string, mixed> $config */
if (!defined('ABSPATH')) exit;
$tiers = $config['tiers'] ?? [];
if (empty($tiers)) {
    $tiers = [
        ['nth' => 1, 'method' => 'percentage', 'value' => 0],
        ['nth' => 2, 'method' => 'percentage', 'value' => 40],
    ];
}
$sortBy = (string) ($config['sort_by'] ?? 'price_desc');
$recursive = !empty($config['recursive']);
?>
<table class="form-table">
    <tr>
        <th><?php esc_html_e('Per-position discount', 'power-discount'); ?></th>
        <td>
            <div class="pd-repeater" data-pd-repeater="nth-tier">
                <?php foreach ($tiers as $i => $t): ?>
                    <div class="pd-repeater-row">
                        <label><?php esc_html_e('Nth item:', 'power-discount'); ?>
                            <input type="number" name="config_by_type[nth_item][tiers][<?php echo (int) $i; ?>][nth]" value="<?php echo esc_attr((string) ($t['nth'] ?? '')); ?>" class="small-text" min="1">
                        </label>
                        <select name="config_by_type[nth_item][tiers][<?php echo (int) $i; ?>][method]">
                            <option value="percentage"<?php selected($t['method'] ?? 'percentage', 'percentage'); ?>>%</option>
                            <option value="flat"<?php selected($t['method'] ?? '', 'flat'); ?>>Flat NT$</option>
                            <option value="free"<?php selected($t['method'] ?? '', 'free'); ?>>Free</option>
                        </select>
                        <input type="number" step="0.01" min="0" name="config_by_type[nth_item][tiers][<?php echo (int) $i; ?>][value]" value="<?php echo esc_attr((string) ($t['value'] ?? '')); ?>" class="small-text">
                        <button type="button" class="button button-small pd-repeater-remove">×</button>
                    </div>
                <?php endforeach; ?>
                <template class="pd-repeater-template">
                    <div class="pd-repeater-row">
                        <label><?php esc_html_e('Nth item:', 'power-discount'); ?>
                            <input type="number" name="config_by_type[nth_item][tiers][__INDEX__][nth]" value="1" class="small-text" min="1">
                        </label>
                        <select name="config_by_type[nth_item][tiers][__INDEX__][method]">
                            <option value="percentage">%</option>
                            <option value="flat">Flat NT$</option>
                            <option value="free">Free</option>
                        </select>
                        <input type="number" step="0.01" min="0" name="config_by_type[nth_item][tiers][__INDEX__][value]" value="" class="small-text">
                        <button type="button" class="button button-small pd-repeater-remove">×</button>
                    </div>
                </template>
            </div>
            <p><button type="button" class="button pd-repeater-add" data-pd-add="nth-tier">+ <?php esc_html_e('Add tier', 'power-discount'); ?></button></p>
        </td>
    </tr>
    <tr>
        <th><label><?php esc_html_e('Sort items by', 'power-discount'); ?></label></th>
        <td>
            <select name="config_by_type[nth_item][sort_by]">
                <option value="price_desc"<?php selected($sortBy, 'price_desc'); ?>><?php esc_html_e('Price (high → low)', 'power-discount'); ?></option>
                <option value="price_asc"<?php selected($sortBy, 'price_asc'); ?>><?php esc_html_e('Price (low → high)', 'power-discount'); ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <th><label><?php esc_html_e('Recursive', 'power-discount'); ?></label></th>
        <td><label><input type="checkbox" name="config_by_type[nth_item][recursive]" value="1"<?php checked($recursive, true); ?>> <?php esc_html_e('Cycle tiers every K items', 'power-discount'); ?></label></td>
    </tr>
</table>
