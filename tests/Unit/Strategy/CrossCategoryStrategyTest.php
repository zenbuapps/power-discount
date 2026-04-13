<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Strategy\CrossCategoryStrategy;

final class CrossCategoryStrategyTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('cross_category', (new CrossCategoryStrategy())->type());
    }

    public function testTwoGroupsPercentage(): void
    {
        $rule = $this->rule([
            'groups' => [
                ['name' => 'Top', 'filter' => ['type' => 'categories', 'value' => [12]], 'min_qty' => 1],
                ['name' => 'Bot', 'filter' => ['type' => 'categories', 'value' => [13]], 'min_qty' => 1],
            ],
            'reward' => ['method' => 'percentage', 'value' => 20],
            'repeat' => false,
        ]);
        $ctx = new CartContext([
            new CartItem(1, 'Shirt', 500.0, 1, [12]),
            new CartItem(2, 'Pants', 800.0, 1, [13]),
        ]);
        // bundle total = 1300 × 20% = 260
        $result = (new CrossCategoryStrategy())->apply($rule, $ctx);
        self::assertNotNull($result);
        self::assertSame(260.0, $result->getAmount());
    }

    public function testFixedBundlePrice(): void
    {
        $rule = $this->rule([
            'groups' => [
                ['name' => 'Coffee', 'filter' => ['type' => 'categories', 'value' => [100]], 'min_qty' => 1],
                ['name' => 'Filter', 'filter' => ['type' => 'categories', 'value' => [101]], 'min_qty' => 1],
            ],
            'reward' => ['method' => 'fixed_bundle_price', 'value' => 399],
            'repeat' => false,
        ]);
        $ctx = new CartContext([
            new CartItem(1, 'Beans', 450.0, 1, [100]),
            new CartItem(2, 'Paper', 50.0, 1, [101]),
        ]);
        // bundle 500 - 399 = 101
        $result = (new CrossCategoryStrategy())->apply($rule, $ctx);
        self::assertSame(101.0, $result->getAmount());
    }

    public function testFlatOff(): void
    {
        $rule = $this->rule([
            'groups' => [
                ['name' => 'A', 'filter' => ['type' => 'categories', 'value' => [1]], 'min_qty' => 1],
                ['name' => 'B', 'filter' => ['type' => 'categories', 'value' => [2]], 'min_qty' => 1],
            ],
            'reward' => ['method' => 'flat', 'value' => 100],
            'repeat' => false,
        ]);
        $ctx = new CartContext([
            new CartItem(1, 'A1', 200.0, 1, [1]),
            new CartItem(2, 'B1', 200.0, 1, [2]),
        ]);
        $result = (new CrossCategoryStrategy())->apply($rule, $ctx);
        self::assertSame(100.0, $result->getAmount());
    }

    public function testInsufficientGroupReturnsNull(): void
    {
        $rule = $this->rule([
            'groups' => [
                ['name' => 'A', 'filter' => ['type' => 'categories', 'value' => [1]], 'min_qty' => 1],
                ['name' => 'B', 'filter' => ['type' => 'categories', 'value' => [2]], 'min_qty' => 1],
            ],
            'reward' => ['method' => 'percentage', 'value' => 20],
        ]);
        $ctx = new CartContext([
            new CartItem(1, 'A1', 200.0, 5, [1]),
            // No B
        ]);
        self::assertNull((new CrossCategoryStrategy())->apply($rule, $ctx));
    }

    public function testRepeatFormsMultipleBundles(): void
    {
        $rule = $this->rule([
            'groups' => [
                ['name' => 'A', 'filter' => ['type' => 'categories', 'value' => [1]], 'min_qty' => 1],
                ['name' => 'B', 'filter' => ['type' => 'categories', 'value' => [2]], 'min_qty' => 1],
            ],
            'reward' => ['method' => 'percentage', 'value' => 10],
            'repeat' => true,
        ]);
        $ctx = new CartContext([
            new CartItem(1, 'A1', 100.0, 3, [1]),
            new CartItem(2, 'B1', 100.0, 3, [2]),
        ]);
        // 3 bundles × 200 × 10% = 60
        $result = (new CrossCategoryStrategy())->apply($rule, $ctx);
        self::assertSame(60.0, $result->getAmount());
    }

    public function testHigherMinQty(): void
    {
        $rule = $this->rule([
            'groups' => [
                ['name' => 'A', 'filter' => ['type' => 'categories', 'value' => [1]], 'min_qty' => 2],
                ['name' => 'B', 'filter' => ['type' => 'categories', 'value' => [2]], 'min_qty' => 1],
            ],
            'reward' => ['method' => 'percentage', 'value' => 10],
            'repeat' => false,
        ]);
        $ctx = new CartContext([
            new CartItem(1, 'A1', 100.0, 2, [1]),
            new CartItem(2, 'B1', 300.0, 1, [2]),
        ]);
        // bundle = 100 + 100 + 300 = 500, 10% = 50
        $result = (new CrossCategoryStrategy())->apply($rule, $ctx);
        self::assertSame(50.0, $result->getAmount());
    }

    public function testEmptyCartReturnsNull(): void
    {
        $rule = $this->rule([
            'groups' => [
                ['name' => 'A', 'filter' => ['type' => 'categories', 'value' => [1]], 'min_qty' => 1],
                ['name' => 'B', 'filter' => ['type' => 'categories', 'value' => [2]], 'min_qty' => 1],
            ],
            'reward' => ['method' => 'percentage', 'value' => 10],
        ]);
        self::assertNull((new CrossCategoryStrategy())->apply($rule, new CartContext([])));
    }

    public function testZeroMinQtyReturnsNull(): void
    {
        $rule = $this->rule([
            'groups' => [
                ['name' => 'A', 'filter' => ['type' => 'categories', 'value' => [1]], 'min_qty' => 1],
                ['name' => 'B', 'filter' => ['type' => 'categories', 'value' => [2]], 'min_qty' => 0],
            ],
            'reward' => ['method' => 'percentage', 'value' => 10],
        ]);
        $ctx = new CartContext([
            new CartItem(1, 'A1', 100.0, 1, [1]),
            new CartItem(2, 'B1', 100.0, 1, [2]),
        ]);
        self::assertNull((new CrossCategoryStrategy())->apply($rule, $ctx));
    }

    public function testSingleGroupReturnsNull(): void
    {
        // Cross-category implies multiple groups.
        $rule = $this->rule([
            'groups' => [
                ['name' => 'A', 'filter' => ['type' => 'categories', 'value' => [1]], 'min_qty' => 1],
            ],
            'reward' => ['method' => 'percentage', 'value' => 10],
        ]);
        $ctx = new CartContext([new CartItem(1, 'A1', 100.0, 1, [1])]);
        self::assertNull((new CrossCategoryStrategy())->apply($rule, $ctx));
    }

    private function rule(array $config): Rule
    {
        return new Rule(['id' => 1, 'title' => 't', 'type' => 'cross_category', 'config' => $config]);
    }
}
