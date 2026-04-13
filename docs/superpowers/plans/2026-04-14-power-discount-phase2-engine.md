# Power Discount — Phase 2: Repository + Engine + WC Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 讓 power-discount 從「4 個可單元測試的折扣策略」進化成「掛到真 WooCommerce 購物車後會實際算出折扣」的可運作外掛，並能把命中紀錄寫入自訂表。

**Architecture:** 在 Phase 1 的 Domain + Strategy 基礎上，加入：
- Repository 層（用 wpdb 的最小抽象 `DatabaseAdapter`，可完全 mock 於單元測試）
- Condition / Filter 的骨架 + 各 2 個最常用實作（cart_subtotal、date_range、all_products、categories）
- Engine 三件組（Calculator / Aggregator / ExclusivityResolver）
- WooCommerce CartHooks（`woocommerce_cart_calculate_fees`）把 Calculator 串到購物車
- OrderDiscountLogger（結帳時把命中規則寫入 `wp_pd_order_discounts`）

**Tech Stack:** PHP 7.4+、PHPUnit 9.6、Brain\Monkey（WP 函式 mock）、WC 7.0+

**Phase 定位：** 是 4 個 phase 中的第 2 個。
- Phase 1 ✅ Foundation + Domain + 4 core strategies
- **Phase 2（本文）** Repository + Engine + WC 串接（僅 2 conditions / 2 filters）
- Phase 3 剩下 11 conditions + 4 filters + Taiwan strategies
- Phase 4 Admin UI + REST API + Frontend

---

## File Structure

本 phase 新增：

```
src/
├── Persistence/
│   ├── DatabaseAdapter.php              # 介面：write/read/query/prepare
│   ├── WpdbAdapter.php                  # 生產用：包 global $wpdb
│   └── JsonSerializer.php               # 處理 filters/conditions/config 欄位
├── Repository/
│   ├── RuleRepository.php               # pd_rules CRUD + getActiveRules
│   └── OrderDiscountRepository.php      # pd_order_discounts insert + find
├── Condition/
│   ├── ConditionInterface.php
│   ├── ConditionRegistry.php
│   ├── Evaluator.php                    # AND/OR 組合
│   ├── CartSubtotalCondition.php
│   └── DateRangeCondition.php
├── Filter/
│   ├── FilterInterface.php
│   ├── FilterRegistry.php
│   ├── Matcher.php                      # 回傳過濾後的 CartItem[]
│   ├── AllProductsFilter.php
│   └── CategoriesFilter.php
├── Engine/
│   ├── Calculator.php                   # 主流程
│   ├── Aggregator.php                   # DiscountResult[] → 套回 WC_Cart
│   └── ExclusivityResolver.php          # priority + exclusive
├── Integration/
│   ├── CartContextBuilder.php           # WC_Cart → Domain CartContext
│   ├── CartHooks.php                    # 掛 woocommerce_cart_calculate_fees
│   └── OrderDiscountLogger.php          # 掛 woocommerce_checkout_order_processed

tests/Unit/
├── Persistence/
│   └── JsonSerializerTest.php
├── Repository/
│   ├── RuleRepositoryTest.php           # DatabaseAdapter 用 stub
│   └── OrderDiscountRepositoryTest.php
├── Condition/
│   ├── EvaluatorTest.php
│   ├── CartSubtotalConditionTest.php
│   └── DateRangeConditionTest.php
├── Filter/
│   ├── MatcherTest.php
│   ├── AllProductsFilterTest.php
│   └── CategoriesFilterTest.php
└── Engine/
    ├── CalculatorTest.php               # 多規則 priority / exclusive
    ├── AggregatorTest.php
    └── ExclusivityResolverTest.php

tests/Stub/
└── InMemoryDatabaseAdapter.php          # 測試用 stub，模擬 wpdb 行為
```

修改：
- `src/Plugin.php` — 在 boot 時建立 Container，註冊 strategies、conditions、filters，接上 CartHooks
- `composer.json` — 加入 `brain/monkey` dev 相依

**分工原則**：

- **Persistence** 是 Repository 的底層抽象，Repository 不直接觸碰 global `$wpdb`，方便單元測試
- **Condition / Filter** 與 Phase 1 的 Strategy 同構：Interface + Registry + 若干實作類別
- **Engine** 內部三類各司其職：Calculator 跑流程、Aggregator 套到 WC_Cart、ExclusivityResolver 處理優先序互斥
- **Integration** 是對 WooCommerce API 的唯一接觸點，隔離外部依賴

---

## Ground Rules (同 Phase 1)

- 所有 PHP 檔首行：`<?php declare(strict_types=1);`
- PSR-12、4 空白縮排
- PHP 7.4 相容
- Test 先行（TDD）：紅 → 實作 → 綠 → commit
- 每個 task 獨立 commit，message 用 Conventional Commits
- Git commit 用 `git -c user.email=luke@local -c user.name=Luke commit -m "..."`

---

## Tasks

### Task 1: 安裝 Brain\Monkey + tests/Stub 目錄

**Files:**
- Modify: `composer.json`
- Create: `tests/Stub/.gitkeep`

- [ ] **Step 1:** 在 `composer.json` 的 `require-dev` 增加 `brain/monkey`

```json
"require-dev": {
    "brain/monkey": "^2.6",
    "phpunit/phpunit": "^9.6"
}
```

- [ ] **Step 2:** 執行 `composer update brain/monkey --with-dependencies`
  Expected：下載 mockery、brain/monkey，`vendor/brain/monkey` 存在

- [ ] **Step 3:** 建立 `tests/Stub/.gitkeep`（空檔案，讓目錄進 git）

- [ ] **Step 4:** 跑 `vendor/bin/phpunit` 確認 61 tests 仍綠

- [ ] **Step 5:** Commit

```bash
git add composer.json tests/Stub/.gitkeep
git commit -m "chore: add brain/monkey for WP function mocking"
```

> Note: `composer.lock` stays gitignored (as configured in `.gitignore`). Version pinning is handled by the `^2.6` constraint in `composer.json`. Do not commit the lock file in this task.

---

### Task 2: DatabaseAdapter 介面 + WpdbAdapter 實作

**Files:**
- Create: `src/Persistence/DatabaseAdapter.php`
- Create: `src/Persistence/WpdbAdapter.php`

- [ ] **Step 1:** 建立 `src/Persistence/DatabaseAdapter.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Persistence;

interface DatabaseAdapter
{
    /**
     * Run a prepared SELECT and return an array of associative rows.
     *
     * @param string $sql SQL with %s / %d placeholders.
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function selectAll(string $sql, array $params = []): array;

    /**
     * Run a prepared SELECT and return the first row, or null.
     *
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $params = []): ?array;

    /**
     * Insert a row into a table. Returns the inserted id.
     *
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int;

    /**
     * Update rows in a table. Returns affected row count.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update(string $table, array $data, array $where): int;

    /**
     * Delete rows in a table. Returns affected row count.
     *
     * @param array<string, mixed> $where
     */
    public function delete(string $table, array $where): int;

    /**
     * Fully-qualified table name with the WP table prefix.
     */
    public function table(string $name): string;
}
```

- [ ] **Step 2:** 建立 `src/Persistence/WpdbAdapter.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Persistence;

use wpdb;

final class WpdbAdapter implements DatabaseAdapter
{
    private wpdb $wpdb;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function selectAll(string $sql, array $params = []): array
    {
        $prepared = $this->prepare($sql, $params);
        $rows = $this->wpdb->get_results($prepared, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public function selectOne(string $sql, array $params = []): ?array
    {
        $prepared = $this->prepare($sql, $params);
        $row = $this->wpdb->get_row($prepared, ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function insert(string $table, array $data): int
    {
        $this->wpdb->insert($table, $data);
        return (int) $this->wpdb->insert_id;
    }

    public function update(string $table, array $data, array $where): int
    {
        $affected = $this->wpdb->update($table, $data, $where);
        return (int) ($affected ?: 0);
    }

    public function delete(string $table, array $where): int
    {
        $affected = $this->wpdb->delete($table, $where);
        return (int) ($affected ?: 0);
    }

    public function table(string $name): string
    {
        return $this->wpdb->prefix . $name;
    }

    private function prepare(string $sql, array $params): string
    {
        if ($params === []) {
            return $sql;
        }
        return $this->wpdb->prepare($sql, $params);
    }
}
```

- [ ] **Step 3:** `php -l src/Persistence/DatabaseAdapter.php src/Persistence/WpdbAdapter.php`
  Expected：no syntax errors

- [ ] **Step 4:** Commit

```bash
git add src/Persistence/
git commit -m "feat: add DatabaseAdapter interface and WpdbAdapter"
```

---

### Task 3: InMemoryDatabaseAdapter stub

**Files:**
- Create: `tests/Stub/InMemoryDatabaseAdapter.php`

這個 stub 只支援 Repository 會用到的操作：insert（auto-increment id）、update、delete（by where）、selectAll（預設不 parse SQL，改吃 callback-based filter）。為了保持簡單，selectAll 我們用「全表掃描 + closure 過濾」的設計，由 Repository 測試決定過濾邏輯。

