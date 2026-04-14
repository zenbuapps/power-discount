<?php
/**
 * @var string $conditionLogic
 * @var array<int, array<string, mixed>> $conditionItems
 */
if (!defined('ABSPATH')) exit;
$types = [
    'cart_subtotal'    => __('Cart subtotal', 'power-discount'),
    'cart_quantity'    => __('Cart total quantity', 'power-discount'),
    'cart_line_items'  => __('Number of line items', 'power-discount'),
    'total_spent'      => __('Customer total spent (lifetime)', 'power-discount'),
    'user_role'        => __('User role', 'power-discount'),
    'user_logged_in'   => __('User logged in', 'power-discount'),
    'payment_method'   => __('Payment method', 'power-discount'),
    'shipping_method'  => __('Shipping method', 'power-discount'),
    'date_range'       => __('Date range', 'power-discount'),
    'day_of_week'      => __('Day of week', 'power-discount'),
    'time_of_day'      => __('Time of day', 'power-discount'),
    'first_order'      => __('First order', 'power-discount'),
    'birthday_month'   => __('Birthday month', 'power-discount'),
];
$render_row = function (int $i, array $item) use ($types) {
    $t = (string) ($item['type'] ?? 'cart_subtotal');
    ?>
    <div class="pd-repeater-row pd-condition-row">
        <select name="conditions[items][<?php echo $i; ?>][type]" class="pd-condition-type">
            <?php foreach ($types as $v => $l): ?>
                <option value="<?php echo esc_attr($v); ?>"<?php selected($t, $v); ?>><?php echo esc_html($l); ?></option>
            <?php endforeach; ?>
        </select>

        <!-- value pattern: operator + number (cart_subtotal/qty/line_items/total_spent) -->
        <span class="pd-cond-fields" data-for="cart_subtotal,cart_quantity,cart_line_items,total_spent"<?php echo in_array($t, ['cart_subtotal','cart_quantity','cart_line_items','total_spent'], true) ? '' : ' style="display:none"'; ?>>
            <select name="conditions[items][<?php echo $i; ?>][operator]">
                <?php foreach (['>=','>','=','<=','<','!='] as $op):
                    $current = (string) ($item['operator'] ?? '>=');
                ?>
                    <option value="<?php echo esc_attr($op); ?>"<?php selected($current, $op); ?>><?php echo esc_html($op); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" step="0.01" name="conditions[items][<?php echo $i; ?>][value]" value="<?php echo esc_attr((string) ($item['value'] ?? '')); ?>" class="small-text">
        </span>

        <!-- user_role -->
        <span class="pd-cond-fields" data-for="user_role"<?php echo $t === 'user_role' ? '' : ' style="display:none"'; ?>>
            <input type="text" name="conditions[items][<?php echo $i; ?>][roles_csv]" value="<?php echo esc_attr(implode(',', (array) ($item['roles'] ?? []))); ?>" class="regular-text" placeholder="customer, subscriber">
            <span class="description"><?php esc_html_e('Comma-separated role slugs', 'power-discount'); ?></span>
        </span>

        <!-- user_logged_in -->
        <span class="pd-cond-fields" data-for="user_logged_in"<?php echo $t === 'user_logged_in' ? '' : ' style="display:none"'; ?>>
            <label><input type="checkbox" name="conditions[items][<?php echo $i; ?>][is_logged_in]" value="1"<?php checked(!empty($item['is_logged_in'])); ?>> <?php esc_html_e('Require logged in', 'power-discount'); ?></label>
        </span>

        <!-- payment_method / shipping_method -->
        <span class="pd-cond-fields" data-for="payment_method,shipping_method"<?php echo in_array($t, ['payment_method','shipping_method'], true) ? '' : ' style="display:none"'; ?>>
            <input type="text" name="conditions[items][<?php echo $i; ?>][methods_csv]" value="<?php echo esc_attr(implode(',', (array) ($item['methods'] ?? []))); ?>" class="regular-text" placeholder="e.g. cod, bacs, stripe">
            <span class="description"><?php esc_html_e('Comma-separated method slugs', 'power-discount'); ?></span>
        </span>

        <!-- date_range -->
        <span class="pd-cond-fields" data-for="date_range"<?php echo $t === 'date_range' ? '' : ' style="display:none"'; ?>>
            <input type="text" name="conditions[items][<?php echo $i; ?>][from]" value="<?php echo esc_attr((string) ($item['from'] ?? '')); ?>" placeholder="YYYY-MM-DD HH:MM:SS">
            →
            <input type="text" name="conditions[items][<?php echo $i; ?>][to]" value="<?php echo esc_attr((string) ($item['to'] ?? '')); ?>" placeholder="YYYY-MM-DD HH:MM:SS">
        </span>

        <!-- day_of_week -->
        <span class="pd-cond-fields" data-for="day_of_week"<?php echo $t === 'day_of_week' ? '' : ' style="display:none"'; ?>>
            <?php
            $dayLabels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
            $selected = array_map('intval', (array) ($item['days'] ?? []));
            foreach ($dayLabels as $dv => $dl): ?>
                <label><input type="checkbox" name="conditions[items][<?php echo $i; ?>][days][]" value="<?php echo $dv; ?>"<?php checked(in_array($dv, $selected, true)); ?>> <?php echo esc_html($dl); ?></label>
            <?php endforeach; ?>
        </span>

        <!-- time_of_day -->
        <span class="pd-cond-fields" data-for="time_of_day"<?php echo $t === 'time_of_day' ? '' : ' style="display:none"'; ?>>
            <input type="text" name="conditions[items][<?php echo $i; ?>][from]" value="<?php echo esc_attr((string) ($item['from'] ?? '')); ?>" placeholder="HH:MM" style="width:70px;">
            →
            <input type="text" name="conditions[items][<?php echo $i; ?>][to]" value="<?php echo esc_attr((string) ($item['to'] ?? '')); ?>" placeholder="HH:MM" style="width:70px;">
        </span>

        <!-- first_order -->
        <span class="pd-cond-fields" data-for="first_order"<?php echo $t === 'first_order' ? '' : ' style="display:none"'; ?>>
            <label><input type="checkbox" name="conditions[items][<?php echo $i; ?>][is_first_order]" value="1"<?php checked(!empty($item['is_first_order'])); ?>> <?php esc_html_e('Customer first order only', 'power-discount'); ?></label>
        </span>

        <!-- birthday_month -->
        <span class="pd-cond-fields" data-for="birthday_month"<?php echo $t === 'birthday_month' ? '' : ' style="display:none"'; ?>>
            <label><input type="checkbox" name="conditions[items][<?php echo $i; ?>][match_current_month]" value="1"<?php checked(!empty($item['match_current_month'])); ?>> <?php esc_html_e('Match current month', 'power-discount'); ?></label>
        </span>

        <button type="button" class="button button-small pd-repeater-remove">×</button>
    </div>
    <?php
};
?>
<p>
    <label><?php esc_html_e('Logic', 'power-discount'); ?>
        <select name="conditions[logic]">
            <option value="and"<?php selected($conditionLogic, 'and'); ?>><?php esc_html_e('AND (all)', 'power-discount'); ?></option>
            <option value="or"<?php selected($conditionLogic, 'or'); ?>><?php esc_html_e('OR (any)', 'power-discount'); ?></option>
        </select>
    </label>
</p>
<div class="pd-repeater" data-pd-repeater="condition-row">
    <?php foreach ($conditionItems as $i => $item) { $render_row((int) $i, (array) $item); } ?>
</div>
<p><button type="button" class="button pd-repeater-add" data-pd-add="condition-row">+ <?php esc_html_e('Add condition', 'power-discount'); ?></button></p>
