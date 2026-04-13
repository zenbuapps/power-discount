# Power Discount — 設計文件

- **Date**: 2026-04-14
- **Status**: Draft (pending user review)
- **Owner**: Luke
- **Plugin slug**: `power-discount`
- **Repo**: `/Users/luke/power-discount/`

## 1. 目標與範圍

建立一個免費的 WooCommerce 折扣規則外掛 `power-discount`，供內部工作團隊服務電商客戶使用。不含授權機制、不走 marketplace。

設計參考 Flycart「Woo Discount Rules」(Free v2.6.14 + Pro v2.6.7) 的良好架構，並補足它在臺灣電商常見場景中的缺口。

### 範圍（Taiwan 優先版）

**涵蓋**：

- 8 種折扣類型（含 2 種 Taiwan 新增：第 N 件 X 折、紅配綠）
- 13 種條件（含 Taiwan 的生日月份、付款方式、運送方式）
- 6 種商品篩選器
- 管理後台（列表頁 PHP + 編輯頁 React）
- 基本報表（規則命中次數、折抵總額）
- 前端元件：Price Table 短碼、免運進度條、Saved Label

**刻意不做（YAGNI）**：

- 授權機制、付費 Add-on
- WDR Pro 的 weight-based condition、zip code、custom taxonomy filter
- 點數折抵、發票載具（與外部系統整合的能力）
- 購買歷史進階條件（僅做 first_order、total_spent）
- 多層巢狀 AND/OR（只做單層）
- 客選贈品（BOGO 只做固定/最便宜）

### 成功標準

1. 3 個 Taiwan 示範規則可以完整設定並正確計算：
   - 咖啡豆分類任選 2 件 $600
   - 咖啡豆分類任選 4 件現折 $100（WDR 做不到）
   - 第二件 6 折（WDR 做不到）
2. 核心 Strategy / Condition / Filter unit test 覆蓋率 ≥ 80%
3. HPOS 與舊式 post meta 雙相容，開關切換皆可用
4. 單一規則編輯頁面從載入到存檔 ≤ 1 秒（本機）
5. 1000 條規則下購物車計算 ≤ 150ms（本機）

---

## 2. 技術決策（Q1–Q8 結論）

| # | 項目 | 決定 |
|---|---|---|
| Q1 | 功能範圍 | Taiwan 優先版 |
| Q2 | Admin UI 風格 | Hybrid：列表頁 PHP、編輯頁 React |
| Q3 | 資料儲存 | 自訂資料表 |
| Q4 | HPOS 相容 | 雙相容（舊 post meta + HPOS），WC 7.0+ / PHP 7.4+ |
| Q5 | 擴充性 | 中度開放 + Registry pattern |
| Q6 | 命名 | slug `power-discount` / namespace `PowerDiscount\` / 表前綴 `pd_` / hook 前綴 `power_discount_*` |
| Q7a | Repo 位置 | `/Users/luke/power-discount/`（獨立 repo） |
| Q7b | 測試策略 | 核心 PHPUnit 80% 覆蓋 + 手動 UI |
| Q8 | 內部架構 | Strategy Pattern + Registry |

---

## 3. 頂層架構

```
┌─────────────────────────────────────────────────────────┐
│                  WooCommerce Cart/Order                 │
└──────────────────────┬──────────────────────────────────┘
                       │ (WC hooks)
┌──────────────────────▼──────────────────────────────────┐
│             PowerDiscount\Engine\Calculator              │
│  1. RuleRepository::getActiveRules()                    │
│  2. foreach rule (priority ASC):                        │
│     ├─ FilterMatcher::matches(rule, product)            │
│     ├─ ConditionEvaluator::evaluate(rule, cart)         │
│     └─ StrategyRegistry::resolve(rule->type)            │
│                    ↓                                    │
│              Strategy->apply(rule, cart)                │
│                    ↓                                    │
│              DiscountResult                             │
│  3. Aggregator 套用到 cart / fee / shipping             │
│  4. OrderDiscountLogger 下單時記錄                      │
└─────────────────────────────────────────────────────────┘

                  ▲                   ▲
                  │                   │
          Strategy Registry    Condition Registry
        (simple/bulk/set/...)   (subtotal/role/...)
                  ▲                   ▲
                  │                   │
         外部 addon 可透過 filter 註冊新實作
