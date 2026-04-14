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

    public function testGetSummaryConsolidatesEverythingInOnePass(): void
    {
        $this->orderRepo->record(1, [
            new DiscountResult(1, 'simple', 'product', 100.0, [], null, []),
            new DiscountResult(2, 'cart', 'cart', 50.0, [], null, []),
        ], [1 => 'A', 2 => 'B']);
        $this->orderRepo->record(2, [
            new DiscountResult(1, 'simple', 'product', 200.0, [], null, []),
        ], [1 => 'A']);

        $summary = $this->reports->getSummary();

        self::assertSame(350.0, $summary['total_discount']);
        self::assertSame(2, $summary['total_orders']);
        self::assertCount(2, $summary['stats']);
        self::assertSame(1, $summary['stats'][0]['rule_id']);
        self::assertSame(300.0, $summary['stats'][0]['total_amount']);
    }
}
