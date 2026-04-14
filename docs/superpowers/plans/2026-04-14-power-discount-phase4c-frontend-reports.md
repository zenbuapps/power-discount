# Power Discount — Phase 4c: Frontend Components + Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 補上 MVP 的最後一哩 — 顧客面的免運進度條與商品階梯短碼，以及後台的折扣命中報表。完成後 `power-discount` 是一個從規則設定、購物車計算、結帳記錄到報表分析都齊全的端到端 WooCommerce 折扣外掛。

**Architecture:** 同前；新增 `Frontend/` 命名空間放顧客面 HTML 元件、`Repository/ReportsRepository` 做 PHP-side 聚合（不依賴 SQL GROUP BY，便於用 InMemoryDatabaseAdapter 測試）、`Admin/ReportsPage` 提供後台報表頁。Pure helper（`FreeShippingProgressHelper`）與 `ReportsRepository` 走 TDD；HTML 渲染 / WC hook 整合層只 `php -l` 與手動驗證。

**Tech Stack:** PHP 7.4+、PHPUnit 9.6、WooCommerce 7.0+

**Phase 定位:**
- Phase 1 ✅ Foundation
- Phase 2 ✅ Repository + Engine
- Phase 3 ✅ Taiwan Strategies
- Phase 4a ✅ Conditions + Filters + ShippingHooks
- Phase 4b ✅ PHP Admin UI
- **Phase 4c（本文）** Frontend + Reports
- Phase 4d (optional) React rule builder

---

## File Structure

新增：

```
src/Frontend/
├── FreeShippingProgressHelper.php   # pure, testable
├── FreeShippingBar.php              # WC hook integration, no unit test
└── PriceTableShortcode.php          # [power_discount_table id=N], no unit test

src/Repository/
└── ReportsRepository.php            # PHP-side aggregation, testable

src/Admin/
├── ReportsPage.php                  # admin sub-page controller
└── views/
    └── reports.php                  # report view template

assets/frontend/
└── frontend.css                     # minimal styling for shipping bar + price table

tests/Unit/
├── Frontend/
│   └── FreeShippingProgressHelperTest.php
└── Repository/
    └── ReportsRepositoryTest.php
```

修改：
- `src/Plugin.php` — boot 階段註冊 `FreeShippingBar`、`PriceTableShortcode`，並在 `is_admin()` 區塊註冊 `ReportsPage`
- `src/Admin/AdminMenu.php` — 新增 Reports 子選單路由

---

## Key Designs

### FreeShippingProgressHelper

純 PHP helper。給定 `CartContext` + `Rule[]`，回傳：

```php
class FreeShippingProgress {
    public bool $hasFreeShippingRule;   // 是否有任何 free_shipping rule
    public ?float $threshold;           // 最近且尚未達到的 cart_subtotal 門檻
    public ?float $remaining;           // threshold - subtotal，>0 表示還差
    public bool $achieved;              // 是否已達到任一 free_shipping rule 門檻
}
```

策略：

1. 過濾 `type='free_shipping'` 且 `isEnabled()` 的規則
2. 從每個規則的 `conditions.items[]` 找 `cart_subtotal` 條件且 operator 為 `>=` 或 `>`，取出 `value` 作為 threshold
3. 若任一 threshold ≤ subtotal，`achieved=true`；不再回傳 remaining
4. 否則找 threshold 最接近 subtotal 的（最小的尚未達到）
5. 若沒有任何 cart_subtotal 條件可解析（如以其他 condition 觸發），則回傳 `hasFreeShippingRule=true` 但 `threshold=null`

**啟發式**：只認 `cart_subtotal >= X` 形式。其他複合 condition（payment_method 等）的免運不能算進度條，UI 顯示「尚有免運優惠可用」字樣即可。

### ReportsRepository

不需 SQL GROUP BY。`getRuleStats()` 全表掃 `pd_order_discounts`，PHP 端 reduce 為：

```php
[
    [
        'rule_id' => 1,
        'rule_title' => 'Coffee 10%',
        'rule_type' => 'simple',
        'count' => 12,
        'total_amount' => 3450.00,
    ],
    ...
]
```

`getMonthlyTotals(int $months)` 同樣全表掃，PHP 端依 `created_at` 切割成 `YYYY-MM` bucket。

效能對 MVP 足夠（Phase 4d 可改為 SQL 聚合）。

---

## Ground Rules

- `<?php declare(strict_types=1);`
- PHP 7.4 相容
- TDD for `FreeShippingProgressHelper` 與 `ReportsRepository`
- 其他類別只 `php -l`
- Per-task commits
- `git -c user.email=luke@local -c user.name=Luke commit -m "..."`

---

## Tasks

### Task 1: FreeShippingProgressHelper (TDD)

**Files:**
- Create: `tests/Unit/Frontend/FreeShippingProgressHelperTest.php`
- Create: `src/Frontend/FreeShippingProgress.php` (value object)
- Create: `src/Frontend/FreeShippingProgressHelper.php`

