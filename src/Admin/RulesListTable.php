<?php
declare(strict_types=1);

namespace PowerDiscount\Admin;

use PowerDiscount\Domain\Rule;
use PowerDiscount\Repository\RuleRepository;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class RulesListTable extends \WP_List_Table
{
    private RuleRepository $rules;

    public function __construct(RuleRepository $rules)
    {
        parent::__construct([
            'singular' => 'rule',
            'plural'   => 'rules',
            'ajax'     => false,
        ]);
        $this->rules = $rules;
    }

    public function get_columns(): array
    {
        return [
            'title'      => __('Title', 'power-discount'),
            'type'       => __('Type', 'power-discount'),
            'status'     => __('Status', 'power-discount'),
            'priority'   => __('Priority', 'power-discount'),
            'schedule'   => __('Schedule', 'power-discount'),
            'used_count' => __('Used', 'power-discount'),
        ];
    }

    public function prepare_items(): void
    {
        $this->_column_headers = [$this->get_columns(), [], []];
        // Phase 4b: show ALL rules (no pagination, status filter), sorted by priority.
        $rules = $this->rules->getActiveRules(); // active only first
        $allRows = [];
        foreach ($rules as $rule) {
            $allRows[] = $this->ruleToRow($rule);
        }
        $this->items = $allRows;
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleToRow(Rule $rule): array
    {
        return [
            'id'         => $rule->getId(),
            'title'      => $rule->getTitle(),
            'type'       => $rule->getType(),
            'status'     => $rule->getStatus(),
            'priority'   => $rule->getPriority(),
            'starts_at'  => $rule->getStartsAt(),
            'ends_at'    => $rule->getEndsAt(),
            'used_count' => $rule->getUsedCount(),
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    public function column_title($item): string
    {
        $editUrl = add_query_arg([
            'page'   => 'power-discount',
            'action' => 'edit',
            'id'     => (int) $item['id'],
        ], admin_url('admin.php'));

        $deleteUrl = wp_nonce_url(add_query_arg([
            'action' => 'pd_delete_rule',
            'id'     => (int) $item['id'],
        ], admin_url('admin-post.php')), 'pd_delete_rule_' . $item['id']);

        $duplicateUrl = wp_nonce_url(add_query_arg([
            'action' => 'pd_duplicate_rule',
            'id'     => (int) $item['id'],
        ], admin_url('admin-post.php')), 'pd_duplicate_rule_' . $item['id']);

        $title = sprintf(
            '<strong><a href="%s">%s</a></strong>',
            esc_url($editUrl),
            esc_html((string) $item['title'])
        );
        $actions = sprintf(
            '<div class="row-actions"><span class="edit"><a href="%s">%s</a> | </span><span class="duplicate"><a href="%s">%s</a> | </span><span class="delete"><a href="%s" onclick="return confirm(\'%s\')">%s</a></span></div>',
            esc_url($editUrl),
            esc_html__('Edit', 'power-discount'),
            esc_url($duplicateUrl),
            esc_html__('Duplicate', 'power-discount'),
            esc_url($deleteUrl),
            esc_js(__('Delete this rule?', 'power-discount')),
            esc_html__('Delete', 'power-discount')
        );
        return $title . $actions;
    }

    /** @param array<string, mixed> $item */
    public function column_type($item): string
    {
        return esc_html((string) $item['type']);
    }

    /** @param array<string, mixed> $item */
    public function column_status($item): string
    {
        $enabled = (int) $item['status'] === 1;
        $label = $enabled
            ? '<span style="color:#46b450">' . esc_html__('Enabled', 'power-discount') . '</span>'
            : '<span style="color:#dc3232">' . esc_html__('Disabled', 'power-discount') . '</span>';
        $toggleNonce = wp_create_nonce('power_discount_admin');
        return sprintf(
            '%s &nbsp;<a href="#" class="pd-toggle-status" data-id="%d" data-nonce="%s">%s</a>',
            $label,
            (int) $item['id'],
            esc_attr($toggleNonce),
            esc_html__('Toggle', 'power-discount')
        );
    }

    /** @param array<string, mixed> $item */
    public function column_priority($item): string
    {
        return (string) (int) $item['priority'];
    }

    /** @param array<string, mixed> $item */
    public function column_schedule($item): string
    {
        $start = (string) ($item['starts_at'] ?? '');
        $end = (string) ($item['ends_at'] ?? '');
        if ($start === '' && $end === '') {
            return '<span style="color:#999">' . esc_html__('Always', 'power-discount') . '</span>';
        }
        return esc_html(($start ?: '...') . ' → ' . ($end ?: '...'));
    }

    /** @param array<string, mixed> $item */
    public function column_used_count($item): string
    {
        return (string) (int) $item['used_count'];
    }
}
