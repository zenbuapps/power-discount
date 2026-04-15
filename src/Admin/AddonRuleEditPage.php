<?php
declare(strict_types=1);

namespace PowerDiscount\Admin;

use PowerDiscount\Repository\AddonRuleRepository;

final class AddonRuleEditPage
{
    private AddonRuleRepository $rules;

    public function __construct(AddonRuleRepository $rules)
    {
        $this->rules = $rules;
    }

    public function render(): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('加價購規則 — 編輯', 'power-discount') . '</h1><p>' . esc_html__('編輯器將在 Phase C 實作。', 'power-discount') . '</p></div>';
    }
}