#### Step 1: Test

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Frontend\FreeShippingProgressHelper;

final class FreeShippingProgressHelperTest extends TestCase
{
    public function testNoFreeShippingRule(): void
    {
        $helper = new FreeShippingProgressHelper();
        $progress = $helper->compute(new CartContext([new CartItem(1, 'A', 100.0, 1, [])]), []);

        self::assertFalse($progress->hasFreeShippingRule);
        self::assertFalse($progress->achieved);
        self::assertNull($progress->threshold);
        self::assertNull($progress->remaining);
    }

    public function testThresholdNotYetReached(): void
    {
        $helper = new FreeShippingProgressHelper();
        $rule = $this->freeShippingRule(1000.0);
        $ctx = new CartContext([new CartItem(1, 'A', 300.0, 1, [])]);

        $progress = $helper->compute($ctx, [$rule]);

        self::assertTrue($progress->hasFreeShippingRule);
        self::assertFalse($progress->achieved);
        self::assertSame(1000.0, $progress->threshold);
        self::assertSame(700.0, $progress->remaining);
    }

    public function testThresholdAchievedExactly(): void
    {
        $helper = new FreeShippingProgressHelper();
        $rule = $this->freeShippingRule(1000.0);
        $ctx = new CartContext([new CartItem(1, 'A', 1000.0, 1, [])]);

        $progress = $helper->compute($ctx, [$rule]);

        self::assertTrue($progress->achieved);
        self::assertNull($progress->remaining);
    }

    public function testPicksLowestUnachievedThreshold(): void
    {
        $helper = new FreeShippingProgressHelper();
        $cheap = $this->freeShippingRule(500.0);
        $premium = $this->freeShippingRule(2000.0);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);

        $progress = $helper->compute($ctx, [$cheap, $premium]);

        self::assertSame(500.0, $progress->threshold);
        self::assertSame(400.0, $progress->remaining);
    }

    public function testIgnoresAlreadyAchievedRules(): void
    {
        $helper = new FreeShippingProgressHelper();
        $cheap = $this->freeShippingRule(500.0); // already achieved
        $premium = $this->freeShippingRule(2000.0); // not yet
        $ctx = new CartContext([new CartItem(1, 'A', 700.0, 1, [])]);

        $progress = $helper->compute($ctx, [$cheap, $premium]);

        // Achieved is true because at least one threshold is met
        self::assertTrue($progress->achieved);
    }

    public function testSkipsDisabledFreeShippingRule(): void
    {
        $helper = new FreeShippingProgressHelper();
        $disabled = new Rule([
            'title' => 'x', 'type' => 'free_shipping',
            'status' => 0,
            'conditions' => ['logic' => 'and', 'items' => [['type' => 'cart_subtotal', 'operator' => '>=', 'value' => 100]]],
        ]);
        $ctx = new CartContext([new CartItem(1, 'A', 50.0, 1, [])]);

        $progress = $helper->compute($ctx, [$disabled]);

        self::assertFalse($progress->hasFreeShippingRule);
    }

    public function testRuleWithoutCartSubtotalConditionStillCountsAsFreeShipping(): void
    {
        $helper = new FreeShippingProgressHelper();
        // Free shipping triggered by payment method, not subtotal
        $rule = new Rule([
            'title' => 'LinePay free ship', 'type' => 'free_shipping',
            'status' => 1,
            'conditions' => ['logic' => 'and', 'items' => [['type' => 'payment_method', 'methods' => ['linepay']]]],
        ]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);

        $progress = $helper->compute($ctx, [$rule]);

        self::assertTrue($progress->hasFreeShippingRule);
        self::assertNull($progress->threshold);
        self::assertNull($progress->remaining);
        self::assertFalse($progress->achieved);
    }

    public function testIgnoresNonFreeShippingRules(): void
    {
        $helper = new FreeShippingProgressHelper();
        $simple = new Rule([
            'title' => 'x', 'type' => 'simple',
            'conditions' => ['logic' => 'and', 'items' => [['type' => 'cart_subtotal', 'operator' => '>=', 'value' => 500]]],
        ]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);

        $progress = $helper->compute($ctx, [$simple]);

        self::assertFalse($progress->hasFreeShippingRule);
    }

    private function freeShippingRule(float $threshold): Rule
    {
        return new Rule([
            'title' => 'Free Ship',
            'type' => 'free_shipping',
            'status' => 1,
            'conditions' => [
                'logic' => 'and',
                'items' => [['type' => 'cart_subtotal', 'operator' => '>=', 'value' => $threshold]],
            ],
        ]);
    }
}
```

#### Step 2: Run → fail.

#### Step 3: `src/Frontend/FreeShippingProgress.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Frontend;

final class FreeShippingProgress
{
    public bool $hasFreeShippingRule;
    public bool $achieved;
    public ?float $threshold;
    public ?float $remaining;

