<?php
declare(strict_types=1);

namespace PowerDiscount\Admin;

use InvalidArgumentException;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Repository\RuleRepository;

final class RuleEditPage
{
    private RuleRepository $rules;

    public function __construct(RuleRepository $rules)
    {
        $this->rules = $rules;
    }

    public function render(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied.', 'power-discount'));
        }
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $rule = $id > 0 ? $this->rules->findById($id) : null;

        if ($rule === null) {
            // New rule scaffold with sensible defaults.
            $rule = new Rule([
                'title'    => '',
                'type'     => 'simple',
                'status'   => 1,
                'priority' => 10,
                'config'   => ['method' => 'percentage', 'value' => 10],
            ]);
        }

        $formData = RuleFormMapper::toFormData($rule);
        $isNew = $rule->getId() === 0;
        $strategyTypes = [
            'simple'         => __('Simple (per-product)', 'power-discount'),
            'bulk'           => __('Bulk (quantity tiers)', 'power-discount'),
            'cart'           => __('Cart (whole-cart discount)', 'power-discount'),
            'set'            => __('Set (任選 N 件)', 'power-discount'),
            'buy_x_get_y'    => __('Buy X Get Y', 'power-discount'),
            'nth_item'       => __('Nth item (第 N 件 X 折)', 'power-discount'),
            'cross_category' => __('Cross-category (紅配綠)', 'power-discount'),
            'free_shipping'  => __('Free Shipping', 'power-discount'),
        ];

        require POWER_DISCOUNT_DIR . 'src/Admin/views/rule-edit.php';
    }

    public function handleSave(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied.', 'power-discount'));
        }
        $postedId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        check_admin_referer('pd_save_rule_' . $postedId);

        $post = wp_unslash($_POST);
        if (!is_array($post)) {
            $post = [];
        }

        try {
            $rule = RuleFormMapper::fromFormData($post);
        } catch (InvalidArgumentException $e) {
            Notices::add($e->getMessage(), 'error');
            $redirectId = (int) ($post['id'] ?? 0);
            $redirectAction = $redirectId > 0 ? 'edit' : 'new';
            $args = ['page' => 'power-discount', 'action' => $redirectAction];
            if ($redirectId > 0) {
                $args['id'] = $redirectId;
            }
            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        }

        if ($rule->getId() > 0) {
            $this->rules->update($rule);
            Notices::add(__('Rule updated.', 'power-discount'), 'success');
        } else {
            $newId = $this->rules->insert($rule);
            Notices::add(__('Rule created.', 'power-discount'), 'success');
            wp_safe_redirect(add_query_arg([
                'page'   => 'power-discount',
                'action' => 'edit',
                'id'     => $newId,
            ], admin_url('admin.php')));
            exit;
        }

        wp_safe_redirect(add_query_arg([
            'page'   => 'power-discount',
            'action' => 'edit',
            'id'     => $rule->getId(),
        ], admin_url('admin.php')));
        exit;
    }
}
