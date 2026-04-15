<?php
declare(strict_types=1);

namespace PowerDiscount\Admin;

use PowerDiscount\Repository\RuleRepository;

final class AdminMenu
{
    private RuleRepository $rules;
    private RulesListPage $listPage;
    private RuleEditPage $editPage;
    private ReportsPage $reportsPage;
    private AddonMenu $addonMenu;

    public function __construct(RuleRepository $rules, RulesListPage $listPage, RuleEditPage $editPage, ReportsPage $reportsPage, AddonMenu $addonMenu)
    {
        $this->rules = $rules;
        $this->listPage = $listPage;
        $this->editPage = $editPage;
        $this->reportsPage = $reportsPage;
        $this->addonMenu = $addonMenu;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_pd_save_rule', [$this->editPage, 'handleSave']);
        add_action('admin_post_pd_delete_rule', [$this->listPage, 'handleDelete']);
        add_action('admin_post_pd_duplicate_rule', [$this->listPage, 'handleDuplicate']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('PowerDiscount', 'power-discount'),
            __('PowerDiscount', 'power-discount'),
            'manage_woocommerce',
            'power-discount',
            [$this, 'route'],
            'dashicons-tag',
            55.6
        );
        // First submenu auto-duplicates the parent; rename it.
        add_submenu_page(
            'power-discount',
            __('Discount Rules', 'power-discount'),
            __('Discount Rules', 'power-discount'),
            'manage_woocommerce',
            'power-discount',
            [$this, 'route']
        );
        add_submenu_page(
            'power-discount',
            __('Reports', 'power-discount'),
            __('Reports', 'power-discount'),
            'manage_woocommerce',
            'power-discount-reports',
            [$this->reportsPage, 'render']
        );
        add_submenu_page(
            'power-discount',
            __('加價購', 'power-discount'),
            __('加價購', 'power-discount'),
            'manage_woocommerce',
            'power-discount-addons',
            [$this->addonMenu, 'route']
        );
    }

    public function route(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'power-discount'));
        }

        $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
        if ($action === 'edit' || $action === 'new') {
            $this->editPage->render();
            return;
        }
        $this->listPage->render();
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        if (strpos($hookSuffix, 'power-discount') === false) {
            return;
        }

        // WC-enhanced-select for category/product pickers on the edit page
        wp_enqueue_script('wc-enhanced-select');
        wp_enqueue_style('woocommerce_admin_styles');
        wp_enqueue_style('select2');

        wp_enqueue_style(
            'power-discount-admin',
            POWER_DISCOUNT_URL . 'assets/admin/admin.css',
            [],
            POWER_DISCOUNT_VERSION
        );
        wp_enqueue_style('dashicons');
        wp_enqueue_script(
            'power-discount-admin',
            POWER_DISCOUNT_URL . 'assets/admin/admin.js',
            ['jquery', 'jquery-ui-sortable', 'wc-enhanced-select'],
            POWER_DISCOUNT_VERSION,
            true
        );
        wp_localize_script('power-discount-admin', 'PowerDiscountAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('power_discount_admin'),
            'i18n'    => [
                // Filter type labels
                'allProducts'      => __('All products', 'power-discount'),
                'specificProducts' => __('Specific products', 'power-discount'),
                'categories'       => __('Categories', 'power-discount'),
                'tags'             => __('Tags', 'power-discount'),
                'attributes'       => __('Attributes', 'power-discount'),
                'onSale'           => __('On sale', 'power-discount'),
                'inList'           => __('in list', 'power-discount'),
                'notInList'        => __('not in list', 'power-discount'),
                'searchProducts'   => __('Search products', 'power-discount'),
                'selectCategories' => __('Select categories', 'power-discount'),
                'selectTags'       => __('Select tags', 'power-discount'),
                // Condition type labels
                'cartSubtotal'         => __('Cart subtotal', 'power-discount'),
                'cartQuantity'         => __('Cart total quantity', 'power-discount'),
                'cartLineItems'        => __('Number of line items', 'power-discount'),
                'totalSpent'           => __('Customer total spent (lifetime)', 'power-discount'),
                'userRole'             => __('User role', 'power-discount'),
                'userLoggedIn'         => __('User logged in', 'power-discount'),
                'paymentMethod'        => __('Payment method', 'power-discount'),
                'shippingMethod'       => __('Shipping method', 'power-discount'),
                'dateRange'            => __('Date range', 'power-discount'),
                'dayOfWeek'            => __('Day of week', 'power-discount'),
                'timeOfDay'            => __('Time of day', 'power-discount'),
                'firstOrder'           => __('First order', 'power-discount'),
                'birthdayMonth'        => __('Birthday month', 'power-discount'),
                'requireLoggedIn'      => __('Require logged in', 'power-discount'),
                'firstOrderOnly'       => __('Customer first order only', 'power-discount'),
                'matchCurrentMonth'    => __('Match current month', 'power-discount'),
                // Day of week (short)
                'dayMon' => __('Mon', 'power-discount'),
                'dayTue' => __('Tue', 'power-discount'),
                'dayWed' => __('Wed', 'power-discount'),
                'dayThu' => __('Thu', 'power-discount'),
                'dayFri' => __('Fri', 'power-discount'),
                'daySat' => __('Sat', 'power-discount'),
                'daySun' => __('Sun', 'power-discount'),
                // Cross-category group fields
                'groupName'      => __('Group name', 'power-discount'),
                'minQty'         => __('Min qty', 'power-discount'),
                'removeGroup'    => __('Remove group', 'power-discount'),
                'groupNameTops'  => __('e.g. Tops', 'power-discount'),
            ],
        ]);
    }
}