    public function __construct(bool $hasFreeShippingRule, bool $achieved, ?float $threshold, ?float $remaining)
    {
        $this->hasFreeShippingRule = $hasFreeShippingRule;
        $this->achieved = $achieved;
        $this->threshold = $threshold;
        $this->remaining = $remaining;
    }
}
```

#### Step 4: `src/Frontend/FreeShippingProgressHelper.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Frontend;

use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\Rule;

final class FreeShippingProgressHelper
{
    /**
     * @param Rule[] $allRules
     */
    public function compute(CartContext $context, array $allRules): FreeShippingProgress
    {
        $shippingRules = array_filter(
            $allRules,
            static function (Rule $r): bool {
                return $r->getType() === 'free_shipping' && $r->isEnabled();
            }
        );

        if ($shippingRules === []) {
            return new FreeShippingProgress(false, false, null, null);
        }

        $subtotal = $context->getSubtotal();
        $achieved = false;
        $bestUnachieved = null;

        foreach ($shippingRules as $rule) {
            $threshold = $this->extractCartSubtotalThreshold($rule);
            if ($threshold === null) {
                // Rule has no cart_subtotal condition; treat as "available but no progress to display"
                continue;
            }
            if ($subtotal >= $threshold) {
                $achieved = true;
                continue;
            }
            if ($bestUnachieved === null || $threshold < $bestUnachieved) {
                $bestUnachieved = $threshold;
            }
        }

        if ($achieved) {
            return new FreeShippingProgress(true, true, null, null);
        }
        if ($bestUnachieved === null) {
            // Rules exist but none expressed via cart_subtotal
            return new FreeShippingProgress(true, false, null, null);
        }
        return new FreeShippingProgress(true, false, $bestUnachieved, $bestUnachieved - $subtotal);
    }

    private function extractCartSubtotalThreshold(Rule $rule): ?float
    {
        $conditions = $rule->getConditions();
        $items = $conditions['items'] ?? [];
        if (!is_array($items)) {
            return null;
        }
        foreach ($items as $item) {
            if (!is_array($item) || ($item['type'] ?? '') !== 'cart_subtotal') {
                continue;
            }
            $op = (string) ($item['operator'] ?? '');
            if (!in_array($op, ['>=', '>'], true)) {
                continue;
            }
            return (float) ($item['value'] ?? 0);
        }
        return null;
    }
}
```

#### Step 5: Re-run → expect 8 passes. Full suite: 250 tests (242 + 8).

#### Step 6: Commit

```bash
git add src/Frontend/FreeShippingProgress.php src/Frontend/FreeShippingProgressHelper.php tests/Unit/Frontend/FreeShippingProgressHelperTest.php
git commit -m "feat(frontend): add FreeShippingProgressHelper with TDD"
```

---

### Task 2: FreeShippingBar (WC integration)

**Files:**
- Create: `src/Frontend/FreeShippingBar.php`
- Create: `assets/frontend/frontend.css`

No unit test (WC runtime).

#### Step 1: `src/Frontend/FreeShippingBar.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Frontend;

use PowerDiscount\Integration\CartContextBuilder;
use PowerDiscount\Repository\RuleRepository;

final class FreeShippingBar
{
    private RuleRepository $rules;
    private CartContextBuilder $builder;
    private FreeShippingProgressHelper $helper;

    public function __construct(RuleRepository $rules, CartContextBuilder $builder, FreeShippingProgressHelper $helper)
    {
        $this->rules = $rules;
        $this->builder = $builder;
        $this->helper = $helper;
    }