- [ ] **Step 1:** 建立 `tests/Stub/InMemoryDatabaseAdapter.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Stub;

use PowerDiscount\Persistence\DatabaseAdapter;

/**
 * Minimal in-memory stand-in for wpdb. Tests populate rows directly via
 * insert() and then query them by primary key (id) or by full-table
 * scan using a closure passed as the first SQL param.
 *
 * This is NOT a real SQL engine. It exists purely so Repository tests can
 * assert on CRUD calls without a real database.
 */
final class InMemoryDatabaseAdapter implements DatabaseAdapter
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $tables = [];

    /** @var array<string, int> */
    private array $autoIncrement = [];

    public string $prefix = 'wp_';

    public function selectAll(string $sql, array $params = []): array
    {
        // Tests pass a closure through $params[0] for custom filtering.
        // SQL tag like "SELECT_ALL_FROM:table_name" determines the table.
        [$table, $filter] = $this->parseCustomQuery($sql, $params);
        $rows = array_values($this->tables[$table] ?? []);
        if ($filter !== null) {
            $rows = array_values(array_filter($rows, $filter));
        }
        return $rows;
    }

    public function selectOne(string $sql, array $params = []): ?array
    {
        $rows = $this->selectAll($sql, $params);
        return $rows[0] ?? null;
    }

    public function insert(string $table, array $data): int
    {
        $this->tables[$table] = $this->tables[$table] ?? [];
        $this->autoIncrement[$table] = ($this->autoIncrement[$table] ?? 0) + 1;
        $id = $this->autoIncrement[$table];
        $row = array_merge(['id' => $id], $data);
        $this->tables[$table][$id] = $row;
        return $id;
    }

    public function update(string $table, array $data, array $where): int
    {
        if (!isset($this->tables[$table])) {
            return 0;
        }
        $affected = 0;
        foreach ($this->tables[$table] as $id => $row) {
            foreach ($where as $k => $v) {
                if (!array_key_exists($k, $row) || $row[$k] !== $v) {
                    continue 2;
                }
            }
            $this->tables[$table][$id] = array_merge($row, $data);
            $affected++;
        }
        return $affected;
    }

    public function delete(string $table, array $where): int
    {
        if (!isset($this->tables[$table])) {
            return 0;
        }
        $affected = 0;
        foreach ($this->tables[$table] as $id => $row) {
            foreach ($where as $k => $v) {
                if (!array_key_exists($k, $row) || $row[$k] !== $v) {
                    continue 2;
                }
            }
            unset($this->tables[$table][$id]);
            $affected++;
        }
        return $affected;
    }

    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    /**
     * @return array{0:string,1:callable|null}
     */
    private function parseCustomQuery(string $sql, array $params): array
    {
        // Convention: SQL format "SELECT_ALL_FROM:{table}" and $params[0] is optional closure
        if (strpos($sql, 'SELECT_ALL_FROM:') === 0) {
            $table = substr($sql, strlen('SELECT_ALL_FROM:'));
            $filter = $params[0] ?? null;
            return [$table, is_callable($filter) ? $filter : null];
        }
        return ['', null];
    }
}
```

- [ ] **Step 2:** `php -l tests/Stub/InMemoryDatabaseAdapter.php`
  Expected：no syntax errors

- [ ] **Step 3:** Commit

```bash
git add tests/Stub/InMemoryDatabaseAdapter.php
git commit -m "test: add in-memory DatabaseAdapter stub"
```

---

### Task 4: JsonSerializer (TDD)

**Files:**
- Create: `tests/Unit/Persistence/JsonSerializerTest.php`
- Create: `src/Persistence/JsonSerializer.php`

JsonSerializer 負責 `filters` / `conditions` / `config` 欄位的 encode/decode，處理 JSON 錯誤與空字串。

- [ ] **Step 1:** Write test

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Persistence;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Persistence\JsonSerializer;

final class JsonSerializerTest extends TestCase
{
    public function testEncodeArray(): void
    {
        $json = JsonSerializer::encode(['a' => 1, 'b' => ['c' => 2]]);
        self::assertSame('{"a":1,"b":{"c":2}}', $json);
    }

    public function testEncodeEmptyArrayProducesEmptyObject(): void
    {
        self::assertSame('[]', JsonSerializer::encode([]));
    }

    public function testDecodeValidJson(): void
    {
        self::assertSame(['x' => 1], JsonSerializer::decode('{"x":1}'));
    }

    public function testDecodeEmptyOrNullReturnsEmptyArray(): void
    {
        self::assertSame([], JsonSerializer::decode(''));
        self::assertSame([], JsonSerializer::decode(null));
    }

    public function testDecodeInvalidJsonReturnsEmptyArray(): void
    {
        self::assertSame([], JsonSerializer::decode('{not json'));
    }

    public function testDecodeNonArrayJsonReturnsEmptyArray(): void
    {
        self::assertSame([], JsonSerializer::decode('"string"'));
        self::assertSame([], JsonSerializer::decode('42'));
    }
}
```

- [ ] **Step 2:** Run test — expect fail

Run: `vendor/bin/phpunit tests/Unit/Persistence/JsonSerializerTest.php`

- [ ] **Step 3:** Implement `src/Persistence/JsonSerializer.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Persistence;

final class JsonSerializer
{
    public static function encode(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? '[]' : $json;
    }

