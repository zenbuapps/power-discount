<?php
/** @var array<string, mixed> $config */
if (!defined('ABSPATH')) exit;
$countScope = (string) ($config['count_scope'] ?? 'cumulative');
$ranges = $config['ranges'] ?? [];
if (empty($ranges)) {
    $ranges = [['from' => 1, 'to' => null, 'method' => 'percentage', 'value' => 0]];
}
?>
<table class="form-table">
    <tr>
        <th><label><?php esc_html_e('Count scope', 'power-discount'); ?></label></th>
        <td>
            <select name="config_by_type[bulk][count_scope]">
                <option value="cumulative"<?php selected($countScope, 'cumulative'); ?>><?php esc_html_e('Cumulative — sum qty across all matched items', 'power-discount'); ?></option>
                <option value="per_item"<?php selected($countScope, 'per_item'); ?>><?php esc_html_e('Per item — each line counts on its own', 'power-discount'); ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e('Quantity tiers', 'power-discount'); ?></th>
        <td>
            <div class="pd-repeater" data-pd-repeater="bulk-range" data-name-prefix="config_by_type[bulk][ranges]">
                <?php foreach ($ranges as $i => $r): ?>
                    <div class="pd-repeater-row">
                        <label><?php esc_html_e('From', 'power-discount'); ?> <input type="number" name="config_by_type[bulk][ranges][<?php echo (int) $i; ?>][from]" value="<?php echo esc_attr((string) ($r['from'] ?? '1')); ?>" class="small-text" min="1"></label>
                        <label><?php esc_html_e('to', 'power-discount'); ?> <input type="number" name="config_by_type[bulk][ranges][<?php echo (int) $i; ?>][to]" value="<?php echo esc_attr($r['to'] !== null ? (string) $r['to'] : ''); ?>" class="small-text" min="1" placeholder="∞"></label>
                        <select name="config_by_type[bulk][ranges][<?php echo (int) $i; ?>][method]">
                            <option value="percentage"<?php selected($r['method'] ?? 'percentage', 'percentage'); ?>>%</option>
                            <option value="flat"<?php selected($r['method'] ?? '', 'flat'); ?>>Flat NT$</option>
                        </select>
                        <input type="number" step="0.01" min="0" name="config_by_type[bulk][ranges][<?php echo (int) $i; ?>][value]" value="<?php echo esc_attr((string) ($r['value'] ?? '')); ?>" class="small-text">
                        <button type="button" class="button button-small pd-repeater-remove">×</button>
                    </div>
                <?php endforeach; ?>
                <template class="pd-repeater-template">
                    <div class="pd-repeater-row">
                        <label><?php esc_html_e('From', 'power-discount'); ?> <input type="number" name="config_by_type[bulk][ranges][__INDEX__][from]" value="1" class="small-text" min="1"></label>
                        <label><?php esc_html_e('to', 'power-discount'); ?> <input type="number" name="config_by_type[bulk][ranges][__INDEX__][to]" value="" class="small-text" min="1" placeholder="∞"></label>
                        <select name="config_by_type[bulk][ranges][__INDEX__][method]">
                            <option value="percentage">%</option>
                            <option value="flat">Flat NT$</option>
                        </select>
                        <input type="number" step="0.01" min="0" name="config_by_type[bulk][ranges][__INDEX__][value]" value="" class="small-text">
                        <button type="button" class="button button-small pd-repeater-remove">×</button>
                    </div>
                </template>
            </div>
            <p><button type="button" class="button pd-repeater-add" data-pd-add="bulk-range">+ <?php esc_html_e('Add tier', 'power-discount'); ?></button></p>
        </td>
    </tr>
</table>
