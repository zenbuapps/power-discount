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

    /**
     * Bilingual labels for each strategy type.
     *
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            'simple'             => __('商品折扣 · Simple', 'power-discount'),
            'bulk'               => __('數量階梯 · Bulk', 'power-discount'),
            'cart'               => __('整車折扣 · Cart', 'power-discount'),
            'set'                => __('任選 N 件 · Set', 'power-discount'),
            'buy_x_get_y'        => __('買 X 送 Y · Buy X Get Y', 'power-discount'),
            'nth_item'           => __('第 N 件 X 折 · Nth item', 'power-discount'),
            'cross_category'     => __('紅配綠 · Cross-category', 'power-discount'),
            'free_shipping'     => __('條件免運 · Free shipping', 'power-discount'),
            'gift_with_purchase' => __('滿額贈 · Gift with purchase', 'power-discount'),
        ];
    }

    public function get_columns(): array
    {
        return [
            'status'     => __('Status', 'power-discount'),
            'title'      => __('Title', 'power-discount'),
            'type'       => __('Type', 'power-discount'),
            'priority'   => __('Priority', 'power-discount') . ' <span class="pd-help-tip" title="' .
                esc_attr__('Lower number = higher priority. Rules run in priority ascending order.', 'power-discount') . '">?</span>',
            'schedule'   => __('Schedule', 'power-discount'),
            'used_count' => __('Used', 'power-discount'),
        ];
    }

    public function prepare_items(): void
    {
        $this->_column_headers = [$this->get_columns(), [], []];
        $rules = $this->rules->findAll();
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

    /** @param array<string, mixed> $item */
    public function column_status($item): string
    {
        $enabled = (int) $item['status'] === 1;
        $toggleNonce = wp_create_nonce('power_discount_admin');
        return sprintf(
            '<label class="pd-toggle-switch" title="%s">'
            . '<input type="checkbox" class="pd-toggle-status-input" data-id="%d" data-nonce="%s"%s>'
            . '<span class="pd-toggle-slider"></span>'
            . '</label>',
            esc_attr($enabled ? __('Click to disable', 'power-discount') : __('Click to enable', 'power-discount')),
            (int) $item['id'],
            esc_attr($toggleNonce),
            $enabled ? ' checked' : ''
        );
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
        $type = (string) $item['type'];
        $labels = self::typeLabels();
        $label = $labels[$type] ?? $type;
        return '<span class="pd-type-badge pd-type-' . esc_attr($type) . '">' . esc_html($label) . '</span>';
    }

    /** @param array<string, mixed> $item */
    public function column_priority($item): string
    {
        return '<span class="pd-priority-pill">' . (int) $item['priority'] . '</span>';
    }

    /** @param array<string, mixed> $item */
    public function column_schedule($item): string
    {
        $start = (string) ($item['starts_at'] ?? '');
        $end = (string) ($item['ends_at'] ?? '');
        if ($start === '' && $end === '') {
            return '<span class="pd-muted">' . esc_html__('Always', 'power-discount') . '</span>';
        }
        return '<span class="pd-schedule">' . esc_html(($start ?: '…') . ' → ' . ($end ?: '…')) . '</span>';
    }

    /** @param array<string, mixed> $item */
    public function column_used_count($item): string
    {
        $count = (int) $item['used_count'];
        if ($count === 0) {
            return '<span class="pd-muted">0</span>';
        }
        return '<strong>' . $count . '</strong>';
    }
}
