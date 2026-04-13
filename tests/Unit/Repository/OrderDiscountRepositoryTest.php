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
