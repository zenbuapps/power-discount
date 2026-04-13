<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Strategy\SimpleStrategy;

final class SimpleStrategyTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('simple', (new SimpleStrategy())->type());
    }

    public function testPercentageDiscount(): void
    {
        $rule = $this->makeRule(['method' => 'percentage', 'value' => 10]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 2, [])]);

        $result = (new SimpleStrategy())->apply($rule, $ctx);

        self::assertNotNull($result);
        self::assertSame('product', $result->getScope());
        self::assertSame(20.0, $result->getAmount()); // 100 * 0.10 * 2
        self::assertSame([1], $result->getAffectedProductIds());
    }

    public function testFlatDiscountCappedAtPrice(): void
    {
        $rule = $this->makeRule(['method' => 'flat', 'value' => 150]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);

        $result = (new SimpleStrategy())->apply($rule, $ctx);

        self::assertNotNull($result);
        self::assertSame(100.0, $result->getAmount()); // capped
    }

    public function testFixedPriceReducesToTarget(): void
    {
        $rule = $this->makeRule(['method' => 'fixed_price', 'value' => 80]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 3, [])]);

        $result = (new SimpleStrategy())->apply($rule, $ctx);

        self::assertNotNull($result);
        self::assertSame(60.0, $result->getAmount()); // (100-80) * 3
    }

    public function testFixedPriceHigherThanCurrentYieldsNoDiscount(): void
    {
        $rule = $this->makeRule(['method' => 'fixed_price', 'value' => 200]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);

        $result = (new SimpleStrategy())->apply($rule, $ctx);

        self::assertNull($result);
    }

    public function testMultipleItemsAggregated(): void
    {
        $rule = $this->makeRule(['method' => 'percentage', 'value' => 50]);
        $ctx = new CartContext([
            new CartItem(1, 'A', 100.0, 1, []),
            new CartItem(2, 'B', 200.0, 2, []),
        ]);

        $result = (new SimpleStrategy())->apply($rule, $ctx);

        self::assertNotNull($result);
        self::assertSame(50.0 + 200.0, $result->getAmount());
        self::assertSame([1, 2], $result->getAffectedProductIds());
    }

    public function testEmptyCartReturnsNull(): void
    {
        $rule = $this->makeRule(['method' => 'percentage', 'value' => 10]);
        self::assertNull((new SimpleStrategy())->apply($rule, new CartContext([])));
    }

    public function testInvalidMethodReturnsNull(): void
    {
        $rule = $this->makeRule(['method' => 'nonsense', 'value' => 10]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);
        self::assertNull((new SimpleStrategy())->apply($rule, $ctx));
    }

    public function testZeroValueReturnsNull(): void
    {
        $rule = $this->makeRule(['method' => 'percentage', 'value' => 0]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);
        self::assertNull((new SimpleStrategy())->apply($rule, $ctx));
    }

    private function makeRule(array $config): Rule
    {
        return new Rule([
            'id' => 1,
            'title' => 'Test',
            'type' => 'simple',
            'config' => $config,
        ]);
    }
}