    public function register(): void
    {
        add_action('woocommerce_before_cart', [$this, 'render']);
        add_action('woocommerce_before_checkout_form', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        if (function_exists('is_cart') && function_exists('is_checkout') && (is_cart() || is_checkout())) {
            wp_enqueue_style(
                'power-discount-frontend',
                POWER_DISCOUNT_URL . 'assets/frontend/frontend.css',
                [],
                POWER_DISCOUNT_VERSION
            );
        }
    }

    public function render(): void
    {
        if (!function_exists('WC') || WC()->cart === null) {
            return;
        }

        $context = $this->builder->fromWcCart(WC()->cart);
        $allRules = $this->rules->findAll();
        $progress = $this->helper->compute($context, $allRules);

        if (!$progress->hasFreeShippingRule) {
            return;
        }

        if ($progress->achieved) {
            $message = esc_html__('🎉 You qualify for free shipping!', 'power-discount');
            $percent = 100;
        } elseif ($progress->threshold !== null && $progress->remaining !== null) {
            $remainingFormatted = function_exists('wc_price')
                ? wp_strip_all_tags(wc_price($progress->remaining))
                : number_format($progress->remaining, 2);
            $message = sprintf(
                /* translators: %s = remaining amount */
                esc_html__('Add %s more to qualify for free shipping', 'power-discount'),
                esc_html($remainingFormatted)
            );
            $achieved = $progress->threshold - $progress->remaining;
            $percent = (int) max(0, min(100, ($achieved / $progress->threshold) * 100));
        } else {
            $message = esc_html__('Free shipping promotions available — see checkout for details.', 'power-discount');
            $percent = 0;
        }

        echo '<div class="pd-shipping-bar">';
        echo '<div class="pd-shipping-bar__message">' . $message . '</div>';
        if ($percent > 0) {
            echo '<div class="pd-shipping-bar__track"><div class="pd-shipping-bar__fill" style="width:' . (int) $percent . '%"></div></div>';
        }
        echo '</div>';
    }
}
```

#### Step 2: `assets/frontend/frontend.css`

```css
.pd-shipping-bar {
    background: #f0f7ff;
    border: 1px solid #cfe3ff;
    border-radius: 4px;
    padding: 12px 16px;
    margin: 0 0 16px;
    font-size: 14px;
}
.pd-shipping-bar__message {
    margin-bottom: 8px;
    color: #1d4f8a;
}
.pd-shipping-bar__track {
    background: #e2e8f0;
    border-radius: 999px;
    height: 8px;
    overflow: hidden;
}
.pd-shipping-bar__fill {
    background: #2563eb;
    height: 100%;
    transition: width 0.3s ease;
}
.pd-price-table {
    border-collapse: collapse;
    margin: 12px 0;
}
.pd-price-table th,
.pd-price-table td {
    border: 1px solid #e2e8f0;
    padding: 6px 12px;
    text-align: left;
}
.pd-price-table th {
    background: #f8fafc;
}
```

#### Step 3: `php -l src/Frontend/FreeShippingBar.php`. Full suite still 250.

#### Step 4: Commit

```bash
git add src/Frontend/FreeShippingBar.php assets/frontend/frontend.css
git commit -m "feat(frontend): add FreeShippingBar widget for cart and checkout"
```

---

### Task 3: PriceTableShortcode

**Files:**
- Create: `src/Frontend/PriceTableShortcode.php`

`[power_discount_table id=PRODUCT_ID]` — show the bulk discount tiers that apply to a product. Looks up active rules of type `bulk` whose filters match the product, parses their config ranges, renders a simple HTML table.

For Phase 4c, only handles `bulk` strategy (most common use case for price tables). Other strategy types are ignored.

#### Step 1: `src/Frontend/PriceTableShortcode.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Frontend;

use PowerDiscount\Domain\Rule;
use PowerDiscount\Repository\RuleRepository;

final class PriceTableShortcode
{
    private RuleRepository $rules;

    public function __construct(RuleRepository $rules)
    {
        $this->rules = $rules;
    }

