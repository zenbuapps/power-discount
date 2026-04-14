<?php
/**
 * @var array<string, mixed> $formData
 * @var bool $isNew
 * @var array<string, string> $strategyTypes
 */
if (!defined('ABSPATH')) {
    exit;
}

$pageTitle = $isNew ? __('Add Rule', 'power-discount') : __('Edit Rule', 'power-discount');
$listUrl = admin_url('admin.php?page=power-discount');
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($pageTitle); ?></h1>
    <a href="<?php echo esc_url($listUrl); ?>" class="page-title-action">
        <?php esc_html_e('Back to list', 'power-discount'); ?>
    </a>
    <hr class="wp-header-end">

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="pd_save_rule">
        <input type="hidden" name="id" value="<?php echo (int) $formData['id']; ?>">
        <?php wp_nonce_field('pd_save_rule_' . (int) $formData['id']); ?>

        <table class="form-table">
            <tr>
                <th><label for="pd-title"><?php esc_html_e('Title', 'power-discount'); ?> <span style="color:red">*</span></label></th>
                <td>
                    <input type="text" id="pd-title" name="title" value="<?php echo esc_attr((string) $formData['title']); ?>" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th><label for="pd-type"><?php esc_html_e('Discount type', 'power-discount'); ?></label></th>
                <td>
                    <select id="pd-type" name="type">
                        <?php foreach ($strategyTypes as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>"<?php selected($formData['type'], $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="pd-status"><?php esc_html_e('Status', 'power-discount'); ?></label></th>
                <td>
                    <select id="pd-status" name="status">
                        <option value="1"<?php selected($formData['status'], 1); ?>><?php esc_html_e('Enabled', 'power-discount'); ?></option>
                        <option value="0"<?php selected($formData['status'], 0); ?>><?php esc_html_e('Disabled', 'power-discount'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="pd-priority"><?php esc_html_e('Priority', 'power-discount'); ?></label></th>
                <td>
                    <input type="number" id="pd-priority" name="priority" value="<?php echo (int) $formData['priority']; ?>" min="0" class="small-text">
                    <p class="description"><?php esc_html_e('Lower number = higher priority. Rules run in priority ASC order.', 'power-discount'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="pd-exclusive"><?php esc_html_e('Exclusive', 'power-discount'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="pd-exclusive" name="exclusive" value="1"<?php checked($formData['exclusive'], 1); ?>>
                        <?php esc_html_e('Stop processing further rules after this matches', 'power-discount'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e('Schedule', 'power-discount'); ?></label></th>
                <td>
                    <input type="text" name="starts_at" value="<?php echo esc_attr((string) $formData['starts_at']); ?>" placeholder="YYYY-MM-DD HH:MM:SS"> →
                    <input type="text" name="ends_at" value="<?php echo esc_attr((string) $formData['ends_at']); ?>" placeholder="YYYY-MM-DD HH:MM:SS">
                    <p class="description"><?php esc_html_e('Leave both blank for no schedule restriction.', 'power-discount'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="pd-usage-limit"><?php esc_html_e('Usage limit', 'power-discount'); ?></label></th>
                <td>
                    <input type="number" id="pd-usage-limit" name="usage_limit" value="<?php echo esc_attr((string) $formData['usage_limit']); ?>" class="small-text" min="0">
                    <span class="description"><?php esc_html_e('Used:', 'power-discount'); ?> <?php echo (int) $formData['used_count']; ?></span>
                    <p class="description"><?php esc_html_e('Leave blank for unlimited.', 'power-discount'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="pd-label"><?php esc_html_e('Cart label', 'power-discount'); ?></label></th>
                <td>
                    <input type="text" id="pd-label" name="label" value="<?php echo esc_attr((string) $formData['label']); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Shown to the customer in the cart when this rule applies.', 'power-discount'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="pd-config"><?php esc_html_e('Config (JSON)', 'power-discount'); ?></label></th>
                <td>
                    <textarea id="pd-config" name="config_json" rows="8" class="large-text code"><?php echo esc_textarea((string) $formData['config_json']); ?></textarea>
                    <p class="description"><?php esc_html_e('Strategy-specific settings as JSON. See documentation for each type.', 'power-discount'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="pd-filters"><?php esc_html_e('Product filters (JSON)', 'power-discount'); ?></label></th>
                <td>
                    <textarea id="pd-filters" name="filters_json" rows="6" class="large-text code"><?php echo esc_textarea((string) $formData['filters_json']); ?></textarea>
                    <p class="description"><?php echo wp_kses_post(__('Example: <code>{"items":[{"type":"categories","method":"in","ids":[12]}]}</code>', 'power-discount')); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="pd-conditions"><?php esc_html_e('Conditions (JSON)', 'power-discount'); ?></label></th>
                <td>
                    <textarea id="pd-conditions" name="conditions_json" rows="6" class="large-text code"><?php echo esc_textarea((string) $formData['conditions_json']); ?></textarea>
                    <p class="description"><?php echo wp_kses_post(__('Example: <code>{"logic":"and","items":[{"type":"cart_subtotal","operator":"&gt;=","value":1000}]}</code>', 'power-discount')); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="pd-notes"><?php esc_html_e('Internal notes', 'power-discount'); ?></label></th>
                <td>
                    <textarea id="pd-notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea((string) $formData['notes']); ?></textarea>
                </td>
            </tr>
        </table>

        <?php submit_button($isNew ? __('Create rule', 'power-discount') : __('Save rule', 'power-discount')); ?>
    </form>
</div>
