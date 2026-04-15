<?php
declare(strict_types=1);

namespace PowerDiscount\Admin;

use PowerDiscount\Repository\AddonRuleRepository;

final class AddonRulesListPage
{
    private AddonRuleRepository $rules;

    public function __construct(AddonRuleRepository $rules)
    {
        $this->rules = $rules;
    }

    public function render(): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('加價購規則', 'power-discount') . '</h1><p>' . esc_html__('規則清單將在 Phase B2 實作。', 'power-discount') . '</p></div>';
    }
}
