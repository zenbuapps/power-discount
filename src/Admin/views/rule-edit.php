<?php
/**
 * @var \PowerDiscount\Domain\Rule $rule
 * @var bool $isNew
 * @var array<string, string> $strategyTypes
 */
if (!defined('ABSPATH')) {
    exit;
}

$pageTitle = $isNew ? __('Add Rule', 'power-discount') : __('Edit Rule', 'power-discount');
$listUrl = admin_url('admin.php?page=power-discount');
$currentType = $rule->getType() ?: 'simple';
$partialsDir = POWER_DISCOUNT_DIR . 'src/Admin/views/partials/';
?>
<div class="wrap pd-rule-editor">
    <h1 class="wp-heading-inline"><?php echo esc_html($pageTitle); ?></h1>
    <a href="<?php echo esc_url($listUrl); ?>" class="page-title-action">← <?php esc_html_e('Back to list', 'power-discount'); ?></a>
    <hr class="wp-header-end">

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="pd-rule-form">
        <input type="hidden" name="action" value="pd_save_rule">
        <input type="hidden" name="id" value="<?php echo (int) $rule->getId(); ?>">
        <?php wp_nonce_field('pd_save_rule_' . (int) $rule->getId()); ?>

        <div class="pd-section">
            <h2 class="pd-section-title"><?php esc_html_e('1. Basic details', 'power-discount'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="pd-title"><?php esc_html_e('Rule name', 'power-discount'); ?> <span class="pd-required">*</span></label></th>
                    <td><input type="text" id="pd-title" name="title" value="<?php echo esc_attr($rule->getTitle()); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="pd-type"><?php esc_html_e('Discount type', 'power-discount'); ?></label></th>
                    <td>
                        <select id="pd-type" name="type">
                            <?php foreach ($strategyTypes as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>"<?php selected($currentType, $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="pd-status"><?php esc_html_e('Status', 'power-discount'); ?></label></th>
                    <td>
                        <select id="pd-status" name="status">
                            <option value="1"<?php selected($rule->getStatus(), 1); ?>><?php esc_html_e('Enabled', 'power-discount'); ?></option>
                            <option value="0"<?php selected($rule->getStatus(), 0); ?>><?php esc_html_e('Disabled', 'power-discount'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="pd-priority"><?php esc_html_e('Priority', 'power-discount'); ?></label></th>
                    <td>
                        <input type="number" id="pd-priority" name="priority" value="<?php echo (int) $rule->getPriority(); ?>" min="0" class="small-text">
                        <p class="description"><?php esc_html_e('Lower number = higher priority.', 'power-discount'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e('Exclusive', 'power-discount'); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="exclusive" value="1"<?php checked($rule->isExclusive(), true); ?>> <?php esc_html_e('Stop after this rule matches', 'power-discount'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Schedule', 'power-discount'); ?></th>
                    <td>
                        <input type="text" name="starts_at" value="<?php echo esc_attr((string) $rule->getStartsAt()); ?>" placeholder="YYYY-MM-DD HH:MM:SS" class="regular-text">
                        <?php esc_html_e('to', 'power-discount'); ?>
                        <input type="text" name="ends_at" value="<?php echo esc_attr((string) $rule->getEndsAt()); ?>" placeholder="YYYY-MM-DD HH:MM:SS" class="regular-text">
                        <p class="description"><?php esc_html_e('Leave blank for no schedule limit.', 'power-discount'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="pd-usage"><?php esc_html_e('Usage limit', 'power-discount'); ?></label></th>
                    <td>
                        <input type="number" id="pd-usage" name="usage_limit" value="<?php echo $rule->getUsageLimit() === null ? '' : (int) $rule->getUsageLimit(); ?>" class="small-text" min="0">
                        <span class="description"><?php printf(esc_html__('Used: %d', 'power-discount'), (int) $rule->getUsedCount()); ?></span>
                    </td>
                </tr>
                <tr>
                    <th><label for="pd-label"><?php esc_html_e('Cart label', 'power-discount'); ?></label></th>
                    <td><input type="text" id="pd-label" name="label" value="<?php echo esc_attr((string) $rule->getLabel()); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Shown to customers in the cart when this rule applies.', 'power-discount'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="pd-section">
            <h2 class="pd-section-title"><?php esc_html_e('2. Discount settings', 'power-discount'); ?></h2>
            <div id="pd-strategy-sections">
                <?php foreach (array_keys($strategyTypes) as $type): ?>
                    <div class="pd-strategy-section" data-type="<?php echo esc_attr($type); ?>"<?php echo $type === $currentType ? '' : ' style="display:none"'; ?>>
                        <?php
                        $config = $type === $currentType ? $rule->getConfig() : [];
                        $partial = $partialsDir . 'strategy-' . $type . '.php';
                        if (file_exists($partial)) {
                            include $partial;
                        }
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pd-section">
            <h2 class="pd-section-title"><?php esc_html_e('3. Product filters', 'power-discount'); ?></h2>
            <p class="description"><?php esc_html_e('Which products in the cart should this rule apply to? Leave empty to apply to all products.', 'power-discount'); ?></p>
            <?php
            $filters = $rule->getFilters();
            $filterItems = is_array($filters['items'] ?? null) ? $filters['items'] : [];
            include $partialsDir . 'filter-builder.php';
            ?>
        </div>

        <div class="pd-section">
            <h2 class="pd-section-title"><?php esc_html_e('4. Conditions', 'power-discount'); ?></h2>
            <p class="description"><?php esc_html_e('When should this rule apply? Leave empty to apply always.', 'power-discount'); ?></p>
            <?php
            $conditions = $rule->getConditions();
            $conditionLogic = (string) ($conditions['logic'] ?? 'and');
            $conditionItems = is_array($conditions['items'] ?? null) ? $conditions['items'] : [];
            include $partialsDir . 'condition-builder.php';
            ?>
        </div>

        <?php submit_button($isNew ? __('Create rule', 'power-discount') : __('Save rule', 'power-discount')); ?>
    </form>
</div>
