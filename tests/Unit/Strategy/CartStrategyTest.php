<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Strategy\CartStrategy;

final class CartStrategyTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('cart', (new CartStrategy())->type());
    }

    public function testPercentageOfSubtotal(): void
    {
        $rule = $this->rule(['method' => 'percentage', 'value' => 10]);
        $ctx = new CartContext([
            new CartItem(1, 'A', 500.0, 1, []),
            new CartItem(2, 'B', 500.0, 1, []),
        ]);

        $result = (new CartStrategy())->apply($rule, $ctx);
        self::assertNotNull($result);
        self::assertSame('cart', $result->getScope());
        self::assertSame(100.0, $result->getAmount());
    }

    public function testFlatTotal(): void
    {
        $rule = $this->rule(['method' => 'flat_total', 'value' => 100]);
        $ctx = new CartContext([new CartItem(1, 'A', 1000.0, 1, [])]);
        $result = (new CartStrategy())->apply($rule, $ctx);
        self::assertSame(100.0, $result->getAmount());
    }

    public function testFlatTotalCappedAtSubtotal(): void
    {
        $rule = $this->rule(['method' => 'flat_total', 'value' => 5000]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);
        $result = (new CartStrategy())->apply($rule, $ctx);
        self::assertSame(100.0, $result->getAmount());
    }

    public function testFlatPerItemAggregatesAcrossLines(): void
    {
        $rule = $this->rule(['method' => 'flat_per_item', 'value' => 10]);
        $ctx = new CartContext([
            new CartItem(1, 'A', 100.0, 2, []),
            new CartItem(2, 'B', 100.0, 3, []),
        ]);
        // 10 per unit * 5 units = 50
        $result = (new CartStrategy())->apply($rule, $ctx);
        self::assertSame(50.0, $result->getAmount());
    }

    public function testEmptyCartReturnsNull(): void
    {
        $rule = $this->rule(['method' => 'percentage', 'value' => 10]);
        self::assertNull((new CartStrategy())->apply($rule, new CartContext([])));
    }

    public function testZeroValueReturnsNull(): void
    {
        $rule = $this->rule(['method' => 'percentage', 'value' => 0]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);
        self::assertNull((new CartStrategy())->apply($rule, $ctx));
    }

    public function testInvalidMethodReturnsNull(): void
    {
        $rule = $this->rule(['method' => 'nonsense', 'value' => 10]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);
        self::assertNull((new CartStrategy())->apply($rule, $ctx));
    }

    private function rule(array $config): Rule
    {
        return new Rule(['id' => 1, 'title' => 't', 'type' => 'cart', 'config' => $config]);
    }
}