    public function register(): void
    {
        add_shortcode('power_discount_table', [$this, 'render']);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function render($atts = []): string
    {
        if (!is_array($atts)) {
            $atts = [];
        }
        $atts = shortcode_atts(['id' => 0], $atts, 'power_discount_table');
        $productId = (int) $atts['id'];
        if ($productId <= 0) {
            return '';
        }

        $product = function_exists('wc_get_product') ? wc_get_product($productId) : null;
        if (!$product) {
            return '';
        }

        $allRules = $this->rules->getActiveRules();
        $bulkRules = $this->collectMatchingBulkRules($allRules, $productId, $product);
        if ($bulkRules === []) {
            return '';
        }

        ob_start();
        echo '<table class="pd-price-table">';
        echo '<thead><tr><th>' . esc_html__('Quantity', 'power-discount') . '</th><th>' . esc_html__('Discount', 'power-discount') . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($bulkRules as $rule) {
            $config = $rule->getConfig();
            $ranges = $config['ranges'] ?? [];
            if (!is_array($ranges)) {
                continue;
            }
            foreach ($ranges as $range) {
                $from = (int) ($range['from'] ?? 0);
                $to = isset($range['to']) && $range['to'] !== null ? (int) $range['to'] : null;
                $method = (string) ($range['method'] ?? 'percentage');
                $value = (float) ($range['value'] ?? 0);
                if ($value <= 0) {
                    continue;
                }

                $qtyLabel = $to === null
                    ? sprintf(__('%d+', 'power-discount'), $from)
                    : sprintf('%d – %d', $from, $to);
                $discountLabel = $method === 'percentage'
                    ? sprintf('%s %%', rtrim(rtrim(number_format($value, 2), '0'), '.'))
                    : (function_exists('wc_price') ? wp_strip_all_tags(wc_price($value)) : number_format($value, 2));

                echo '<tr><td>' . esc_html($qtyLabel) . '</td><td>' . esc_html($discountLabel) . '</td></tr>';
            }
        }
        echo '</tbody></table>';
        return (string) ob_get_clean();
    }

    /**
     * @param Rule[] $rules
     * @return Rule[]
     */
    private function collectMatchingBulkRules(array $rules, int $productId, $product): array
    {
        $matched = [];
        $categoryIds = method_exists($product, 'get_category_ids') ? (array) $product->get_category_ids() : [];
        $categoryIds = array_map('intval', $categoryIds);

        foreach ($rules as $rule) {
            if ($rule->getType() !== 'bulk') {
                continue;
            }
            $filters = $rule->getFilters();
            $items = $filters['items'] ?? [];
            if (!is_array($items) || $items === []) {
                $matched[] = $rule;
                continue;
            }
            foreach ($items as $filterItem) {
                if (!is_array($filterItem)) {
                    continue;
                }
                $type = (string) ($filterItem['type'] ?? '');
                if ($type === 'all_products') {
                    $matched[] = $rule;
                    break;
                }
                if ($type === 'products') {
                    $ids = array_map('intval', (array) ($filterItem['ids'] ?? []));
                    $method = (string) ($filterItem['method'] ?? 'in');
                    $hit = in_array($productId, $ids, true);
                    if (($method === 'in' && $hit) || ($method === 'not_in' && !$hit)) {
                        $matched[] = $rule;
                        break;
                    }
                }
                if ($type === 'categories') {
                    $ids = array_map('intval', (array) ($filterItem['ids'] ?? []));
                    $method = (string) ($filterItem['method'] ?? 'in');
                    $hit = false;
                    foreach ($categoryIds as $cat) {
                        if (in_array($cat, $ids, true)) {
                            $hit = true;
                            break;
                        }
                    }
                    if (($method === 'in' && $hit) || ($method === 'not_in' && !$hit)) {
                        $matched[] = $rule;
                        break;
                    }
                }
            }
        }
        return $matched;
    }
}
```

#### Step 2: `php -l`. Full suite still 250.

#### Step 3: Commit

```bash
git add src/Frontend/PriceTableShortcode.php
git commit -m "feat(frontend): add [power_discount_table] shortcode for bulk tier display"
```

---

### Task 4: ReportsRepository (TDD)

**Files:**
- Create: `tests/Unit/Repository/ReportsRepositoryTest.php`
- Create: `src/Repository/ReportsRepository.php`

#### Step 1: Test

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Repository\OrderDiscountRepository;
use PowerDiscount\Repository\ReportsRepository;
use PowerDiscount\Tests\Stub\InMemoryDatabaseAdapter;

final class ReportsRepositoryTest extends TestCase
{
    private InMemoryDatabaseAdapter $db;
    private OrderDiscountRepository $orderRepo;
    private ReportsRepository $reports;

    protected function setUp(): void
    {
        $this->db = new InMemoryDatabaseAdapter();
        $this->orderRepo = new OrderDiscountRepository($this->db);
        $this->reports = new ReportsRepository($this->db);
    }

    public function testEmptyStats(): void
    {
        self::assertSame([], $this->reports->getRuleStats());
    }

    public function testSingleRuleStats(): void
    {
        $this->orderRepo->record(101, [
            new DiscountResult(1, 'simple', 'product', 50.0, [10], null, []),
        ], [1 => 'Coffee 10%']);

        $stats = $this->reports->getRuleStats();
        self::assertCount(1, $stats);
        self::assertSame(1, $stats[0]['rule_id']);
        self::assertSame('Coffee 10%', $stats[0]['rule_title']);
        self::assertSame('simple', $stats[0]['rule_type']);
        self::assertSame(1, $stats[0]['count']);
        self::assertSame(50.0, $stats[0]['total_amount']);
    }

    public function testMultipleRulesAggregation(): void
    {
        $this->orderRepo->record(1, [
            new DiscountResult(1, 'simple', 'product', 100.0, [], null, []),
            new DiscountResult(2, 'cart', 'cart', 50.0, [], null, []),
        ], [1 => 'A', 2 => 'B']);
        $this->orderRepo->record(2, [
            new DiscountResult(1, 'simple', 'product', 200.0, [], null, []),
        ], [1 => 'A']);

        $stats = $this->reports->getRuleStats();
        // Sorted by total_amount DESC by default
        self::assertSame(1, $stats[0]['rule_id']);
        self::assertSame(2, $stats[0]['count']);
        self::assertSame(300.0, $stats[0]['total_amount']);

        self::assertSame(2, $stats[1]['rule_id']);
        self::assertSame(1, $stats[1]['count']);
        self::assertSame(50.0, $stats[1]['total_amount']);
    }

    public function testTotalDiscountSum(): void
    {
        $this->orderRepo->record(1, [
            new DiscountResult(1, 'simple', 'product', 100.0, [], null, []),
            new DiscountResult(2, 'cart', 'cart', 50.0, [], null, []),
        ], [1 => 'A', 2 => 'B']);

        self::assertSame(150.0, $this->reports->getTotalDiscount());
    }

    public function testTotalOrdersCount(): void
    {
        $this->orderRepo->record(1, [new DiscountResult(1, 'simple', 'product', 10.0, [], null, [])], [1 => 'A']);
        $this->orderRepo->record(2, [new DiscountResult(1, 'simple', 'product', 20.0, [], null, [])], [1 => 'A']);
        $this->orderRepo->record(3, [new DiscountResult(2, 'cart', 'cart', 30.0, [], null, [])], [2 => 'B']);

        // 3 distinct order_ids
        self::assertSame(3, $this->reports->getTotalOrdersAffected());
    }

    public function testStatsAccountForRuleTitleSnapshot(): void
    {
        // Rule 1 records as "Old Title" first, then "New Title" later. Both stay distinct snapshots
        // but should aggregate by rule_id, taking the most recent title.
        $this->orderRepo->record(1, [new DiscountResult(1, 'simple', 'product', 10.0, [], null, [])], [1 => 'Old Title']);
        $this->orderRepo->record(2, [new DiscountResult(1, 'simple', 'product', 20.0, [], null, [])], [1 => 'New Title']);

        $stats = $this->reports->getRuleStats();
        self::assertCount(1, $stats);
        self::assertSame(2, $stats[0]['count']);
        self::assertSame(30.0, $stats[0]['total_amount']);
        // Title is the most recent snapshot
        self::assertSame('New Title', $stats[0]['rule_title']);
    }
}
```

#### Step 2: Run → fail.

#### Step 3: `src/Repository/ReportsRepository.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Repository;

use PowerDiscount\Persistence\DatabaseAdapter;

final class ReportsRepository
{
    private const TABLE = 'pd_order_discounts';

