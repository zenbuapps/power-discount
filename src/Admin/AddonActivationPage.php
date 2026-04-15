<?php
declare(strict_types=1);

namespace PowerDiscount\Admin;

final class AddonActivationPage
{
    public function render(): void
    {
        $activateUrl = wp_nonce_url(
            add_query_arg(['action' => 'pd_activate_addons'], admin_url('admin-post.php')),
            'pd_activate_addons'
        );
        require POWER_DISCOUNT_DIR . 'src/Admin/views/addon-activation.php';
    }

    public function handleActivate(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied.', 'power-discount'));
        }
        check_admin_referer('pd_activate_addons');
        update_option(AddonMenu::OPTION_ENABLED, true, false);
        Notices::add(__('加價購功能已啟用。', 'power-discount'), 'success');
        wp_safe_redirect(add_query_arg(['page' => 'power-discount-addons'], admin_url('admin.php')));
        exit;
    }

    public function handleDeactivate(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied.', 'power-discount'));
        }
        check_admin_referer('pd_deactivate_addons');
        update_option(AddonMenu::OPTION_ENABLED, false, false);
        Notices::add(__('加價購功能已停用。既有規則未刪除，再次啟用即可繼續使用。', 'power-discount'), 'info');
        wp_safe_redirect(add_query_arg(['page' => 'power-discount-addons'], admin_url('admin.php')));
        exit;
    }
}