```

### 分層原則

- **Domain**：純值物件，不依賴 WooCommerce，可 100% 單元測試
- **Strategy / Condition / Filter**：接受 `Rule`、`CartContext`，回傳 `DiscountResult` 或 bool；不直接操作 `WC_Cart`
- **Engine**：協調層，呼叫 Strategy，把結果交給 Aggregator
- **Integration**：與 WC hook 對接的唯一入口；是整個外掛對 WC API 的 adapter
- **Admin / Frontend**：UI 層，只透過 Repository 與 Service 讀寫

---

## 4. 目錄結構

```
/Users/luke/power-discount/
├── power-discount.php               # Bootstrap, HPOS declare
├── composer.json                    # PSR-4: "PowerDiscount\\": "src/"
├── phpunit.xml
├── readme.txt
├── uninstall.php
│
├── src/
│   ├── Plugin.php                   # 啟動器
│   ├── Container.php                # 輕量 service container
│   │
│   ├── Install/
│   │   ├── Activator.php
│   │   ├── Deactivator.php
│   │   └── Migrator.php             # schema versioning
│   │
│   ├── Domain/                      # 純資料物件
│   │   ├── Rule.php
│   │   ├── DiscountResult.php
│   │   ├── CartContext.php
│   │   └── RuleStatus.php
│   │
│   ├── Repository/
│   │   ├── RuleRepository.php
│   │   └── OrderDiscountRepository.php
│   │
│   ├── Strategy/
│   │   ├── DiscountStrategyInterface.php
│   │   ├── StrategyRegistry.php
│   │   ├── SimpleStrategy.php
│   │   ├── BulkStrategy.php
│   │   ├── CartStrategy.php
│   │   ├── SetStrategy.php
│   │   ├── BuyXGetYStrategy.php
│   │   ├── NthItemStrategy.php
│   │   ├── CrossCategoryStrategy.php
│   │   └── FreeShippingStrategy.php
│   │
│   ├── Condition/
│   │   ├── ConditionInterface.php
│   │   ├── ConditionRegistry.php
│   │   ├── Evaluator.php
│   │   ├── CartSubtotalCondition.php
│   │   ├── CartQuantityCondition.php
│   │   ├── CartLineItemsCondition.php
│   │   ├── UserRoleCondition.php
│   │   ├── UserLoggedInCondition.php
│   │   ├── DateRangeCondition.php
│   │   ├── DayOfWeekCondition.php
│   │   ├── TimeOfDayCondition.php
│   │   ├── PaymentMethodCondition.php
│   │   ├── ShippingMethodCondition.php
│   │   ├── FirstOrderCondition.php
│   │   ├── TotalSpentCondition.php
│   │   └── BirthdayMonthCondition.php
│   │
│   ├── Filter/
│   │   ├── FilterInterface.php
│   │   ├── FilterRegistry.php
│   │   ├── Matcher.php
│   │   ├── AllProductsFilter.php
│   │   ├── ProductsFilter.php
│   │   ├── CategoriesFilter.php
│   │   ├── TagsFilter.php
│   │   ├── AttributesFilter.php
│   │   └── OnSaleFilter.php
│   │
│   ├── Engine/
│   │   ├── Calculator.php
│   │   ├── Aggregator.php
│   │   └── ExclusivityResolver.php
│   │
│   ├── Integration/
│   │   ├── CartHooks.php
│   │   ├── OrderHooks.php
│   │   ├── ProductPriceHooks.php
│   │   └── ShippingHooks.php
│   │
│   ├── Admin/
│   │   ├── Menu.php
│   │   ├── RuleListPage.php         # WP_List_Table
│   │   ├── RuleEditPage.php         # 掛載 React
│   │   ├── SettingsPage.php
│   │   ├── ReportPage.php
│   │   └── Rest/
│   │       ├── RulesController.php
│   │       └── PreviewController.php
│   │
│   ├── Frontend/
│   │   ├── PriceTableShortcode.php
│   │   ├── FreeShippingBar.php
│   │   └── SavedLabel.php
│   │
│   └── I18n/
│       └── Loader.php
│
├── assets/
│   ├── admin-editor/                # React source
│   │   ├── package.json
│   │   ├── src/
│   │   │   ├── index.tsx
│   │   │   ├── App.tsx
│   │   │   ├── store/               # @wordpress/data
│   │   │   ├── components/
│   │   │   │   ├── RuleBuilder/
│   │   │   │   ├── StrategyForm/
│   │   │   │   ├── ConditionBuilder/
│   │   │   │   └── FilterBuilder/
│   │   │   └── types/
│   │   └── build/
│   ├── admin-list/
│   └── frontend/
│       ├── price-table.js
│       └── free-shipping-bar.js
│
├── languages/
│   └── power-discount-zh_TW.po
│
├── tests/
│   ├── Unit/
│   │   ├── Strategy/
│   │   ├── Condition/
│   │   ├── Filter/
│   │   └── Engine/
│   ├── Integration/
│   └── bootstrap.php
│
└── docs/
    ├── superpowers/specs/           # 本文件
    ├── architecture.md
    ├── hooks.md
    └── adding-a-strategy.md
