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
