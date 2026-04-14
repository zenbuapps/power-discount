<?php
declare(strict_types=1);

namespace PowerDiscount\Admin;

use PowerDiscount\Domain\Rule;
use PowerDiscount\Domain\RuleStatus;
use PowerDiscount\Repository\RuleRepository;

final class AjaxController
{
    private RuleRepository $rules;

    public function __construct(RuleRepository $rules)
    {
        $this->rules = $rules;
    }

    public function register(): void
    {
        add_action('wp_ajax_pd_toggle_rule_status', [$this, 'toggleStatus']);
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

        $newStatus = $rule->isEnabled() ? RuleStatus::DISABLED : RuleStatus::ENABLED;

        $updated = new Rule([
            'id'          => $rule->getId(),
            'title'       => $rule->getTitle(),
            'type'        => $rule->getType(),
            'status'      => $newStatus,
            'priority'    => $rule->getPriority(),
            'exclusive'   => $rule->isExclusive(),
            'starts_at'   => $rule->getStartsAt(),
            'ends_at'     => $rule->getEndsAt(),
            'usage_limit' => $rule->getUsageLimit(),
            'used_count'  => $rule->getUsedCount(),
            'config'      => $rule->getConfig(),
            'filters'     => $rule->getFilters(),
            'conditions'  => $rule->getConditions(),
            'label'       => $rule->getLabel(),
            'notes'       => $rule->getNotes(),
        ]);
        $this->rules->update($updated);

        wp_send_json_success(['status' => $newStatus]);
    }
}
