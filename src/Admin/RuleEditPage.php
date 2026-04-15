<?php
declare(strict_types=1);

namespace PowerDiscount\Admin;

use InvalidArgumentException;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Repository\RuleRepository;

final class RuleEditPage
{
    private RuleRepository $rules;
    private ?Rule $pendingRule = null;
    private string $pendingError = '';

    public function __construct(RuleRepository $rules)
    {
        $this->rules = $rules;
    }

    public function render(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied.', 'power-discount'));
        }

        $rule = $this->pendingRule;
        if ($rule === null) {
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $rule = $id > 0 ? $this->rules->findById($id) : null;
        }

        if ($rule === null) {
            $rule = new Rule([
                'title'    => '',
                'type'     => 'simple',
                'status'   => 1,
                'priority' => 10,
                'config'   => ['method' => 'percentage', 'value' => 10],
            ]);
        }

        $pendingError = $this->pendingError;

        $isNew = $rule->getId() === 0;
        $strategyTypes = [
            'simple' => [
                'label'       => __('Simple — 商品折扣', 'power-discount'),
                'description' => __('對符合篩選條件的每件商品套用一個固定折扣（百分比、扣固定金額、或設定固定售價）。最常用，例：全站 9 折、指定商品折 $50、本商品固定賣 $299。', 'power-discount'),
            ],
            'bulk' => [
                'label'       => __('Bulk — 數量階梯折扣', 'power-discount'),
                'description' => __('依購買數量決定折扣級別。例：1–4 件原價、5–9 件 9 折、10 件以上 8 折。可指定算總量或逐項計算。', 'power-discount'),
            ],
            'cart' => [
                'label'       => __('Cart — 整車折扣', 'power-discount'),
                'description' => __('整張購物車達到條件後，從 cart total 扣固定金額或百分比。例：滿千折百、整單 9 折。', 'power-discount'),
            ],
            'set' => [
                'label'       => __('Set — 任選 N 件組合', 'power-discount'),
                'description' => __('從符合條件的商品中任選 N 件，套用組合價、組合折扣或現折固定金額。例：任選 2 件 $90、任選 3 件 9 折、任選 4 件現折 $100。', 'power-discount'),
            ],
            'buy_x_get_y' => [
                'label'       => __('Buy X Get Y — 買 X 送 Y', 'power-discount'),
                'description' => __('購買 X 件就送 Y 件。贈品可以是同樣的商品、購物車中最便宜的商品、或指定的商品清單。可開啟循環模式重複套用。', 'power-discount'),
            ],
            'nth_item' => [
                'label'       => __('Nth item — 第 N 件 X 折', 'power-discount'),
                'description' => __('依購物車內商品的順序，第 N 件套用對應折扣。例：第二件 6 折、第三件半價、第四件免費。可循環。', 'power-discount'),
            ],
            'cross_category' => [
                'label'       => __('Cross-category — 紅配綠（跨類組合）', 'power-discount'),
                'description' => __('要求顧客同時購買多個分類的商品才能享折扣。例：上衣一件 + 褲子一件，整組 8 折。可形成多組重複套用。', 'power-discount'),
            ],
            'free_shipping' => [
                'label'       => __('Free Shipping — 條件免運', 'power-discount'),
                'description' => __('條件達成後，免除全部運費或運費打折。例：滿 $1000 免運、特定運送方式運費半價。', 'power-discount'),
            ],
            'gift_with_purchase' => [
                'label'       => __('Gift with purchase — 滿額贈', 'power-discount'),
                'description' => __('購物車金額達到門檻就送指定商品。顧客需自行把贈品加入購物車，系統會自動把它的價格折抵為 0。例：滿 $1000 送馬克杯。', 'power-discount'),
            ],
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
            // Re-render the form with the user's input preserved + an inline error.
            $this->pendingRule = RuleFormMapper::fromFormDataLoose($post);
            $this->pendingError = $e->getMessage();
            $this->render();
            return;
        }

        if ($rule->getId() > 0) {
            $this->rules->update($rule);
            Notices::add(__('Rule updated.', 'power-discount'), 'success');
        } else {
            // New rules go to the bottom of the list. Priority is managed via drag & drop.
            $rule = self::withPriority($rule, $this->rules->getMaxPriority() + 1);
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

    private static function withPriority(Rule $rule, int $priority): Rule
    {
        return new Rule([
            'id'            => $rule->getId(),
            'title'         => $rule->getTitle(),
            'type'          => $rule->getType(),
            'status'        => $rule->getStatus(),
            'priority'      => $priority,
            'exclusive'     => $rule->isExclusive(),
            'starts_at'     => $rule->getStartsAt(),
            'ends_at'       => $rule->getEndsAt(),
            'usage_limit'   => $rule->getUsageLimit(),
            'used_count'    => $rule->getUsedCount(),
            'filters'       => $rule->getFilters(),
            'conditions'    => $rule->getConditions(),
            'config'        => $rule->getConfig(),
            'label'         => $rule->getLabel(),
            'notes'         => $rule->getNotes(),
            'schedule_meta' => $rule->getScheduleMeta(),
        ]);
    }
}
