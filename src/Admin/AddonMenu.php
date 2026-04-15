<?php
declare(strict_types=1);

namespace PowerDiscount\Admin;

use PowerDiscount\Repository\AddonRuleRepository;

final class AddonMenu
{
    public const OPTION_ENABLED = 'power_discount_addon_enabled';

    private AddonRuleRepository $rules;
    private AddonActivationPage $activationPage;
    private AddonRulesListPage $listPage;
    private AddonRuleEditPage $editPage;

    public function __construct(
        AddonRuleRepository $rules,
        AddonActivationPage $activationPage,
        AddonRulesListPage $listPage,
        AddonRuleEditPage $editPage
    ) {
        $this->rules = $rules;
        $this->activationPage = $activationPage;
        $this->listPage = $listPage;
        $this->editPage = $editPage;
    }

    public function register(): void
    {
        add_action('admin_post_pd_activate_addons', [$this->activationPage, 'handleActivate']);
        add_action('admin_post_pd_deactivate_addons', [$this->activationPage, 'handleDeactivate']);
        // B2 and C2 will wire up pd_save_addon_rule and pd_delete_addon_rule here
    }

    public static function isEnabled(): bool
    {
        return (bool) get_option(self::OPTION_ENABLED, false);
    }

    public function route(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'power-discount'));
        }
        if (!self::isEnabled()) {
            $this->activationPage->render();
            return;
        }
        $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
        if ($action === 'edit' || $action === 'new') {
            $this->editPage->render();
            return;
        }
        $this->listPage->render();
    }
}