```

---

## 5. 資料庫 Schema

### 5.1 `wp_pd_rules`

```sql
CREATE TABLE {$prefix}pd_rules (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title           VARCHAR(255) NOT NULL,
  type            VARCHAR(64)  NOT NULL,
  status          TINYINT(1)   NOT NULL DEFAULT 1,
  priority        INT          NOT NULL DEFAULT 10,
  exclusive       TINYINT(1)   NOT NULL DEFAULT 0,
  starts_at       DATETIME     NULL,
  ends_at         DATETIME     NULL,
  usage_limit     INT          NULL,
  used_count      INT          NOT NULL DEFAULT 0,
  filters         LONGTEXT     NOT NULL,
  conditions      LONGTEXT     NOT NULL,
  config          LONGTEXT     NOT NULL,
  label           VARCHAR(255) NULL,
  notes           TEXT         NULL,
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  KEY idx_status_priority (status, priority),
  KEY idx_type (type),
  KEY idx_dates (starts_at, ends_at)
);
```

**`type`** 值：`simple` / `bulk` / `cart` / `set` / `buy_x_get_y` / `nth_item` / `cross_category` / `free_shipping`

**`config`** 為 JSON，由每個 Strategy 自行 serialize / parse。每個 Strategy 必須實作 `validateConfig(array $config): array` 做 schema 驗證。

### 5.2 `wp_pd_order_discounts`

```sql
CREATE TABLE {$prefix}pd_order_discounts (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id        BIGINT UNSIGNED NOT NULL,
  rule_id         BIGINT UNSIGNED NOT NULL,
  rule_title      VARCHAR(255)    NOT NULL,
  rule_type       VARCHAR(64)     NOT NULL,
  discount_amount DECIMAL(18,4)   NOT NULL,
  scope           VARCHAR(32)     NOT NULL,
  meta            LONGTEXT        NULL,
  created_at      DATETIME        NOT NULL,
  KEY idx_order (order_id),
  KEY idx_rule (rule_id)
);
```

**`scope`** 值：`product` / `cart` / `shipping`。（MVP 不做 extra fee；欄位長度留給未來擴充。）

**快照策略**：`rule_title` 與 `rule_type` 寫入時快照，避免規則事後改名影響歷史報表。

### 5.3 Migration

`Migrator` class 管理 schema version，以 `power_discount_schema_version` option 追蹤。每次啟用時比對目標版本，有差距就跑遞增 migration。

---

## 6. 折扣類型規格

每種 Strategy 的 `config` JSON schema 與計算邏輯：

### 6.1 `simple` — 商品簡單折扣

```json
{
  "method": "percentage | flat | fixed_price",
  "value": 10,
  "label": "9 折優惠"
}
```

對命中 filter 的商品逐件改價。`percentage` → `price × (1 - v/100)`；`flat` → `price - v`；`fixed_price` → `= v`。

### 6.2 `bulk` — 數量階梯折扣

```json
{
  "count_scope": "per_item | per_category | cumulative",
  "ranges": [
    {"from": 1, "to": 4,    "method": "percentage", "value": 0},
    {"from": 5, "to": 9,    "method": "percentage", "value": 10},
    {"from": 10, "to": null, "method": "percentage", "value": 20}
  ]
}
```

依總數量落在哪個 range 決定折扣。`to: null` 表示無上限。

- `per_item`：每個商品各自算階梯
- `per_category`：依 filter 命中的分類合併數量
- `cumulative`：所有命中商品總和

### 6.3 `cart` — 整車折扣

```json
{
  "method": "percentage | flat_total | flat_per_item",
  "value": 100,
  "label": "滿千折百"
}
```

- `percentage`：整車 N%
- `flat_total`：從小計扣 $X（以單筆 fee 實現）
- `flat_per_item`：逐項目攤平扣 $X（發票每項都有折扣）

### 6.4 `set` — 任選組合折扣 ⭐

```json
{
  "bundle_size": 3,
  "method": "set_price | set_percentage | set_flat_off",
  "value": 90,
  "repeat": true,
  "source": "filter | specific_products",
  "specific_products": [123, 456]
}
```

- `set_price`：N 件固定賣 $X（任選 2 件 $90）
- `set_percentage`：N 件打 X 折（任選 3 件 9 折）
- `set_flat_off`：N 件現折 $X（任選 4 件現折 $100）— **WDR 沒有**
- `repeat`：購物車能湊出多組時，折扣是否重複套用

### 6.5 `buy_x_get_y` — 買 X 送 Y

```json
{
  "trigger": {"source": "filter | specific", "qty": 2, "product_ids": []},
  "reward": {
    "target": "same | specific | cheapest_in_cart | cheapest_from_filter",
    "qty": 1,
    "method": "free | percentage | flat",
    "value": 100,
    "product_ids": []
  },
  "auto_add": false,
  "recursive": true
}
```

涵蓋：買一送一、買二送一、買三送最便宜、買 X 送 Y 半價。`auto_add` 只做開關，MVP 不做「客選贈品」。

### 6.6 `nth_item` — 第 N 件 X 折 ⭐

```json
{
  "tiers": [
    {"nth": 1, "method": "percentage", "value": 0},
    {"nth": 2, "method": "percentage", "value": 40},
    {"nth": 3, "method": "percentage", "value": 50}
  ],
  "sort_by": "price_desc | price_asc",
  "recursive": true
}
```

命中商品依 `sort_by` 排序，逐件套對應 tier。超出定義的 tier 沿用最後一個。

`recursive` 開啟時以 `max(tiers.nth)` 為循環長度 K，每 K 件重啟 tier 計數。例：tiers 定義到第 3 件，第 4 件重新當作「第 1 件」套用 tier 1；第 5 件當第 2 件套 tier 2；以此類推。`recursive=false` 時超過最後一個 tier 沿用最後一個。

### 6.7 `cross_category` — 紅配綠 ⭐

```json
{
  "groups": [
    {"name": "上衣", "filter": {"type":"categories","value":[12]}, "min_qty": 1},
    {"name": "褲子", "filter": {"type":"categories","value":[13]}, "min_qty": 1}
  ],
  "reward": {
    "method": "percentage | flat | fixed_bundle_price",
    "value": 20,
    "apply_to": "bundle | specific_group",
    "target_group_index": 0
  },
  "repeat": true
}
```

所有 group 都達到 `min_qty` 才成立，成立一組套一次折扣。`apply_to` 可指定折扣只套在某一組（例如「買上衣任一件 + 褲子任一件，褲子打 8 折」）。

### 6.8 `free_shipping` — 條件免運

```json
{
  "method": "remove_shipping | percentage_off_shipping",
  "value": 50
}
```

條件成立時移除或按比例折抵運費。Integration 層用 `woocommerce_package_rates` filter 實現。

### 規則類型對照 WDR

| 類型 | WDR Free | WDR Pro | power-discount |
|---|---|---|---|
| simple | ✅ | ✅ +fixed_price | ✅ 三種 method |
| bulk | ✅ | ✅ | ✅ |
| cart | ✅ (2) | ✅ (3) | ✅ 三種 method |
| set | 半殘 | ✅ (2) | ✅ **三種 method**，補 flat_off |
| buy_x_get_y | 隱藏 | ✅ 6 變體 | ✅ 統一 strategy |
| nth_item | ❌ | ❌ | ⭐ 新增 |
| cross_category | ❌ | ❌ | ⭐ 新增 |
| free_shipping | ❌ | ✅ | ✅ |

---

## 7. 條件系統

### 7.1 Conditions（13 條）

| 代碼 | Class | 欄位 |
|---|---|---|
| `cart_subtotal` | CartSubtotalCondition | operator, value |
| `cart_quantity` | CartQuantityCondition | operator, value |
| `cart_line_items` | CartLineItemsCondition | operator, value |
| `user_role` | UserRoleCondition | roles[] |
| `user_logged_in` | UserLoggedInCondition | is_logged_in |
| `date_range` | DateRangeCondition | from, to |
| `day_of_week` | DayOfWeekCondition | days[] |
| `time_of_day` | TimeOfDayCondition | from, to |
| `payment_method` | PaymentMethodCondition | methods[] |
| `shipping_method` | ShippingMethodCondition | methods[] |
| `first_order` | FirstOrderCondition | is_first_order |
| `total_spent` | TotalSpentCondition | operator, value |
| `birthday_month` | BirthdayMonthCondition | match_current_month |

**特殊行為說明**：

- `first_order`：以登入使用者的歷史訂單判斷（排除 cancelled / failed / trash）。未登入的訪客一律視為 first order = true（避免首購優惠對訪客無效）。
- `total_spent`：只計登入使用者。未登入訪客視為 0。
- `birthday_month`：讀取 user meta key `billing_birthday`（格式 `YYYY-MM-DD` 或 `MM-DD`）。欄位本身不由本外掛提供，需由客戶站另外設定（例如 My Account 表單客製）。欄位不存在或格式錯誤時條件回傳 false。

**Operator** 統一支援：`>=`、`>`、`=`、`<=`、`<`、`!=`。

### 7.2 組合邏輯

```json
{
  "logic": "and | or",
  "items": [
    {"type": "cart_subtotal", "operator": ">=", "value": 1000},
    {"type": "payment_method", "methods": ["ecpay_linepay"]}
  ]
}
```

單層組合，不做巢狀。

---

## 8. 篩選器系統

### 8.1 Filters（6 種）

| 代碼 | Class | 欄位 |
|---|---|---|
| `all_products` | AllProductsFilter | — |
| `products` | ProductsFilter | ids[], method: in/not_in |
| `categories` | CategoriesFilter | ids[], method, include_subcategories |
| `tags` | TagsFilter | ids[], method |
| `attributes` | AttributesFilter | attribute, terms[], method |
| `on_sale` | OnSaleFilter | — |

### 8.2 組合邏輯

```json
{
  "logic": "and",
  "items": [
    {"type": "categories", "ids": [12, 13], "method": "in"},
    {"type": "products", "ids": [999], "method": "not_in"}
  ]
}
```

Filter 的 `logic` 固定為 AND（「符合類別 X 且不在排除清單」是最常見用法）。若需 OR，用多條規則搞定。

---

## 9. 計算流程

```
WC event → Calculator::run(CartContext $ctx)
  │
  ├── rules = RuleRepository::getActiveRules()
  │     (WHERE status=1 ORDER BY priority ASC)
  │
  ├── foreach rule:
  │     ├─ 檢查 starts_at/ends_at
  │     ├─ 檢查 usage_limit vs used_count
  │     ├─ ConditionEvaluator::evaluate(rule, ctx)
  │     ├─ matchedItems = FilterMatcher::matches(rule, ctx->items)
  │     ├─ 若 matchedItems 空 → skip
  │     ├─ strategy = StrategyRegistry::resolve(rule->type)
  │     ├─ result = strategy->apply(rule, matchedItems, ctx)
  │     ├─ apply_filters('power_discount_calculated_result', $result, $rule, $ctx)
  │     ├─ results[] = result
  │     └─ if rule->exclusive: break
  │
  ├── Aggregator::apply(results, cart)
  │     ├─ scope=product  → $cart_item['data']->set_price()
  │     ├─ scope=cart     → $cart->add_fee() negative
  │     └─ scope=shipping → ShippingHooks 處理
  │
  └── on 'woocommerce_checkout_order_processed':
        OrderDiscountLogger::log(order_id, results)
