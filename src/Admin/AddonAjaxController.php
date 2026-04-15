<?php
declare(strict_types=1);

namespace PowerDiscount\Admin;

use PowerDiscount\Domain\AddonRule;
use PowerDiscount\Repository\AddonRuleRepository;

final class AddonAjaxController
{
    private AddonRuleRepository $rules;

    public function __construct(AddonRuleRepository $rules)
    {
        $this->rules = $rules;
    }

    public function register(): void
    {
        add_action('wp_ajax_pd_toggle_addon_rule_status', [$this, 'toggleStatus']);
        add_action('wp_ajax_pd_reorder_addon_rules', [$this, 'reorder']);
        // pd_toggle_addon_metabox_rule is wired by Task D1 on this same class.
    }

    public function toggleStatus(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }
        check_ajax_referer('power_discount_admin', 'nonce');

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $rule = $this->rules->findById($id);
        if ($rule === null) {
            wp_send_json_error(['message' => 'Rule not found'], 404);
        }

        $newStatus = $rule->isEnabled() ? 0 : 1;

        // Reconstruct AddonRule with flipped status (immutable pattern)
        $itemsArr = array_map(static fn ($i) => $i->toArray(), $rule->getAddonItems());
        $updated = new AddonRule([
            'id'                     => $rule->getId(),
            'title'                  => $rule->getTitle(),
            'status'                 => $newStatus,
            'priority'               => $rule->getPriority(),
            'addon_items'            => $itemsArr,
            'target_product_ids'     => $rule->getTargetProductIds(),
            'exclude_from_discounts' => $rule->isExcludeFromDiscounts(),
        ]);
        $this->rules->update($updated);

        wp_send_json_success(['status' => $newStatus]);
    }

    public function reorder(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }
        check_ajax_referer('power_discount_admin', 'nonce');

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];
        $ordered = array_values(array_filter(
            array_map('intval', $ids),
            static function (int $id): bool { return $id > 0; }
        ));
        if ($ordered === []) {
            wp_send_json_error(['message' => 'No ids provided'], 400);
        }

        $this->rules->reorder($ordered);
        wp_send_json_success(['count' => count($ordered)]);
    }
}
