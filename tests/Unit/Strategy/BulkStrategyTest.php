<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Strategy\BulkStrategy;

final class BulkStrategyTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('bulk', (new BulkStrategy())->type());
    }

    public function testCumulativePercentageBelowFirstRangeYieldsNothing(): void
    {
        $rule = $this->rule([
            'count_scope' => 'cumulative',
            'ranges' => [
                ['from' => 5, 'to' => 9, 'method' => 'percentage', 'value' => 10],
            ],
        ]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 4, [])]);

        self::assertNull((new BulkStrategy())->apply($rule, $ctx));
    }

    public function testCumulativePercentageAppliesToAllMatched(): void
    {
        $rule = $this->rule([
            'count_scope' => 'cumulative',
            'ranges' => [
                ['from' => 1, 'to' => 4,    'method' => 'percentage', 'value' => 0],
                ['from' => 5, 'to' => 9,    'method' => 'percentage', 'value' => 10],
                ['from' => 10, 'to' => null, 'method' => 'percentage', 'value' => 20],
            ],
        ]);
        $ctx = new CartContext([
            new CartItem(1, 'A', 100.0, 3, []),
            new CartItem(2, 'B', 200.0, 2, []),
        ]);
        // total qty = 5 → 10% off everything
        $result = (new BulkStrategy())->apply($rule, $ctx);
        self::assertNotNull($result);
        // 100*3*0.1 + 200*2*0.1 = 30 + 40 = 70
        self::assertSame(70.0, $result->getAmount());
    }

    public function testCumulativeFlatPerItem(): void
    {
        $rule = $this->rule([
            'count_scope' => 'cumulative',
            'ranges' => [
                ['from' => 10, 'to' => null, 'method' => 'flat', 'value' => 5],
            ],
        ]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 10, [])]);

        $result = (new BulkStrategy())->apply($rule, $ctx);
        self::assertNotNull($result);
        self::assertSame(50.0, $result->getAmount()); // 5 * 10
    }

    public function testOpenEndedUpperBound(): void
    {
        $rule = $this->rule([
            'count_scope' => 'cumulative',
            'ranges' => [
                ['from' => 10, 'to' => null, 'method' => 'percentage', 'value' => 20],
            ],
        ]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 100, [])]);
        $result = (new BulkStrategy())->apply($rule, $ctx);
        self::assertNotNull($result);
        self::assertSame(100.0 * 100 * 0.2, $result->getAmount());
    }

    public function testPerItemScopeUsesPerLineQuantity(): void
    {
        $rule = $this->rule([
            'count_scope' => 'per_item',
            'ranges' => [
                ['from' => 3, 'to' => null, 'method' => 'percentage', 'value' => 10],
            ],
        ]);
        $ctx = new CartContext([
            new CartItem(1, 'A', 100.0, 3, []), // qualifies
            new CartItem(2, 'B', 100.0, 2, []), // doesn't
        ]);

        $result = (new BulkStrategy())->apply($rule, $ctx);
        self::assertNotNull($result);
        self::assertSame(30.0, $result->getAmount()); // only A gets 10% of 100*3
        self::assertSame([1], $result->getAffectedProductIds());
    }

    public function testEmptyCartReturnsNull(): void
    {
        $rule = $this->rule([
            'count_scope' => 'cumulative',
            'ranges' => [['from' => 1, 'to' => null, 'method' => 'percentage', 'value' => 10]],
        ]);
        self::assertNull((new BulkStrategy())->apply($rule, new CartContext([])));
    }

    public function testMissingRangesReturnsNull(): void
    {
        $rule = $this->rule(['count_scope' => 'cumulative', 'ranges' => []]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 5, [])]);
        self::assertNull((new BulkStrategy())->apply($rule, $ctx));
    }

    private function rule(array $config): Rule
    {
        return new Rule(['id' => 1, 'title' => 't', 'type' => 'bulk', 'config' => $config]);
    }
}