```

### 互斥 / 優先序策略

1. 規則依 `priority` ASC 順序迭代（同 priority 依 `id` ASC）
2. 命中後若 `exclusive=1`，停止處理後續規則
3. 非互斥規則的折扣結果互相疊加，除非造成負數總價（此時由 Aggregator 夾住 ≥ 0）

---

## 10. Admin UI

### 10.1 列表頁（PHP + WP_List_Table）

**路徑**：`/wp-admin/admin.php?page=power-discount`

**欄位**：標題 / 類型 / 狀態 / 優先序 / 期間 / 已使用次數 / 操作

**功能**：

- 啟用 / 停用切換（AJAX）
- 複製、刪除
- 拖曳排序 priority（jQuery UI Sortable）
- 頂部篩選：依類型、依狀態

### 10.2 編輯頁（React SPA）

**路徑**：`/wp-admin/admin.php?page=power-discount&action=edit&id=X`

**技術**：`@wordpress/scripts` + `@wordpress/components` + `@wordpress/data`

**區塊**（上到下）：

1. **基本資訊**：標題 / 啟用 / 優先序 / 互斥 / 期間 / 使用次數
2. **折扣類型**：Select（8 種）
3. **折扣設定**：依類型載入對應 `<StrategyForm />`
4. **商品篩選**：`<FilterBuilder />`
5. **條件**：`<ConditionBuilder />` + AND/OR toggle
6. **前台顯示文字**：label、說明
7. **Live Preview**：測試購物車即時顯示套用後金額

**StrategyForm 註冊機制**：每個 strategy 的表單 component 透過 `power_discount_admin_strategy_form_fields` filter 註冊，對應 Strategy Registry 的後端擴充點。

### 10.3 REST API

```
GET    /wp-json/power-discount/v1/rules
GET    /wp-json/power-discount/v1/rules/{id}
POST   /wp-json/power-discount/v1/rules
PUT    /wp-json/power-discount/v1/rules/{id}
DELETE /wp-json/power-discount/v1/rules/{id}
POST   /wp-json/power-discount/v1/preview    # 試算
GET    /wp-json/power-discount/v1/meta       # 類型 / 條件 / 篩選器清單（給 React 初始化）
```

Permission：`manage_woocommerce` capability。

### 10.4 設定頁（PHP）

- 計算時機（cart / checkout）
- 是否顯示 saved label、price table
- 小計顯示方式
- 是否記錄到 order meta

### 10.5 報表頁（PHP，MVP 簡版）

- 每條規則：命中次數、折抵總額、TOP 商品
- 以月為單位的折扣趨勢
- 匯出 CSV

---

## 11. 前端呈現

- **Price Table 短碼** `[power_discount_table]`：顯示當前商品的 bulk/set 階梯
- **Free Shipping Bar**：購物車頂部顯示「再買 $X 免運」進度條，購物車更新時 AJAX 重算
- **Saved Label**：購物車項目顯示本項省下多少
- **Rule Label**：命中規則名稱顯示在購物車對應項目（可關）

---

## 12. Hooks（Registry 擴充點）

### 12.1 Filters

```php
apply_filters('power_discount_strategies', array $strategies)
apply_filters('power_discount_conditions', array $conditions)
apply_filters('power_discount_filters', array $filters)
apply_filters('power_discount_calculated_result', DiscountResult $result, Rule $rule, CartContext $ctx)
apply_filters('power_discount_admin_strategy_form_fields', array $fields, string $type)
```

### 12.2 Actions

```php
do_action('power_discount_rule_matched', Rule $rule, CartContext $ctx)
do_action('power_discount_rule_skipped', Rule $rule, CartContext $ctx, string $reason)
do_action('power_discount_order_logged', int $order_id, array $results)
```

這 8 個 hook 覆蓋 ≥ 95% 的擴充需求。

---

## 13. 測試策略

- **Unit (PHPUnit)** — `tests/Unit/`
  - 每個 Strategy：8–15 case（空車、單品、多品、邊界、null 處理）
  - 每個 Condition / Filter：3–5 case
  - Calculator：多規則 priority / exclusive 的整合情境
  - **目標 ≥ 80% coverage**
- **Integration** — `tests/Integration/` 用 `wp-phpunit` 跑真 WC 環境，約 10 個 end-to-end case（規則 → 加商品 → 驗 cart totals → 下單 → 驗 `wp_pd_order_discounts`）
- **React** — Jest + RTL，只測 `RuleBuilder` 的 form state 行為
- **手動** — 瀏覽器跑 golden path 與 3 個 Taiwan 示範規則

---

## 14. 實作階段（Milestones）

| # | Milestone | 產出 |
|---|---|---|
| **M1** | Foundation | composer.json、主檔、HPOS declare、Activator、Deactivator、Migrator、兩張表、i18n loader、CI skeleton |
| **M2** | Domain + Repository | Rule、DiscountResult、CartContext、RuleStatus、RuleRepository CRUD + unit tests |
| **M3** | Registry + 4 core strategies | 三個 Registry、Simple / Bulk / Cart / Set（含 flat_off）+ 完整 unit tests |
| **M4** | Engine + Integration | Calculator、Aggregator、ExclusivityResolver、CartHooks、ProductPriceHooks |
| **M5** | Taiwan 特化 strategies | BuyXGetY / NthItem / CrossCategory / FreeShipping + tests |
| **M6** | Conditions + Filters | 13 conditions、6 filters、Evaluator AND/OR + tests |
| **M7** | Admin UI | WP_List_Table、設定頁、React 編輯器骨架、StrategyForm、ConditionBuilder、FilterBuilder、REST API |
| **M8** | Frontend + 報表 + 收尾 | Price table 短碼、免運進度條、Saved label、Order logger、報表頁、手動 QA、README |

**預估總時程**：3–4 週（每日 4–6 小時）

---

## 15. 風險與未決事項

- **React build pipeline**：第一次跑 `@wordpress/scripts` 在此專案，M7 要預留半天處理。
- **HPOS 雙寫測試**：需要一台 HPOS 開啟與一台關閉的 WC 測試站，最好 Docker 搞定。
- **性能**：1000 條規則的目標若達不到，先優化 `getActiveRules()` 的快取（in-memory per request cache）。
- **i18n 字串提取時機**：每個 Milestone 結束抽一次 POT，避免最後補字串找不到上下文。
- **uninstall 政策**：MVP 預設 uninstall 時**不刪資料**（只移除 option），避免誤刪客戶訂單記錄；提供「刪除全部資料」按鈕在設定頁。

---

## 16. 參考

- Flycart Woo Discount Rules Free v2.6.14 — `/tmp/wdr-free/woo-discount-rules/`
- Flycart Woo Discount Rules Pro v2.6.7 — `/tmp/wdr-pro/woo-discount-rules-pro/`
- WooCommerce HPOS 官方文件
- `@wordpress/scripts` build toolchain