    private DatabaseAdapter $db;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
    }

    /**
     * @return array<int, array{rule_id:int,rule_title:string,rule_type:string,count:int,total_amount:float}>
     */
    public function getRuleStats(): array
    {
        $rows = $this->db->findWhere($this->db->table(self::TABLE), []);
        $byRuleId = [];

        foreach ($rows as $row) {
            $ruleId = (int) ($row['rule_id'] ?? 0);
            if ($ruleId <= 0) {
                continue;
            }
            if (!isset($byRuleId[$ruleId])) {
                $byRuleId[$ruleId] = [
                    'rule_id'      => $ruleId,
                    'rule_title'   => (string) ($row['rule_title'] ?? ''),
                    'rule_type'    => (string) ($row['rule_type'] ?? ''),
                    'count'        => 0,
                    'total_amount' => 0.0,
                    '_max_id'      => 0,
                ];
            }
            $byRuleId[$ruleId]['count']++;
            $byRuleId[$ruleId]['total_amount'] += (float) ($row['discount_amount'] ?? 0);

            // Track the most recent (highest id) row's title as the canonical title.
            $rowId = (int) ($row['id'] ?? 0);
            if ($rowId >= $byRuleId[$ruleId]['_max_id']) {
                $byRuleId[$ruleId]['_max_id'] = $rowId;
                $byRuleId[$ruleId]['rule_title'] = (string) ($row['rule_title'] ?? '');
                $byRuleId[$ruleId]['rule_type'] = (string) ($row['rule_type'] ?? '');
            }
        }

        $stats = array_values(array_map(static function (array $entry): array {
            unset($entry['_max_id']);
            return $entry;
        }, $byRuleId));

        usort($stats, static function (array $a, array $b): int {
            return $b['total_amount'] <=> $a['total_amount'];
        });

        return $stats;
    }

    public function getTotalDiscount(): float
    {
        $rows = $this->db->findWhere($this->db->table(self::TABLE), []);
        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float) ($row['discount_amount'] ?? 0);
        }
        return $sum;
    }

    public function getTotalOrdersAffected(): int
    {
        $rows = $this->db->findWhere($this->db->table(self::TABLE), []);
        $orderIds = [];
        foreach ($rows as $row) {
            $orderIds[(int) ($row['order_id'] ?? 0)] = true;
        }
        unset($orderIds[0]);
        return count($orderIds);
    }
}
```

#### Step 4: Re-run → expect 6 passes. Full suite: 256 tests.

#### Step 5: Commit

```bash
git add src/Repository/ReportsRepository.php tests/Unit/Repository/ReportsRepositoryTest.php
git commit -m "feat(repo): add ReportsRepository with PHP-side aggregation and tests"
```

---

### Task 5: ReportsPage + AdminMenu integration

**Files:**
- Create: `src/Admin/ReportsPage.php`
- Create: `src/Admin/views/reports.php`
- Modify: `src/Admin/AdminMenu.php`

#### Step 1: `src/Admin/ReportsPage.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Admin;

use PowerDiscount\Repository\ReportsRepository;

final class ReportsPage
{
    private ReportsRepository $reports;

    public function __construct(ReportsRepository $reports)
    {
        $this->reports = $reports;
    }

    public function render(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied.', 'power-discount'));
        }

        $stats = $this->reports->getRuleStats();
        $totalDiscount = $this->reports->getTotalDiscount();
        $totalOrders = $this->reports->getTotalOrdersAffected();

        $rulesUrl = admin_url('admin.php?page=power-discount');

        require POWER_DISCOUNT_DIR . 'src/Admin/views/reports.php';
    }
}
```

#### Step 2: `src/Admin/views/reports.php`

```php
<?php
/**
 * @var array<int, array<string, mixed>> $stats
 * @var float $totalDiscount
 * @var int $totalOrders
 * @var string $rulesUrl
 */
