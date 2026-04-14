<?php
declare(strict_types=1);

namespace PowerDiscount\Admin;

use PowerDiscount\Repository\RuleRepository;

final class RulesListPage
{
    private RuleRepository $rules;

    public function __construct(RuleRepository $rules)
    {
        $this->rules = $rules;
    }

    public function render(): void
    {
        $table = new RulesListTable($this->rules);
        $table->prepare_items();

        $newUrl = add_query_arg([
            'page'   => 'power-discount',
            'action' => 'new',
        ], admin_url('admin.php'));

        require POWER_DISCOUNT_DIR . 'src/Admin/views/rule-list.php';
    }

    public function handleDelete(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied.', 'power-discount'));
        }
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        check_admin_referer('pd_delete_rule_' . $id);

        if ($id > 0) {
            $this->rules->delete($id);
            Notices::add(__('Rule deleted.', 'power-discount'), 'success');
        }

        wp_safe_redirect(admin_url('admin.php?page=power-discount'));
        exit;
    }

    public function handleDuplicate(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied.', 'power-discount'));
        }
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        check_admin_referer('pd_duplicate_rule_' . $id);

        $original = $this->rules->findById($id);
        if ($original === null) {
            wp_safe_redirect(admin_url('admin.php?page=power-discount'));
            exit;
        }

        $copy = new \PowerDiscount\Domain\Rule([
            'title'       => $original->getTitle() . ' (copy)',
            'type'        => $original->getType(),
            'status'      => 0, // duplicates start disabled
            'priority'    => $original->getPriority(),
            'exclusive'   => $original->isExclusive(),
            'starts_at'   => $original->getStartsAt(),
            'ends_at'     => $original->getEndsAt(),
            'usage_limit' => $original->getUsageLimit(),
            'config'      => $original->getConfig(),
            'filters'     => $original->getFilters(),
            'conditions'  => $original->getConditions(),
            'label'       => $original->getLabel(),
            'notes'       => $original->getNotes(),
        ]);

        $newId = $this->rules->insert($copy);
        Notices::add(__('Rule duplicated.', 'power-discount'), 'success');

        wp_safe_redirect(add_query_arg([
            'page'   => 'power-discount',
            'action' => 'edit',
            'id'     => $newId,
        ], admin_url('admin.php')));
        exit;
    }
}