    public static function decode(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
```

- [ ] **Step 4:** Re-run test — expect 6 passes

- [ ] **Step 5:** Commit

```bash
git add src/Persistence/JsonSerializer.php tests/Unit/Persistence/JsonSerializerTest.php
git commit -m "feat: add JsonSerializer with tests"
```

---

### Task 5: RuleRepository (TDD)

**Files:**
- Create: `tests/Unit/Repository/RuleRepositoryTest.php`
- Create: `src/Repository/RuleRepository.php`

RuleRepository 提供：`insert`、`update`、`delete`、`findById`、`getActiveRules`（由 status/priority 排序，也過濾 date range）、`incrementUsedCount`。

- [ ] **Step 1:** Write test

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Domain\RuleStatus;
use PowerDiscount\Repository\RuleRepository;
use PowerDiscount\Tests\Stub\InMemoryDatabaseAdapter;

final class RuleRepositoryTest extends TestCase
{
    private InMemoryDatabaseAdapter $db;
    private RuleRepository $repo;

    protected function setUp(): void
    {
        $this->db = new InMemoryDatabaseAdapter();
        $this->repo = new RuleRepository($this->db);
    }

    public function testInsertAndFindById(): void
    {
        $rule = new Rule([
            'title' => 'Test',
            'type' => 'simple',
            'status' => RuleStatus::ENABLED,
            'priority' => 5,
            'config' => ['method' => 'percentage', 'value' => 10],
            'filters' => [],
            'conditions' => [],
        ]);

        $id = $this->repo->insert($rule);
        self::assertGreaterThan(0, $id);

        $found = $this->repo->findById($id);
        self::assertNotNull($found);
        self::assertSame('Test', $found->getTitle());
        self::assertSame('simple', $found->getType());
        self::assertSame(5, $found->getPriority());
        self::assertSame(10, $found->getConfig()['value']);
    }

    public function testFindByIdReturnsNullWhenMissing(): void
    {
        self::assertNull($this->repo->findById(999));
    }

    public function testUpdateExistingRule(): void
    {
        $rule = new Rule(['title' => 'Old', 'type' => 'simple', 'config' => []]);
        $id = $this->repo->insert($rule);

        $updated = new Rule(['id' => $id, 'title' => 'New', 'type' => 'simple', 'config' => ['v' => 1]]);
        $affected = $this->repo->update($updated);
        self::assertSame(1, $affected);

        $found = $this->repo->findById($id);
        self::assertSame('New', $found->getTitle());
        self::assertSame(['v' => 1], $found->getConfig());
    }

    public function testDelete(): void
    {
        $id = $this->repo->insert(new Rule(['title' => 'x', 'type' => 'simple']));
        $affected = $this->repo->delete($id);
        self::assertSame(1, $affected);
        self::assertNull($this->repo->findById($id));
    }

    public function testGetActiveRulesExcludesDisabled(): void
    {
        $this->repo->insert(new Rule(['title' => 'A', 'type' => 'simple', 'status' => RuleStatus::ENABLED, 'priority' => 10]));
        $this->repo->insert(new Rule(['title' => 'B', 'type' => 'simple', 'status' => RuleStatus::DISABLED, 'priority' => 5]));
        $this->repo->insert(new Rule(['title' => 'C', 'type' => 'simple', 'status' => RuleStatus::ENABLED, 'priority' => 20]));

        $active = $this->repo->getActiveRules();
        self::assertCount(2, $active);
        // Priority ASC ordering: A (10) before C (20)
        self::assertSame('A', $active[0]->getTitle());
        self::assertSame('C', $active[1]->getTitle());
    }

    public function testIncrementUsedCount(): void
    {
        $id = $this->repo->insert(new Rule([
            'title' => 'x', 'type' => 'simple',
            'usage_limit' => 100, 'used_count' => 5,
        ]));

        $this->repo->incrementUsedCount($id);

        $found = $this->repo->findById($id);
        self::assertSame(6, $found->getUsedCount());
    }
}
```

- [ ] **Step 2:** Run test — expect class-not-found failure

- [ ] **Step 3:** Implement `src/Repository/RuleRepository.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Repository;

use PowerDiscount\Domain\Rule;
use PowerDiscount\Domain\RuleStatus;
use PowerDiscount\Persistence\DatabaseAdapter;
use PowerDiscount\Persistence\JsonSerializer;

final class RuleRepository
{
    private const TABLE = 'pd_rules';

    private DatabaseAdapter $db;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
    }

    public function insert(Rule $rule): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $row = $this->toRow($rule);
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        return $this->db->insert($this->table(), $row);
    }

    public function update(Rule $rule): int
    {
        $row = $this->toRow($rule);
        $row['updated_at'] = gmdate('Y-m-d H:i:s');
        return $this->db->update($this->table(), $row, ['id' => $rule->getId()]);
    }

    public function delete(int $id): int
    {
        return $this->db->delete($this->table(), ['id' => $id]);
    }

    public function findById(int $id): ?Rule
    {
        $rows = $this->db->selectAll('SELECT_ALL_FROM:' . $this->table(), [
            static function (array $row) use ($id): bool {
                return (int) $row['id'] === $id;
            },
        ]);
        if ($rows === []) {
            return null;
        }
        return $this->hydrate($rows[0]);
    }

    /**
     * @return Rule[] ordered by priority ASC, id ASC
     */
    public function getActiveRules(): array
    {
        $rows = $this->db->selectAll('SELECT_ALL_FROM:' . $this->table(), [
            static function (array $row): bool {
                return (int) $row['status'] === RuleStatus::ENABLED;
            },
        ]);
        usort($rows, static function (array $a, array $b): int {
            $prio = ((int) $a['priority']) <=> ((int) $b['priority']);
            if ($prio !== 0) {
                return $prio;
            }
            return ((int) $a['id']) <=> ((int) $b['id']);
        });
        return array_map([$this, 'hydrate'], $rows);
    }

    public function incrementUsedCount(int $id): void
    {
        $rule = $this->findById($id);
        if ($rule === null) {
            return;
        }
        $this->db->update(
            $this->table(),
            ['used_count' => $rule->getUsedCount() + 1],
            ['id' => $id]
        );
    }

    private function table(): string
    {
        return $this->db->table(self::TABLE);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Rule $rule): array
    {
        return [
            'title'       => $rule->getTitle(),
            'type'        => $rule->getType(),
            'status'      => $rule->getStatus(),
            'priority'    => $rule->getPriority(),
            'exclusive'   => $rule->isExclusive() ? 1 : 0,
            'starts_at'   => null, // TODO: expose getters on Rule for raw date if needed later
            'ends_at'     => null,
            'usage_limit' => $rule->getUsageLimit(),
            'used_count'  => $rule->getUsedCount(),
            'filters'     => JsonSerializer::encode($rule->getFilters()),
            'conditions'  => JsonSerializer::encode($rule->getConditions()),
            'config'      => JsonSerializer::encode($rule->getConfig()),
            'label'       => $rule->getLabel(),
            'notes'       => $rule->getNotes(),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Rule
    {
        return new Rule([
            'id'          => (int) ($row['id'] ?? 0),
            'title'       => (string) ($row['title'] ?? ''),
            'type'        => (string) ($row['type'] ?? ''),
            'status'      => (int) ($row['status'] ?? RuleStatus::ENABLED),
            'priority'    => (int) ($row['priority'] ?? 10),
            'exclusive'   => (bool) ($row['exclusive'] ?? false),
            'starts_at'   => $row['starts_at'] ?? null,
            'ends_at'     => $row['ends_at'] ?? null,
            'usage_limit' => $row['usage_limit'] ?? null,
            'used_count'  => (int) ($row['used_count'] ?? 0),
            'filters'     => JsonSerializer::decode((string) ($row['filters'] ?? '')),
            'conditions'  => JsonSerializer::decode((string) ($row['conditions'] ?? '')),
            'config'      => JsonSerializer::decode((string) ($row['config'] ?? '')),
            'label'       => $row['label'] ?? null,
            'notes'       => $row['notes'] ?? null,
        ]);
    }
}
```

- [ ] **Step 4:** Re-run test — expect 6 passes

- [ ] **Step 5:** Commit

```bash
git add src/Repository/RuleRepository.php tests/Unit/Repository/RuleRepositoryTest.php
git commit -m "feat: add RuleRepository with DatabaseAdapter + unit tests"
```

---

### Task 6: OrderDiscountRepository (TDD)

**Files:**
- Create: `tests/Unit/Repository/OrderDiscountRepositoryTest.php`
- Create: `src/Repository/OrderDiscountRepository.php`

- [ ] **Step 1:** Write test

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Repository\OrderDiscountRepository;
use PowerDiscount\Tests\Stub\InMemoryDatabaseAdapter;

final class OrderDiscountRepositoryTest extends TestCase
{
    public function testRecordInsertsRowPerResult(): void
    {
        $db = new InMemoryDatabaseAdapter();
        $repo = new OrderDiscountRepository($db);

        $results = [
            new DiscountResult(1, 'simple', 'product', 100.0, [10, 20], '10% off', []),
            new DiscountResult(2, 'cart', 'cart', 50.0, [], 'Free shipping', ['m' => 'flat_total']),
        ];

        $repo->record(999, $results, ['1' => 'Rule A', '2' => 'Rule B']);

        $found = $repo->findByOrderId(999);
        self::assertCount(2, $found);
        self::assertSame('Rule A', $found[0]['rule_title']);
        self::assertSame('simple', $found[0]['rule_type']);
        self::assertSame('product', $found[0]['scope']);
        self::assertSame(100.0, $found[0]['discount_amount']);
        self::assertSame(999, $found[0]['order_id']);
    }

    public function testRecordSkipsZeroResults(): void
    {
        $db = new InMemoryDatabaseAdapter();
        $repo = new OrderDiscountRepository($db);

        $results = [
            new DiscountResult(1, 'simple', 'product', 0.0, [], null, []),
        ];
        $repo->record(1, $results, ['1' => 'Zero']);

        self::assertCount(0, $repo->findByOrderId(1));
    }

    public function testFindByOrderIdReturnsEmptyWhenNone(): void
    {
        $repo = new OrderDiscountRepository(new InMemoryDatabaseAdapter());
        self::assertSame([], $repo->findByOrderId(42));
    }
}
```

- [ ] **Step 2:** Run test — expect fail

- [ ] **Step 3:** Implement `src/Repository/OrderDiscountRepository.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Repository;

use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Persistence\DatabaseAdapter;
use PowerDiscount\Persistence\JsonSerializer;

final class OrderDiscountRepository
{
    private const TABLE = 'pd_order_discounts';

    private DatabaseAdapter $db;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
    }

    /**
     * @param DiscountResult[] $results
     * @param array<int|string, string> $ruleTitlesById  id => title snapshot
     */
    public function record(int $orderId, array $results, array $ruleTitlesById): void
    {
        $now = gmdate('Y-m-d H:i:s');
        foreach ($results as $result) {
            if (!$result->hasDiscount()) {
                continue;
            }
            $this->db->insert($this->db->table(self::TABLE), [
                'order_id'        => $orderId,
                'rule_id'         => $result->getRuleId(),
                'rule_title'      => (string) ($ruleTitlesById[$result->getRuleId()] ?? ''),
                'rule_type'       => $result->getRuleType(),
                'discount_amount' => $result->getAmount(),
                'scope'           => $result->getScope(),
                'meta'            => JsonSerializer::encode($result->getMeta()),
                'created_at'      => $now,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByOrderId(int $orderId): array
    {
        return $this->db->selectAll('SELECT_ALL_FROM:' . $this->db->table(self::TABLE), [
            static function (array $row) use ($orderId): bool {
                return (int) $row['order_id'] === $orderId;
            },
        ]);
    }
}
```

- [ ] **Step 4:** Re-run test — expect 3 passes

- [ ] **Step 5:** Commit

```bash
git add src/Repository/OrderDiscountRepository.php tests/Unit/Repository/OrderDiscountRepositoryTest.php
git commit -m "feat: add OrderDiscountRepository with unit tests"
```

---

### Task 7: ConditionInterface + ConditionRegistry

**Files:**
- Create: `src/Condition/ConditionInterface.php`
- Create: `src/Condition/ConditionRegistry.php`

- [ ] **Step 1:** 建立 `src/Condition/ConditionInterface.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Condition;

use PowerDiscount\Domain\CartContext;

interface ConditionInterface
{
    /** Which condition type this handles, e.g. "cart_subtotal". */
    public function type(): string;

    /**
     * Evaluate this condition against the cart.
     *
     * @param array<string, mixed> $config  Condition config from rule JSON.
     */
    public function evaluate(array $config, CartContext $context): bool;
}
```

- [ ] **Step 2:** 建立 `src/Condition/ConditionRegistry.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Condition;

final class ConditionRegistry
{
    /** @var array<string, ConditionInterface> */
    private array $conditions = [];

    public function register(ConditionInterface $condition): void
    {
        $this->conditions[$condition->type()] = $condition;
    }

    public function resolve(string $type): ?ConditionInterface
    {
        return $this->conditions[$type] ?? null;
    }

    /** @return ConditionInterface[] */
    public function all(): array
    {
        return array_values($this->conditions);
    }
}
```

- [ ] **Step 3:** `php -l` both files

- [ ] **Step 4:** Commit

```bash
git add src/Condition/ConditionInterface.php src/Condition/ConditionRegistry.php
git commit -m "feat: add ConditionInterface and ConditionRegistry"
```

---

### Task 8: CartSubtotalCondition (TDD)

**Files:**
- Create: `tests/Unit/Condition/CartSubtotalConditionTest.php`
- Create: `src/Condition/CartSubtotalCondition.php`

- [ ] **Step 1:** Write test

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Condition;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Condition\CartSubtotalCondition;
use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;

final class CartSubtotalConditionTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('cart_subtotal', (new CartSubtotalCondition())->type());
    }

    public function testGreaterThanOrEqual(): void
    {
        $ctx = new CartContext([new CartItem(1, 'A', 500.0, 2, [])]); // subtotal 1000
        $c = new CartSubtotalCondition();

        self::assertTrue($c->evaluate(['operator' => '>=', 'value' => 1000], $ctx));
        self::assertTrue($c->evaluate(['operator' => '>=', 'value' => 999], $ctx));
        self::assertFalse($c->evaluate(['operator' => '>=', 'value' => 1001], $ctx));
    }

    public function testAllOperators(): void
    {
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 5, [])]); // subtotal 500
        $c = new CartSubtotalCondition();

        self::assertTrue($c->evaluate(['operator' => '>', 'value' => 499], $ctx));
        self::assertFalse($c->evaluate(['operator' => '>', 'value' => 500], $ctx));

        self::assertTrue($c->evaluate(['operator' => '=', 'value' => 500], $ctx));
        self::assertFalse($c->evaluate(['operator' => '=', 'value' => 499], $ctx));

        self::assertTrue($c->evaluate(['operator' => '<=', 'value' => 500], $ctx));
        self::assertFalse($c->evaluate(['operator' => '<=', 'value' => 499], $ctx));

        self::assertTrue($c->evaluate(['operator' => '<', 'value' => 501], $ctx));
        self::assertFalse($c->evaluate(['operator' => '<', 'value' => 500], $ctx));

        self::assertTrue($c->evaluate(['operator' => '!=', 'value' => 499], $ctx));
        self::assertFalse($c->evaluate(['operator' => '!=', 'value' => 500], $ctx));
    }

    public function testInvalidOperatorReturnsFalse(): void
    {
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);
        self::assertFalse((new CartSubtotalCondition())->evaluate(['operator' => '~~', 'value' => 1], $ctx));
    }

    public function testMissingConfigReturnsFalse(): void
    {
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);
        self::assertFalse((new CartSubtotalCondition())->evaluate([], $ctx));
    }
}
```

- [ ] **Step 2:** Run test — expect fail

- [ ] **Step 3:** Implement `src/Condition/CartSubtotalCondition.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Condition;

use PowerDiscount\Domain\CartContext;

final class CartSubtotalCondition implements ConditionInterface
{
    public function type(): string
    {
        return 'cart_subtotal';
    }

    public function evaluate(array $config, CartContext $context): bool
    {
        if (!isset($config['operator'], $config['value'])) {
            return false;
        }
        $operator = (string) $config['operator'];
        $target = (float) $config['value'];
        $subtotal = $context->getSubtotal();

        switch ($operator) {
            case '>=': return $subtotal >= $target;
            case '>':  return $subtotal >  $target;
            case '=':  return $subtotal === $target;
            case '<=': return $subtotal <= $target;
            case '<':  return $subtotal <  $target;
            case '!=': return $subtotal !== $target;
        }
        return false;
    }
}
```

- [ ] **Step 4:** Re-run test — expect 5 passes

- [ ] **Step 5:** Commit

```bash
git add src/Condition/CartSubtotalCondition.php tests/Unit/Condition/CartSubtotalConditionTest.php
git commit -m "feat: add CartSubtotalCondition with tests"
```

---

### Task 9: DateRangeCondition (TDD)

**Files:**
- Create: `tests/Unit/Condition/DateRangeConditionTest.php`
- Create: `src/Condition/DateRangeCondition.php`

DateRangeCondition 檢查「當下時刻是否在 `from`—`to` 之間」。為方便測試，constructor 可注入 `Closure $now`。

- [ ] **Step 1:** Write test

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Condition;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Condition\DateRangeCondition;
use PowerDiscount\Domain\CartContext;

final class DateRangeConditionTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('date_range', (new DateRangeCondition())->type());
    }

    public function testWithinRange(): void
    {
        $now = static function (): int { return strtotime('2026-04-15 12:00:00 UTC'); };
        $c = new DateRangeCondition($now);

        self::assertTrue($c->evaluate([
            'from' => '2026-04-01 00:00:00',
            'to'   => '2026-04-30 23:59:59',
        ], new CartContext([])));
    }

    public function testBeforeRange(): void
    {
        $now = static function (): int { return strtotime('2026-03-15 00:00:00 UTC'); };
        $c = new DateRangeCondition($now);

        self::assertFalse($c->evaluate([
            'from' => '2026-04-01 00:00:00',
            'to'   => '2026-04-30 23:59:59',
        ], new CartContext([])));
    }

    public function testAfterRange(): void
    {
        $now = static function (): int { return strtotime('2026-05-15 00:00:00 UTC'); };
        $c = new DateRangeCondition($now);

        self::assertFalse($c->evaluate([
            'from' => '2026-04-01 00:00:00',
            'to'   => '2026-04-30 23:59:59',
        ], new CartContext([])));
    }

    public function testOpenEndedStart(): void
    {
        $now = static function (): int { return strtotime('2026-04-15 12:00:00 UTC'); };
        $c = new DateRangeCondition($now);

        self::assertTrue($c->evaluate([
            'to' => '2026-04-30 23:59:59',
        ], new CartContext([])));
    }

    public function testOpenEndedEnd(): void
    {
        $now = static function (): int { return strtotime('2026-04-15 12:00:00 UTC'); };
        $c = new DateRangeCondition($now);

        self::assertTrue($c->evaluate([
            'from' => '2026-04-01 00:00:00',
        ], new CartContext([])));
    }

    public function testEmptyConfigIsAlwaysTrue(): void
    {
        $c = new DateRangeCondition(static function (): int { return 0; });
        self::assertTrue($c->evaluate([], new CartContext([])));
    }

    public function testInvalidDateStringReturnsFalse(): void
    {
        $now = static function (): int { return strtotime('2026-04-15 12:00:00 UTC'); };
        $c = new DateRangeCondition($now);

        self::assertFalse($c->evaluate(['from' => 'not-a-date', 'to' => 'still-not'], new CartContext([])));
    }
}
```

- [ ] **Step 2:** Run test — expect fail

- [ ] **Step 3:** Implement `src/Condition/DateRangeCondition.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Condition;

use Closure;
use PowerDiscount\Domain\CartContext;

final class DateRangeCondition implements ConditionInterface
{
    /** @var Closure(): int */
    private Closure $now;

    public function __construct(?Closure $now = null)
    {
        $this->now = $now ?? static function (): int { return time(); };
    }

    public function type(): string
    {
        return 'date_range';
    }

    public function evaluate(array $config, CartContext $context): bool
    {
        $now = ($this->now)();
        $from = isset($config['from']) && $config['from'] !== '' ? strtotime((string) $config['from']) : null;
        $to   = isset($config['to'])   && $config['to']   !== '' ? strtotime((string) $config['to'])   : null;

        if (isset($config['from']) && $config['from'] !== '' && $from === false) {
            return false;
        }
        if (isset($config['to']) && $config['to'] !== '' && $to === false) {
            return false;
        }

        if ($from !== null && $from !== false && $now < $from) {
            return false;
        }
        if ($to !== null && $to !== false && $now > $to) {
            return false;
        }
        return true;
    }
}
```

- [ ] **Step 4:** Re-run test — expect 7 passes

- [ ] **Step 5:** Commit

```bash
git add src/Condition/DateRangeCondition.php tests/Unit/Condition/DateRangeConditionTest.php
git commit -m "feat: add DateRangeCondition with tests"
```

---

### Task 10: Condition Evaluator (AND/OR) (TDD)

**Files:**
- Create: `tests/Unit/Condition/EvaluatorTest.php`
- Create: `src/Condition/Evaluator.php`

Evaluator 把 rule 的 `conditions` JSON（格式：`{logic: and|or, items: [...]}`）逐項送給 ConditionRegistry 解析並組合。

- [ ] **Step 1:** Write test

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Condition;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Condition\ConditionInterface;
use PowerDiscount\Condition\ConditionRegistry;
use PowerDiscount\Condition\Evaluator;
use PowerDiscount\Domain\CartContext;

final class EvaluatorTest extends TestCase
{
    public function testEmptyConditionsReturnsTrue(): void
    {
        $eval = new Evaluator(new ConditionRegistry());
        self::assertTrue($eval->evaluate([], new CartContext([])));
    }

    public function testAndLogicAllTrue(): void
    {
        $registry = new ConditionRegistry();
        $registry->register($this->stub('a', true));
        $registry->register($this->stub('b', true));
        $eval = new Evaluator($registry);

        self::assertTrue($eval->evaluate([
            'logic' => 'and',
            'items' => [['type' => 'a'], ['type' => 'b']],
        ], new CartContext([])));
    }

    public function testAndLogicOneFalse(): void
    {
        $registry = new ConditionRegistry();
        $registry->register($this->stub('a', true));
        $registry->register($this->stub('b', false));
        $eval = new Evaluator($registry);

        self::assertFalse($eval->evaluate([
            'logic' => 'and',
            'items' => [['type' => 'a'], ['type' => 'b']],
        ], new CartContext([])));
    }

    public function testOrLogicOneTrue(): void
    {
        $registry = new ConditionRegistry();
        $registry->register($this->stub('a', false));
        $registry->register($this->stub('b', true));
        $eval = new Evaluator($registry);

        self::assertTrue($eval->evaluate([
            'logic' => 'or',
            'items' => [['type' => 'a'], ['type' => 'b']],
        ], new CartContext([])));
    }

    public function testOrLogicAllFalse(): void
    {
        $registry = new ConditionRegistry();
        $registry->register($this->stub('a', false));
        $registry->register($this->stub('b', false));
        $eval = new Evaluator($registry);

        self::assertFalse($eval->evaluate([
            'logic' => 'or',
            'items' => [['type' => 'a'], ['type' => 'b']],
        ], new CartContext([])));
    }

    public function testUnknownTypeFailsSafely(): void
    {
        $registry = new ConditionRegistry();
        // No conditions registered
        $eval = new Evaluator($registry);

        // AND: unknown item → false
        self::assertFalse($eval->evaluate([
            'logic' => 'and',
            'items' => [['type' => 'ghost']],
        ], new CartContext([])));

        // OR: unknown item doesn't flip to true
        self::assertFalse($eval->evaluate([
            'logic' => 'or',
            'items' => [['type' => 'ghost']],
        ], new CartContext([])));
    }

    public function testDefaultLogicIsAnd(): void
    {
        $registry = new ConditionRegistry();
        $registry->register($this->stub('a', true));
        $registry->register($this->stub('b', false));
        $eval = new Evaluator($registry);

        self::assertFalse($eval->evaluate([
            'items' => [['type' => 'a'], ['type' => 'b']],
        ], new CartContext([])));
    }

    private function stub(string $type, bool $result): ConditionInterface
    {
        return new class($type, $result) implements ConditionInterface {
            private string $type;
            private bool $result;
            public function __construct(string $type, bool $result)
            {
                $this->type = $type;
                $this->result = $result;
            }
            public function type(): string { return $this->type; }
            public function evaluate(array $config, CartContext $context): bool
            {
                return $this->result;
            }
        };
    }
}
```

- [ ] **Step 2:** Run test — expect fail

- [ ] **Step 3:** Implement `src/Condition/Evaluator.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Condition;

use PowerDiscount\Domain\CartContext;

final class Evaluator
{
    private ConditionRegistry $registry;

    public function __construct(ConditionRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param array<string, mixed> $conditions  { logic: 'and'|'or', items: [...] }
     */
    public function evaluate(array $conditions, CartContext $context): bool
    {
        $items = $conditions['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return true;
        }

        $logic = strtolower((string) ($conditions['logic'] ?? 'and'));
        if ($logic !== 'or') {
            $logic = 'and';
        }

        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['type'])) {
                $result = false;
            } else {
                $condition = $this->registry->resolve((string) $item['type']);
                $result = $condition === null ? false : $condition->evaluate($item, $context);
            }

            if ($logic === 'and' && !$result) {
                return false;
            }
            if ($logic === 'or' && $result) {
                return true;
            }
        }

        return $logic === 'and';
    }
}
```

- [ ] **Step 4:** Re-run — expect 7 passes

- [ ] **Step 5:** Commit

```bash
git add src/Condition/Evaluator.php tests/Unit/Condition/EvaluatorTest.php
git commit -m "feat: add Condition Evaluator with AND/OR logic"
```

---

### Task 11: FilterInterface + FilterRegistry

**Files:**
- Create: `src/Filter/FilterInterface.php`
- Create: `src/Filter/FilterRegistry.php`

- [ ] **Step 1:** 建立 `src/Filter/FilterInterface.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Filter;

use PowerDiscount\Domain\CartItem;

interface FilterInterface
{
    public function type(): string;

    /**
     * @param array<string, mixed> $config
     */
    public function matches(array $config, CartItem $item): bool;
}
```

- [ ] **Step 2:** 建立 `src/Filter/FilterRegistry.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Filter;

final class FilterRegistry
{
    /** @var array<string, FilterInterface> */
    private array $filters = [];

    public function register(FilterInterface $filter): void
    {
        $this->filters[$filter->type()] = $filter;
    }

    public function resolve(string $type): ?FilterInterface
    {
        return $this->filters[$type] ?? null;
    }

    /** @return FilterInterface[] */
    public function all(): array
    {
        return array_values($this->filters);
    }
}
```

- [ ] **Step 3:** `php -l` both files

- [ ] **Step 4:** Commit

```bash
git add src/Filter/FilterInterface.php src/Filter/FilterRegistry.php
git commit -m "feat: add FilterInterface and FilterRegistry"
```

---

### Task 12: AllProductsFilter + CategoriesFilter (TDD)

**Files:**
- Create: `tests/Unit/Filter/AllProductsFilterTest.php`
- Create: `src/Filter/AllProductsFilter.php`
- Create: `tests/Unit/Filter/CategoriesFilterTest.php`
- Create: `src/Filter/CategoriesFilter.php`

Combined task — both filters are small.

- [ ] **Step 1:** Write `tests/Unit/Filter/AllProductsFilterTest.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Filter;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Filter\AllProductsFilter;

final class AllProductsFilterTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('all_products', (new AllProductsFilter())->type());
    }

    public function testAlwaysMatches(): void
    {
        $f = new AllProductsFilter();
        self::assertTrue($f->matches([], new CartItem(1, 'A', 100.0, 1, [])));
        self::assertTrue($f->matches([], new CartItem(99, 'Z', 10.0, 5, [2, 3])));
    }
}
```

- [ ] **Step 2:** Write `tests/Unit/Filter/CategoriesFilterTest.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Filter;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Filter\CategoriesFilter;

final class CategoriesFilterTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('categories', (new CategoriesFilter())->type());
    }

    public function testInListMatches(): void
    {
        $f = new CategoriesFilter();
        $config = ['method' => 'in', 'ids' => [12, 13]];

        self::assertTrue($f->matches($config, new CartItem(1, 'A', 100.0, 1, [12])));
        self::assertTrue($f->matches($config, new CartItem(2, 'B', 100.0, 1, [13, 14])));
        self::assertFalse($f->matches($config, new CartItem(3, 'C', 100.0, 1, [14])));
    }

    public function testNotInListMatches(): void
    {
        $f = new CategoriesFilter();
        $config = ['method' => 'not_in', 'ids' => [99]];

        self::assertTrue($f->matches($config, new CartItem(1, 'A', 100.0, 1, [12])));
        self::assertFalse($f->matches($config, new CartItem(2, 'B', 100.0, 1, [99])));
    }

    public function testEmptyIdsInListNeverMatches(): void
    {
        $f = new CategoriesFilter();
        self::assertFalse($f->matches(['method' => 'in', 'ids' => []], new CartItem(1, 'A', 100.0, 1, [1])));
    }

    public function testEmptyIdsNotInAlwaysMatches(): void
    {
        $f = new CategoriesFilter();
        self::assertTrue($f->matches(['method' => 'not_in', 'ids' => []], new CartItem(1, 'A', 100.0, 1, [1])));
    }

    public function testDefaultMethodIsIn(): void
    {
        $f = new CategoriesFilter();
        self::assertTrue($f->matches(['ids' => [10]], new CartItem(1, 'A', 100.0, 1, [10])));
    }
}
```

- [ ] **Step 3:** Run both — expect fail

- [ ] **Step 4:** Implement `src/Filter/AllProductsFilter.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Filter;

use PowerDiscount\Domain\CartItem;

final class AllProductsFilter implements FilterInterface
{
    public function type(): string
    {
        return 'all_products';
    }

    public function matches(array $config, CartItem $item): bool
    {
        return true;
    }
}
```

- [ ] **Step 5:** Implement `src/Filter/CategoriesFilter.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Filter;

use PowerDiscount\Domain\CartItem;

final class CategoriesFilter implements FilterInterface
{
    public function type(): string
    {
        return 'categories';
    }

    public function matches(array $config, CartItem $item): bool
    {
        $method = strtolower((string) ($config['method'] ?? 'in'));
        $ids = array_map('intval', (array) ($config['ids'] ?? []));

        $matchesList = false;
        foreach ($item->getCategoryIds() as $itemCat) {
            if (in_array($itemCat, $ids, true)) {
                $matchesList = true;
                break;
            }
        }

        if ($method === 'not_in') {
            return !$matchesList;
        }
        return $matchesList;
    }
}
```

- [ ] **Step 6:** Re-run — expect 2 + 6 = 8 passes

- [ ] **Step 7:** Commit

```bash
git add src/Filter/AllProductsFilter.php src/Filter/CategoriesFilter.php tests/Unit/Filter/
git commit -m "feat: add AllProductsFilter and CategoriesFilter with tests"
```

---

### Task 13: Filter Matcher (TDD)

**Files:**
- Create: `tests/Unit/Filter/MatcherTest.php`
- Create: `src/Filter/Matcher.php`

Matcher 吃 rule 的 `filters` JSON（格式：`{logic: and, items: [...]}`，filter 只做 AND）+ 整張 `CartContext`，回傳子集 `CartItem[]`。

- [ ] **Step 1:** Write `tests/Unit/Filter/MatcherTest.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Filter;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Filter\AllProductsFilter;
use PowerDiscount\Filter\CategoriesFilter;
use PowerDiscount\Filter\FilterRegistry;
use PowerDiscount\Filter\Matcher;

final class MatcherTest extends TestCase
{
    public function testEmptyFiltersPassesEverything(): void
    {
        $matcher = new Matcher($this->registry());
        $items = [
            new CartItem(1, 'A', 100.0, 1, [10]),
            new CartItem(2, 'B', 100.0, 1, [20]),
        ];
        $ctx = new CartContext($items);

        $matched = $matcher->matches([], $ctx);
        self::assertCount(2, $matched);
    }

    public function testAllProductsPassesEverything(): void
    {
        $matcher = new Matcher($this->registry());
        $ctx = new CartContext([
            new CartItem(1, 'A', 100.0, 1, [10]),
        ]);

        $matched = $matcher->matches([
            'items' => [['type' => 'all_products']],
        ], $ctx);
        self::assertCount(1, $matched);
    }

    public function testCategoriesFilter(): void
    {
        $matcher = new Matcher($this->registry());
        $ctx = new CartContext([
            new CartItem(1, 'A', 100.0, 1, [10]),
            new CartItem(2, 'B', 100.0, 1, [20]),
            new CartItem(3, 'C', 100.0, 1, [30]),
        ]);

        $matched = $matcher->matches([
            'items' => [['type' => 'categories', 'method' => 'in', 'ids' => [10, 20]]],
        ], $ctx);
        self::assertCount(2, $matched);
    }

    public function testMultipleFiltersAreAnded(): void
    {
        $matcher = new Matcher($this->registry());
        $ctx = new CartContext([
            new CartItem(1, 'A', 100.0, 1, [10]),
            new CartItem(2, 'B', 100.0, 1, [10, 20]),
        ]);

        // in 10 AND not_in 20 → only item 1
        $matched = $matcher->matches([
            'items' => [
                ['type' => 'categories', 'method' => 'in', 'ids' => [10]],
                ['type' => 'categories', 'method' => 'not_in', 'ids' => [20]],
            ],
        ], $ctx);
        self::assertCount(1, $matched);
        self::assertSame(1, $matched[0]->getProductId());
    }

    public function testUnknownFilterTypeFailsSafelyToExclude(): void
    {
        $matcher = new Matcher($this->registry());
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [10])]);

        $matched = $matcher->matches([
            'items' => [['type' => 'ghost']],
        ], $ctx);
        self::assertCount(0, $matched);
    }

    private function registry(): FilterRegistry
    {
        $r = new FilterRegistry();
        $r->register(new AllProductsFilter());
        $r->register(new CategoriesFilter());
        return $r;
    }
}
```

- [ ] **Step 2:** Run — expect fail

- [ ] **Step 3:** Implement `src/Filter/Matcher.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Filter;

use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;

final class Matcher
{
    private FilterRegistry $registry;

    public function __construct(FilterRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param array<string, mixed> $filters  { items: [...] }
     * @return CartItem[]
     */
    public function matches(array $filters, CartContext $context): array
    {
        $items = $filters['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return $context->getItems();
        }

        $matched = [];
        foreach ($context->getItems() as $cartItem) {
            if ($this->itemPassesAll($items, $cartItem)) {
                $matched[] = $cartItem;
            }
        }
        return $matched;
    }

    /**
     * @param array<int, array<string, mixed>> $filterItems
     */
    private function itemPassesAll(array $filterItems, CartItem $item): bool
    {
        foreach ($filterItems as $filterConfig) {
            if (!is_array($filterConfig) || !isset($filterConfig['type'])) {
                return false;
            }
            $filter = $this->registry->resolve((string) $filterConfig['type']);
            if ($filter === null) {
                return false;
            }
            if (!$filter->matches($filterConfig, $item)) {
                return false;
            }
        }
        return true;
    }
}
```

- [ ] **Step 4:** Re-run — expect 5 passes

- [ ] **Step 5:** Commit

```bash
git add src/Filter/Matcher.php tests/Unit/Filter/MatcherTest.php
git commit -m "feat: add Filter Matcher with tests"
```

---

### Task 14: ExclusivityResolver (TDD)

**Files:**
- Create: `tests/Unit/Engine/ExclusivityResolverTest.php`
- Create: `src/Engine/ExclusivityResolver.php`

ExclusivityResolver 只做一件事：判斷「當前已命中規則是否為 exclusive，若是則停止迭代」。非常薄。但抽出來是因為 Phase 3 可能會加「同 priority 互斥」規則。

- [ ] **Step 1:** Write test

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Engine\ExclusivityResolver;

final class ExclusivityResolverTest extends TestCase
{
    public function testShouldStopAfterExclusiveRule(): void
    {
        $resolver = new ExclusivityResolver();
        $exclusive = new Rule(['title' => 'x', 'type' => 'simple', 'exclusive' => true]);
        self::assertTrue($resolver->shouldStopAfter($exclusive));
    }

    public function testShouldNotStopAfterNonExclusive(): void
    {
        $resolver = new ExclusivityResolver();
        $normal = new Rule(['title' => 'x', 'type' => 'simple', 'exclusive' => false]);
        self::assertFalse($resolver->shouldStopAfter($normal));
    }
}
```

- [ ] **Step 2:** Run — expect fail

- [ ] **Step 3:** Implement `src/Engine/ExclusivityResolver.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Engine;

use PowerDiscount\Domain\Rule;

final class ExclusivityResolver
{
    public function shouldStopAfter(Rule $rule): bool
    {
        return $rule->isExclusive();
    }
}
```

- [ ] **Step 4:** Re-run — expect 2 passes

- [ ] **Step 5:** Commit

```bash
git add src/Engine/ExclusivityResolver.php tests/Unit/Engine/ExclusivityResolverTest.php
git commit -m "feat: add ExclusivityResolver with tests"
```

---

### Task 15: Calculator (TDD)

**Files:**
- Create: `tests/Unit/Engine/CalculatorTest.php`
- Create: `src/Engine/Calculator.php`

Calculator 是 Phase 2 的核心。輸入 `Rule[]` + `CartContext`，輸出 `DiscountResult[]`。流程：

1. 遍歷 rules
2. 檢查 `isActiveAt(now)` 與 `!isUsageLimitExhausted()`
3. 用 Evaluator 跑 conditions
4. 用 Matcher 篩 items → 得到 `filteredItems`；若空則 skip
5. 用 filtered items 組成 `filteredContext`
6. 用 StrategyRegistry 解析 → `strategy->apply(rule, filteredContext)`
7. 收集 results；若 rule is exclusive，ExclusivityResolver 判斷停止

- [ ] **Step 1:** Write test

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Condition\ConditionRegistry;
use PowerDiscount\Condition\Evaluator;
use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Engine\Calculator;
use PowerDiscount\Engine\ExclusivityResolver;
use PowerDiscount\Filter\AllProductsFilter;
use PowerDiscount\Filter\CategoriesFilter;
use PowerDiscount\Filter\FilterRegistry;
use PowerDiscount\Filter\Matcher;
use PowerDiscount\Strategy\SimpleStrategy;
use PowerDiscount\Strategy\StrategyRegistry;

final class CalculatorTest extends TestCase
{
    public function testSingleRuleAppliesSimpleDiscount(): void
    {
        $calc = $this->makeCalculator();
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 2, [10])]);
        $rule = new Rule([
            'id' => 1, 'title' => 'r', 'type' => 'simple',
            'config' => ['method' => 'percentage', 'value' => 10],
        ]);

        $results = $calc->run([$rule], $ctx);
        self::assertCount(1, $results);
        self::assertSame(20.0, $results[0]->getAmount()); // 100*0.1*2
    }

    public function testSkipsDisabledOrExpiredRules(): void
    {
        $calc = $this->makeCalculator();
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);

        $expired = new Rule([
            'id' => 1, 'title' => 'r', 'type' => 'simple',
            'config' => ['method' => 'percentage', 'value' => 10],
            'starts_at' => '2020-01-01 00:00:00',
            'ends_at'   => '2020-12-31 23:59:59',
        ]);

        $results = $calc->run([$expired], $ctx);
        self::assertCount(0, $results);
    }

    public function testSkipsUsageLimitExhaustedRules(): void
    {
        $calc = $this->makeCalculator();
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);

        $exhausted = new Rule([
            'id' => 1, 'title' => 'r', 'type' => 'simple',
            'config' => ['method' => 'percentage', 'value' => 10],
            'usage_limit' => 1, 'used_count' => 1,
        ]);

        $results = $calc->run([$exhausted], $ctx);
        self::assertCount(0, $results);
    }

    public function testConditionsGateApplication(): void
    {
        $calc = $this->makeCalculator();
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]); // subtotal 100

        $rule = new Rule([
            'id' => 1, 'title' => 'r', 'type' => 'simple',
            'config' => ['method' => 'percentage', 'value' => 10],
            'conditions' => [
                'logic' => 'and',
                'items' => [['type' => 'cart_subtotal', 'operator' => '>=', 'value' => 500]],
            ],
        ]);

        $results = $calc->run([$rule], $ctx);
        self::assertCount(0, $results);
    }

    public function testFiltersRestrictToCategories(): void
    {
        $calc = $this->makeCalculator();
        $ctx = new CartContext([
            new CartItem(1, 'Coffee', 300.0, 1, [100]), // discounted category
            new CartItem(2, 'Tea',    200.0, 1, [200]),
        ]);

        $rule = new Rule([
            'id' => 1, 'title' => 'coffee 10%', 'type' => 'simple',
            'config' => ['method' => 'percentage', 'value' => 10],
            'filters' => [
                'items' => [['type' => 'categories', 'method' => 'in', 'ids' => [100]]],
            ],
        ]);

        $results = $calc->run([$rule], $ctx);
        self::assertCount(1, $results);
        self::assertSame(30.0, $results[0]->getAmount()); // 10% of 300 only
    }

    public function testExclusiveRuleStopsIteration(): void
    {
        $calc = $this->makeCalculator();
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);

        $first = new Rule([
            'id' => 1, 'title' => 'A', 'type' => 'simple', 'priority' => 1, 'exclusive' => true,
            'config' => ['method' => 'percentage', 'value' => 10],
        ]);
        $second = new Rule([
            'id' => 2, 'title' => 'B', 'type' => 'simple', 'priority' => 2,
            'config' => ['method' => 'percentage', 'value' => 20],
        ]);

        $results = $calc->run([$first, $second], $ctx);
        self::assertCount(1, $results);
        self::assertSame(1, $results[0]->getRuleId());
    }

    public function testEmptyFilterMatchSkipsRule(): void
    {
        $calc = $this->makeCalculator();
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [999])]); // cat 999

        $rule = new Rule([
            'id' => 1, 'title' => 'r', 'type' => 'simple',
            'config' => ['method' => 'percentage', 'value' => 10],
            'filters' => [
                'items' => [['type' => 'categories', 'method' => 'in', 'ids' => [100]]],
            ],
        ]);

        $results = $calc->run([$rule], $ctx);
        self::assertCount(0, $results);
    }

    private function makeCalculator(): Calculator
    {
        $strategies = new StrategyRegistry();
        $strategies->register(new SimpleStrategy());

        $conditions = new ConditionRegistry();
        $conditions->register(new \PowerDiscount\Condition\CartSubtotalCondition());

        $filters = new FilterRegistry();
        $filters->register(new AllProductsFilter());
        $filters->register(new CategoriesFilter());

        return new Calculator(
            $strategies,
            new Evaluator($conditions),
            new Matcher($filters),
            new ExclusivityResolver(),
            static function (): int { return strtotime('2026-04-14 12:00:00 UTC'); }
        );
    }
}
```

- [ ] **Step 2:** Run — expect fail

- [ ] **Step 3:** Implement `src/Engine/Calculator.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Engine;

use Closure;
use PowerDiscount\Condition\Evaluator;
use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Filter\Matcher;
use PowerDiscount\Strategy\StrategyRegistry;

final class Calculator
{
    private StrategyRegistry $strategies;
    private Evaluator $conditionEvaluator;
    private Matcher $filterMatcher;
    private ExclusivityResolver $exclusivityResolver;
    /** @var Closure(): int */
    private Closure $now;

    public function __construct(
        StrategyRegistry $strategies,
        Evaluator $conditionEvaluator,
        Matcher $filterMatcher,
        ExclusivityResolver $exclusivityResolver,
        ?Closure $now = null
    ) {
        $this->strategies = $strategies;
        $this->conditionEvaluator = $conditionEvaluator;
        $this->filterMatcher = $filterMatcher;
        $this->exclusivityResolver = $exclusivityResolver;
        $this->now = $now ?? static function (): int { return time(); };
    }

    /**
     * @param Rule[] $rules  already ordered by priority ASC
     * @return DiscountResult[]
     */
    public function run(array $rules, CartContext $context): array
    {
        $results = [];
        $now = ($this->now)();

        foreach ($rules as $rule) {
            if (!$rule->isEnabled()) {
                continue;
            }
            if (!$rule->isActiveAt($now)) {
                continue;
            }
            if ($rule->isUsageLimitExhausted()) {
                continue;
            }
            if (!$this->conditionEvaluator->evaluate($rule->getConditions(), $context)) {
                continue;
            }

            $matched = $this->filterMatcher->matches($rule->getFilters(), $context);
            if ($matched === []) {
                continue;
            }

            $strategy = $this->strategies->resolve($rule->getType());
            if ($strategy === null) {
                continue;
            }

            $filteredContext = new CartContext($matched);
            $result = $strategy->apply($rule, $filteredContext);
            if ($result === null || !$result->hasDiscount()) {
                continue;
            }

            $results[] = $result;

            if ($this->exclusivityResolver->shouldStopAfter($rule)) {
                break;
            }
        }

        return $results;
    }
}
```

- [ ] **Step 4:** Re-run — expect 7 passes

- [ ] **Step 5:** Commit

```bash
git add src/Engine/Calculator.php tests/Unit/Engine/CalculatorTest.php
git commit -m "feat: add Calculator orchestrating strategies, conditions, filters"
```

---

### Task 16: Aggregator (TDD)

**Files:**
- Create: `tests/Unit/Engine/AggregatorTest.php`
- Create: `src/Engine/AggregatedDiscounts.php`
- Create: `src/Engine/Aggregator.php`

Aggregator 接 `DiscountResult[]`，把每筆歸類成 `product` / `cart` / `shipping` 三桶，回傳給 Integration 層去實際套用到 WC_Cart。Phase 2 的 Aggregator 純邏輯：輸入 results，輸出三個 bucket 的 summary。不直接呼叫 WC API——那是 CartHooks 的事。

- [ ] **Step 1:** Write test

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Engine\Aggregator;

final class AggregatorTest extends TestCase
{
    public function testEmptyInputReturnsZeroBuckets(): void
    {
        $agg = new Aggregator();
        $summary = $agg->aggregate([]);
        self::assertSame(0.0, $summary->productTotal());
        self::assertSame(0.0, $summary->cartTotal());
        self::assertSame(0.0, $summary->shippingTotal());
        self::assertSame([], $summary->results());
    }

    public function testGroupsByScope(): void
    {
        $agg = new Aggregator();
        $results = [
            new DiscountResult(1, 'simple', 'product', 30.0, [1], null, []),
            new DiscountResult(2, 'cart', 'cart', 100.0, [], null, []),
            new DiscountResult(3, 'free_shipping', 'shipping', 50.0, [], null, []),
            new DiscountResult(4, 'simple', 'product', 20.0, [2], null, []),
        ];

        $summary = $agg->aggregate($results);
        self::assertSame(50.0, $summary->productTotal());
        self::assertSame(100.0, $summary->cartTotal());
        self::assertSame(50.0, $summary->shippingTotal());
        self::assertCount(4, $summary->results());
    }

    public function testIgnoresZeroDiscountResults(): void
    {
        $agg = new Aggregator();
        $results = [
            new DiscountResult(1, 'simple', 'product', 0.0, [], null, []),
            new DiscountResult(2, 'cart', 'cart', 25.0, [], null, []),
        ];
        $summary = $agg->aggregate($results);
        self::assertSame(0.0, $summary->productTotal());
        self::assertSame(25.0, $summary->cartTotal());
        self::assertCount(1, $summary->results());
    }
}
```

- [ ] **Step 2:** Run — expect fail

- [ ] **Step 3:** Implement `src/Engine/AggregatedDiscounts.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Engine;

use PowerDiscount\Domain\DiscountResult;

final class AggregatedDiscounts
{
    /** @var DiscountResult[] */
    private array $results;
    private float $product;
    private float $cart;
    private float $shipping;

    /**
     * @param DiscountResult[] $results
     */
    public function __construct(array $results, float $product, float $cart, float $shipping)
    {
        $this->results = $results;
        $this->product = $product;
        $this->cart = $cart;
        $this->shipping = $shipping;
    }

    /** @return DiscountResult[] */
    public function results(): array { return $this->results; }
    public function productTotal(): float { return $this->product; }
    public function cartTotal(): float { return $this->cart; }
    public function shippingTotal(): float { return $this->shipping; }
}
```

- [ ] **Step 4:** Implement `src/Engine/Aggregator.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Engine;

use PowerDiscount\Domain\DiscountResult;

final class Aggregator
{
    /**
     * @param DiscountResult[] $results
     */
    public function aggregate(array $results): AggregatedDiscounts
    {
        $kept = [];
        $product = 0.0;
        $cart = 0.0;
        $shipping = 0.0;

        foreach ($results as $result) {
            if (!$result instanceof DiscountResult || !$result->hasDiscount()) {
                continue;
            }
            $kept[] = $result;
            switch ($result->getScope()) {
                case DiscountResult::SCOPE_PRODUCT:
                    $product += $result->getAmount();
                    break;
                case DiscountResult::SCOPE_CART:
                    $cart += $result->getAmount();
                    break;
                case DiscountResult::SCOPE_SHIPPING:
                    $shipping += $result->getAmount();
                    break;
            }
        }

        return new AggregatedDiscounts($kept, $product, $cart, $shipping);
    }
}
```

- [ ] **Step 5:** Re-run — expect 3 passes

- [ ] **Step 6:** Commit

```bash
git add src/Engine/AggregatedDiscounts.php src/Engine/Aggregator.php tests/Unit/Engine/AggregatorTest.php
git commit -m "feat: add Aggregator and AggregatedDiscounts grouping results by scope"
```

---

### Task 17: CartContextBuilder

**Files:**
- Create: `src/Integration/CartContextBuilder.php`

WC_Cart → Domain CartContext 的映射器。它呼叫 WC 的 `get_product()` / `$product->get_category_ids()` 等方法，所以會依賴 WC runtime。我們**不寫 unit test**（屬於 Integration 層，靠手動驗證）。只需確保 PHP 可 lint 通過。

- [ ] **Step 1:** Create file

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Integration;

use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use WC_Cart;

final class CartContextBuilder
{
    public function fromWcCart(WC_Cart $cart): CartContext
    {
        $items = [];
        foreach ($cart->get_cart() as $cartItem) {
            $product = $cartItem['data'] ?? null;
            if ($product === null || !is_object($product) || !method_exists($product, 'get_id')) {
                continue;
            }

            $productId = (int) $product->get_id();
            $name = method_exists($product, 'get_name') ? (string) $product->get_name() : '';
            $price = method_exists($product, 'get_price') ? (float) $product->get_price() : 0.0;
            $quantity = (int) ($cartItem['quantity'] ?? 0);
            $categoryIds = [];

            // Variation: pull categories from parent.
            $categorySource = $product;
            if (method_exists($product, 'get_parent_id') && (int) $product->get_parent_id() > 0) {
                $parent = wc_get_product((int) $product->get_parent_id());
                if ($parent) {
                    $categorySource = $parent;
                }
            }
            if (method_exists($categorySource, 'get_category_ids')) {
                $categoryIds = array_map('intval', (array) $categorySource->get_category_ids());
            }

            if ($price <= 0 || $quantity <= 0) {
                continue;
            }

            $items[] = new CartItem($productId, $name, $price, $quantity, $categoryIds);
        }
        return new CartContext($items);
    }
}
```

- [ ] **Step 2:** `php -l src/Integration/CartContextBuilder.php`

If the lint fails because `WC_Cart` class is undefined, that's OK — `php -l` checks syntax only, not class resolution. Should pass.

- [ ] **Step 3:** Commit

```bash
git add src/Integration/CartContextBuilder.php
git commit -m "feat: add CartContextBuilder mapping WC_Cart to Domain CartContext"
```

---

### Task 18: CartHooks + OrderDiscountLogger

**Files:**
- Create: `src/Integration/CartHooks.php`
- Create: `src/Integration/OrderDiscountLogger.php`

CartHooks 掛兩個 WC hook：

1. `woocommerce_cart_calculate_fees` — 跑 Calculator，把 cart-scope 折扣用 `$cart->add_fee(... negative)` 加入
2. `woocommerce_before_calculate_totals` — 遍歷結果把 product-scope 折扣用 `$cart_item['data']->set_price()` 改價

OrderDiscountLogger 掛 `woocommerce_checkout_order_processed`，把結果寫入 `pd_order_discounts`。

這些類別**無 unit test**（Integration 層），只要 `php -l` 通過即可。Phase 2 結束後靠手動整合驗證。

- [ ] **Step 1:** Create `src/Integration/CartHooks.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Integration;

use PowerDiscount\Engine\Aggregator;
use PowerDiscount\Engine\Calculator;
use PowerDiscount\Repository\RuleRepository;
use WC_Cart;

final class CartHooks
{
    private RuleRepository $rules;
    private Calculator $calculator;
    private Aggregator $aggregator;
    private CartContextBuilder $builder;

    /** @var array<int, \PowerDiscount\Domain\DiscountResult[]> cached per cart-hash */
    private array $lastResultsByHash = [];

    public function __construct(
        RuleRepository $rules,
        Calculator $calculator,
        Aggregator $aggregator,
        CartContextBuilder $builder
    ) {
        $this->rules = $rules;
        $this->calculator = $calculator;
        $this->aggregator = $aggregator;
        $this->builder = $builder;
    }

    public function register(): void
    {
        add_action('woocommerce_before_calculate_totals', [$this, 'applyProductDiscounts'], 20, 1);
        add_action('woocommerce_cart_calculate_fees', [$this, 'applyCartFees'], 20, 1);
    }

    public function applyProductDiscounts(WC_Cart $cart): void
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $context = $this->builder->fromWcCart($cart);
        $rules = $this->rules->getActiveRules();
        $results = $this->calculator->run($rules, $context);
        $this->lastResultsByHash[spl_object_id($cart)] = $results;

        $summary = $this->aggregator->aggregate($results);

        // Apply product-scope discounts by mutating cart item prices.
        foreach ($summary->results() as $result) {
            if ($result->getScope() !== \PowerDiscount\Domain\DiscountResult::SCOPE_PRODUCT) {
                continue;
            }
            $this->distributeProductDiscount($cart, $result);
        }
    }

    public function applyCartFees(WC_Cart $cart): void
    {
        $results = $this->lastResultsByHash[spl_object_id($cart)] ?? null;
        if ($results === null) {
            return;
        }
        $summary = $this->aggregator->aggregate($results);
        foreach ($summary->results() as $result) {
            if ($result->getScope() !== \PowerDiscount\Domain\DiscountResult::SCOPE_CART) {
                continue;
            }
            $label = $result->getLabel() ?: __('Discount', 'power-discount');
            $cart->add_fee($label, -$result->getAmount(), false);
        }
    }

    private function distributeProductDiscount(WC_Cart $cart, \PowerDiscount\Domain\DiscountResult $result): void
    {
        $affectedIds = $result->getAffectedProductIds();
        if ($affectedIds === []) {
            return;
        }

        // Collect eligible cart items and their line totals.
        $eligible = [];
        $eligibleTotal = 0.0;
        foreach ($cart->get_cart() as $key => $cartItem) {
            $product = $cartItem['data'] ?? null;
            if (!$product || !method_exists($product, 'get_id')) {
                continue;
            }
            $pid = (int) $product->get_id();
            if (!in_array($pid, $affectedIds, true)) {
                // Also check parent for variations
                if (!method_exists($product, 'get_parent_id') || !in_array((int) $product->get_parent_id(), $affectedIds, true)) {
                    continue;
                }
            }
            $price = (float) $product->get_price();
            $qty = (int) ($cartItem['quantity'] ?? 0);
            if ($price <= 0 || $qty <= 0) {
                continue;
            }
            $line = $price * $qty;
            $eligible[$key] = ['product' => $product, 'price' => $price, 'qty' => $qty, 'line' => $line];
            $eligibleTotal += $line;
        }

        if ($eligibleTotal <= 0) {
            return;
        }

        $discountToDistribute = $result->getAmount();

        // Proportional distribution across eligible items.
        foreach ($eligible as $entry) {
            $share = $discountToDistribute * ($entry['line'] / $eligibleTotal);
            $perUnit = $share / $entry['qty'];
            $newPrice = max(0.0, $entry['price'] - $perUnit);
            $entry['product']->set_price($newPrice);
        }
    }
}
```

- [ ] **Step 2:** Create `src/Integration/OrderDiscountLogger.php`

```php
<?php
declare(strict_types=1);

namespace PowerDiscount\Integration;

use PowerDiscount\Engine\Aggregator;
use PowerDiscount\Engine\Calculator;
use PowerDiscount\Repository\OrderDiscountRepository;
use PowerDiscount\Repository\RuleRepository;

final class OrderDiscountLogger
{
    private RuleRepository $rules;
    private OrderDiscountRepository $orderDiscounts;
    private Calculator $calculator;
    private Aggregator $aggregator;
    private CartContextBuilder $builder;

    public function __construct(
        RuleRepository $rules,
        OrderDiscountRepository $orderDiscounts,
        Calculator $calculator,
        Aggregator $aggregator,
        CartContextBuilder $builder
    ) {
        $this->rules = $rules;
        $this->orderDiscounts = $orderDiscounts;
        $this->calculator = $calculator;
        $this->aggregator = $aggregator;
        $this->builder = $builder;
    }

    public function register(): void
    {
        add_action('woocommerce_checkout_order_processed', [$this, 'logOrder'], 20, 1);
    }

    public function logOrder(int $orderId): void
    {
        if (!function_exists('WC') || WC()->cart === null) {
            return;
        }
        $context = $this->builder->fromWcCart(WC()->cart);
        $activeRules = $this->rules->getActiveRules();
        $results = $this->calculator->run($activeRules, $context);

        $titles = [];
        foreach ($activeRules as $rule) {
            $titles[$rule->getId()] = $rule->getTitle();
        }

        $this->orderDiscounts->record($orderId, $results, $titles);

        foreach ($results as $result) {
            $this->rules->incrementUsedCount($result->getRuleId());
        }
    }
}
```

- [ ] **Step 3:** `php -l` both files

- [ ] **Step 4:** Commit

```bash
git add src/Integration/CartHooks.php src/Integration/OrderDiscountLogger.php
git commit -m "feat: wire Calculator to WC cart hooks and order logger"
```

---

### Task 19: Plugin boot — wire everything

**Files:**
- Modify: `src/Plugin.php`

Plugin::boot() 現在要建立所有 registry、Calculator、整合 hooks。Phase 2 結束時這是最後一步，把所有樂高組起來。

- [ ] **Step 1:** Replace `src/Plugin.php` with:

```php
<?php
declare(strict_types=1);

namespace PowerDiscount;

use PowerDiscount\Condition\CartSubtotalCondition;
use PowerDiscount\Condition\ConditionRegistry;
use PowerDiscount\Condition\DateRangeCondition;
use PowerDiscount\Condition\Evaluator as ConditionEvaluator;
use PowerDiscount\Engine\Aggregator;
use PowerDiscount\Engine\Calculator;
use PowerDiscount\Engine\ExclusivityResolver;
use PowerDiscount\Filter\AllProductsFilter;
use PowerDiscount\Filter\CategoriesFilter;
use PowerDiscount\Filter\FilterRegistry;
use PowerDiscount\Filter\Matcher;
use PowerDiscount\I18n\Loader as I18nLoader;
use PowerDiscount\Integration\CartContextBuilder;
use PowerDiscount\Integration\CartHooks;
use PowerDiscount\Integration\OrderDiscountLogger;
use PowerDiscount\Persistence\WpdbAdapter;
use PowerDiscount\Repository\OrderDiscountRepository;
use PowerDiscount\Repository\RuleRepository;
use PowerDiscount\Strategy\BulkStrategy;
use PowerDiscount\Strategy\CartStrategy;
use PowerDiscount\Strategy\SetStrategy;
use PowerDiscount\Strategy\SimpleStrategy;
use PowerDiscount\Strategy\StrategyRegistry;

final class Plugin
{
    private static ?Plugin $instance = null;
    private bool $booted = false;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        (new I18nLoader())->register();

        if (!class_exists('WooCommerce')) {
            return;
        }

        $strategies = $this->buildStrategyRegistry();
        $conditions = $this->buildConditionRegistry();
        $filters = $this->buildFilterRegistry();

        /** @var \wpdb $wpdb */
        global $wpdb;
        $db = new WpdbAdapter($wpdb);
        $rulesRepo = new RuleRepository($db);
        $orderDiscountsRepo = new OrderDiscountRepository($db);

        $calculator = new Calculator(
            $strategies,
            new ConditionEvaluator($conditions),
            new Matcher($filters),
            new ExclusivityResolver()
        );
        $aggregator = new Aggregator();
        $builder = new CartContextBuilder();

        (new CartHooks($rulesRepo, $calculator, $aggregator, $builder))->register();
        (new OrderDiscountLogger($rulesRepo, $orderDiscountsRepo, $calculator, $aggregator, $builder))->register();
    }

    private function buildStrategyRegistry(): StrategyRegistry
    {
        $registry = new StrategyRegistry();
        $registry->register(new SimpleStrategy());
        $registry->register(new BulkStrategy());
        $registry->register(new CartStrategy());
        $registry->register(new SetStrategy());

        $registry = apply_filters('power_discount_strategies', $registry);
        return $registry instanceof StrategyRegistry ? $registry : new StrategyRegistry();
    }

    private function buildConditionRegistry(): ConditionRegistry
    {
        $registry = new ConditionRegistry();
        $registry->register(new CartSubtotalCondition());
        $registry->register(new DateRangeCondition());

        $registry = apply_filters('power_discount_conditions', $registry);
        return $registry instanceof ConditionRegistry ? $registry : new ConditionRegistry();
    }

    private function buildFilterRegistry(): FilterRegistry
    {
        $registry = new FilterRegistry();
        $registry->register(new AllProductsFilter());
        $registry->register(new CategoriesFilter());

        $registry = apply_filters('power_discount_filters', $registry);
        return $registry instanceof FilterRegistry ? $registry : new FilterRegistry();
    }
}
```

- [ ] **Step 2:** `php -l src/Plugin.php`

- [ ] **Step 3:** Run full PHPUnit suite — 既有測試 + 本 phase 新測試全部綠。Phase 2 新增測試預計約 40 (JsonSerializer 6 + RuleRepository 6 + OrderDiscountRepository 3 + CartSubtotal 5 + DateRange 7 + Evaluator 7 + AllProducts 2 + Categories 6 + Matcher 5 + Exclusivity 2 + Calculator 7 + Aggregator 3 = 59)。加上 Phase 1 的 61，預期 **120 tests** 左右，全綠。

- [ ] **Step 4:** Commit

```bash
git add src/Plugin.php
git commit -m "feat: wire Plugin::boot to instantiate engine and register hooks"
```

---

### Task 20: Phase 2 收尾 — 更新 README 與手動驗證清單

**Files:**
- Modify: `README.md`
- Create: `docs/phase-2-manual-verification.md`

- [ ] **Step 1:** Update `README.md` status section:

```markdown
## Status

**Phase 2 (Engine + WC Integration)** — complete.

- Repository with DatabaseAdapter abstraction (fully unit-tested via InMemoryDatabaseAdapter)
- Condition system + 2 conditions: `cart_subtotal`, `date_range`
- Filter system + 2 filters: `all_products`, `categories`
- Engine: Calculator, Aggregator, ExclusivityResolver
- WooCommerce hooks: `woocommerce_before_calculate_totals`, `woocommerce_cart_calculate_fees`, `woocommerce_checkout_order_processed`
- Order discount logging to `wp_pd_order_discounts`
```

- [ ] **Step 2:** Create `docs/phase-2-manual-verification.md`:

````markdown
# Phase 2 Manual Verification

Unit tests cannot exercise the full WooCommerce integration. The following checklist must be run on a real WP + WC site before Phase 2 is declared done on that deploy.

## Setup

1. Activate `power-discount` plugin. Schema v1 must create two tables (`wp_pd_rules`, `wp_pd_order_discounts`).
2. Manually insert a test rule via SQL:

```sql
INSERT INTO wp_pd_rules (title, type, status, priority, exclusive, config, filters, conditions, created_at, updated_at)
VALUES (
  'Coffee 10% off',
  'simple',
  1,
  10,
  0,
  '{"method":"percentage","value":10}',
  '{"items":[{"type":"categories","method":"in","ids":[{CATEGORY_ID}]}]}',
  '{"logic":"and","items":[{"type":"cart_subtotal","operator":">=","value":500}]}',
  NOW(),
  NOW()
);
```

Replace `{CATEGORY_ID}` with a real product category id.

## Checklist

- [ ] Add product in the target category to cart, subtotal < 500 → no discount.
- [ ] Add more items → cart reaches ≥500 → 10% off only applies to category products.
- [ ] Non-category products in cart are unaffected.
- [ ] Proceed to checkout and place order → inspect `wp_pd_order_discounts`: should have one row with `rule_id`, `discount_amount`, `scope='product'`.
- [ ] Disable rule (`status = 0`) → discount no longer appears.
- [ ] Re-enable + set `ends_at` to yesterday → discount no longer appears (expired).
- [ ] Add a second rule with `priority = 5` and `exclusive = 1` → verify it takes over and the first rule is skipped.

## Known Gaps (tracked for Phase 3)

- BulkStrategy `per_category` scope is not implemented (returns null)
- Only 2 conditions available (cart_subtotal, date_range)
- Only 2 filters available (all_products, categories)
- BOGO / NthItem / CrossCategory / FreeShipping strategies not available yet
- No admin UI — rules must be managed via SQL
````

- [ ] **Step 3:** Commit

```bash
git add README.md docs/phase-2-manual-verification.md
git commit -m "docs: update README and add Phase 2 manual verification checklist"
```

---

## Phase 2 Exit Criteria

- ✅ `vendor/bin/phpunit` green, ≥ 120 tests total
- ✅ `find src tests -name '*.php' -exec php -l {} \;` all clean
- ✅ `src/Plugin.php` correctly wires Repository → Calculator → Hooks
- ✅ All new files follow PSR-4 and PHP 7.4 syntax
- ✅ README updated to Phase 2 status
- ✅ Manual verification doc committed

## Known Gaps → Phase 3

- Only 2 conditions / 2 filters — the other 11 conditions + 4 filters go to Phase 3
- `BulkStrategy.per_category` still returns null
- Taiwan strategies (BuyXGetY, NthItem, CrossCategory, FreeShipping) not yet implemented
- No admin UI — rules must be managed via SQL or manual JSON insert
- No integration test against real WC DB — relies on the manual verification checklist