if (!defined('ABSPATH')) {
    exit;
}
$priceFormat = function ($amount) {
    if (function_exists('wc_price')) {
        return wp_strip_all_tags(wc_price((float) $amount));
    }
    return number_format((float) $amount, 2);
};
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Power Discount Reports', 'power-discount'); ?></h1>
    <a href="<?php echo esc_url($rulesUrl); ?>" class="page-title-action"><?php esc_html_e('Manage Rules', 'power-discount'); ?></a>
    <hr class="wp-header-end">

    <div style="display:flex;gap:16px;margin:16px 0;">
        <div style="flex:1;background:#fff;border:1px solid #c3c4c7;padding:16px;border-radius:4px;">
            <div style="color:#646970;font-size:13px;"><?php esc_html_e('Total discount given', 'power-discount'); ?></div>
            <div style="font-size:24px;font-weight:600;margin-top:4px;"><?php echo esc_html($priceFormat($totalDiscount)); ?></div>
        </div>
        <div style="flex:1;background:#fff;border:1px solid #c3c4c7;padding:16px;border-radius:4px;">
            <div style="color:#646970;font-size:13px;"><?php esc_html_e('Orders affected', 'power-discount'); ?></div>
            <div style="font-size:24px;font-weight:600;margin-top:4px;"><?php echo (int) $totalOrders; ?></div>
        </div>
        <div style="flex:1;background:#fff;border:1px solid #c3c4c7;padding:16px;border-radius:4px;">
            <div style="color:#646970;font-size:13px;"><?php esc_html_e('Active rules tracked', 'power-discount'); ?></div>
            <div style="font-size:24px;font-weight:600;margin-top:4px;"><?php echo count($stats); ?></div>
        </div>
    </div>

    <h2><?php esc_html_e('Rule performance', 'power-discount'); ?></h2>
    <?php if ($stats === []): ?>
        <p><?php esc_html_e('No discount records yet. Reports populate as orders get placed.', 'power-discount'); ?></p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Rule', 'power-discount'); ?></th>
                    <th><?php esc_html_e('Type', 'power-discount'); ?></th>
                    <th><?php esc_html_e('Times applied', 'power-discount'); ?></th>
                    <th><?php esc_html_e('Total discount', 'power-discount'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats as $row): ?>
                    <tr>
                        <td><strong><?php echo esc_html((string) $row['rule_title']); ?></strong> <span style="color:#999;">#<?php echo (int) $row['rule_id']; ?></span></td>
                        <td><?php echo esc_html((string) $row['rule_type']); ?></td>
                        <td><?php echo (int) $row['count']; ?></td>
                        <td><?php echo esc_html($priceFormat($row['total_amount'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
```

#### Step 3: Modify `src/Admin/AdminMenu.php`

Add a constructor parameter for `ReportsPage` and a new submenu page.

Update constructor:

```php
    private RuleRepository $rules;
    private RulesListPage $listPage;
    private RuleEditPage $editPage;
    private ReportsPage $reportsPage;

    public function __construct(RuleRepository $rules, RulesListPage $listPage, RuleEditPage $editPage, ReportsPage $reportsPage)
    {
        $this->rules = $rules;
        $this->listPage = $listPage;
        $this->editPage = $editPage;
        $this->reportsPage = $reportsPage;
    }
```

Add import:
```php
use PowerDiscount\Admin\ReportsPage;
```

Wait — `ReportsPage` is in the same namespace, no import needed. Skip that.

Update `registerMenu()` to add the reports submenu:

```php
    public function registerMenu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Power Discount', 'power-discount'),
            __('Power Discount', 'power-discount'),
            'manage_woocommerce',
            'power-discount',
            [$this, 'route']
        );
        add_submenu_page(
            'woocommerce',
            __('Power Discount Reports', 'power-discount'),
            __('PD Reports', 'power-discount'),
            'manage_woocommerce',
            'power-discount-reports',
            [$this->reportsPage, 'render']
        );
    }
```

#### Step 4: `php -l` all 3 files. Full suite still 256.

#### Step 5: Commit

```bash
git add src/Admin/ReportsPage.php src/Admin/views/reports.php src/Admin/AdminMenu.php
git commit -m "feat(admin): add ReportsPage with summary cards and per-rule performance table"
```

---

### Task 6: Plugin wire-up + README + manual verification

**Files:**
- Modify: `src/Plugin.php`
- Modify: `README.md`
- Create: `docs/phase-4c-manual-verification.md`

#### Step 1: Modify `src/Plugin.php`

Add imports:
```php
use PowerDiscount\Admin\ReportsPage;
use PowerDiscount\Frontend\FreeShippingBar;
use PowerDiscount\Frontend\FreeShippingProgressHelper;
use PowerDiscount\Frontend\PriceTableShortcode;
use PowerDiscount\Repository\ReportsRepository;
```

Inside `boot()`, after the existing integration registrations and before the `is_admin()` block, add:

```php
        // Frontend components (cart/checkout pages)
        $progressHelper = new FreeShippingProgressHelper();
        (new FreeShippingBar($rulesRepo, $builder, $progressHelper))->register();
        (new PriceTableShortcode($rulesRepo))->register();
```

Update the `is_admin()` block to instantiate `ReportsPage` and pass it to `AdminMenu`:

Replace:
```php
        if (is_admin()) {
            $listPage = new RulesListPage($rulesRepo);
            $editPage = new RuleEditPage($rulesRepo);
            (new AdminMenu($rulesRepo, $listPage, $editPage))->register();
            (new AjaxController($rulesRepo))->register();
            (new Notices())->register();
        }
```

With:
```php
        if (is_admin()) {
            $listPage = new RulesListPage($rulesRepo);
            $editPage = new RuleEditPage($rulesRepo);
            $reportsPage = new ReportsPage(new ReportsRepository($db));
            (new AdminMenu($rulesRepo, $listPage, $editPage, $reportsPage))->register();
            (new AjaxController($rulesRepo))->register();
            (new Notices())->register();
        }
```

#### Step 2: Update `README.md` `## Status`:

```markdown
## Status

**Phase 4c (Frontend + Reports)** — complete. **MVP feature-complete.**

Frontend:
- `[power_discount_table id=PRODUCT_ID]` shortcode renders bulk-tier price tables for a given product
- Free shipping progress bar appears on cart and checkout pages, computed via `FreeShippingProgressHelper` from active `free_shipping` rules with `cart_subtotal` thresholds

Admin:
- `WooCommerce → PD Reports` shows total discount given, orders affected, and per-rule performance table sorted by total amount

All 8 strategies, 13 conditions, 6 filters, full Admin CRUD, WC integration (cart/order/shipping hooks), and reports are now in place.

Pending (post-MVP):
- React rule builder (Phase 4d, optional polish)
- BulkStrategy `per_category` scope
- BuyXGetY `cheapest_from_filter` reward target
```

#### Step 3: Create `docs/phase-4c-manual-verification.md`

````markdown
# Phase 4c Manual Verification

## Prereqs

Activate `power-discount` on a real WP+WC site. Phase 4a/4b verification already passed.

## Free Shipping Bar

Create rule via the admin UI (or SQL):
- Type: `free_shipping`
- Status: enabled
- `config_json`: `{"method":"remove_shipping"}`
- `conditions_json`: `{"logic":"and","items":[{"type":"cart_subtotal","operator":">=","value":1000}]}`

Verify:
- [ ] Visit cart with subtotal NT$200 → bar shows "Add NT$800 more to qualify for free shipping" with progress bar at 20%
- [ ] Increase to NT$1000 → bar shows "🎉 You qualify for free shipping!"
- [ ] Disable rule → bar disappears
- [ ] Same checks on the checkout page

## Price Table Shortcode

Create a `bulk` rule that targets a specific product:
- `config_json`: `{"count_scope":"cumulative","ranges":[{"from":1,"to":4,"method":"percentage","value":0},{"from":5,"to":9,"method":"percentage","value":10},{"from":10,"to":null,"method":"percentage","value":20}]}`
- `filters_json`: `{"items":[{"type":"products","method":"in","ids":[PRODUCT_ID]}]}`

On the product page (or any page) add the shortcode `[power_discount_table id=PRODUCT_ID]`.

Verify:
- [ ] Table shows three rows: "1 – 4 / 0%" (skipped because value=0), "5 – 9 / 10%", "10+ / 20%"
- [ ] Wrong product ID → empty output
- [ ] Rule disabled → empty output

## Reports Page

Place a few orders that trigger discounts (any rule).

Visit `WooCommerce → PD Reports`.

Verify:
- [ ] Three summary cards show totals
- [ ] Per-rule table sorted by total discount DESC
- [ ] Most recent rule title is shown if a rule was renamed mid-period

## Known Gaps → Phase 4d (optional)

- React rule builder (currently raw JSON textarea)
- Date range filter on reports page
- Export CSV
- Live preview in rule editor
````

#### Step 4: `php -l src/Plugin.php`. Full suite still **256 tests**.

#### Step 5: Commit

```bash
git add src/Plugin.php README.md docs/phase-4c-manual-verification.md
git commit -m "feat: wire frontend components and reports page in Plugin::boot + Phase 4c docs"
```

---

## Phase 4c Exit Criteria

- ✅ `vendor/bin/phpunit` ≥ 256 tests green
- ✅ All `.php` files lint clean
- ✅ FreeShippingProgressHelper has 8 unit tests
- ✅ ReportsRepository has 6 unit tests
- ✅ `[power_discount_table]` shortcode registered
- ✅ Free shipping bar hooked to cart/checkout
- ✅ Reports submenu under WooCommerce
- ✅ README + manual verification doc committed

## Known Gaps → Phase 4d (optional)

- No React rule builder (JSON textarea only)
- Reports page has no date range filter / CSV export
- Price table shortcode only supports `bulk` strategy
- Free shipping bar only parses `cart_subtotal` thresholds (other condition types show generic message)
