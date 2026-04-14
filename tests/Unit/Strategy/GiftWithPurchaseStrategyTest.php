<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Strategy\GiftWithPurchaseStrategy;

final class GiftWithPurchaseStrategyTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('gift_with_purchase', (new GiftWithPurchaseStrategy())->type());
    }

    public function testFreeGiftWhenThresholdMet(): void
    {
        $rule = $this->rule(['threshold' => 1000, 'gift_product_ids' => [99], 'gift_qty' => 1]);
        $ctx = new CartContext([
            new CartItem(1, 'Coffee', 1200.0, 1, []),
            new CartItem(99, 'Free Mug', 250.0, 1, []),
        ]);

        $result = (new GiftWithPurchaseStrategy())->apply($rule, $ctx);
        self::assertNotNull($result);
        self::assertSame('product', $result->getScope());
        self::assertSame(250.0, $result->getAmount());
        self::assertSame([99], $result->getAffectedProductIds());
    }

    public function testBelowThresholdNoDiscount(): void
    {
        $rule = $this->rule(['threshold' => 1000, 'gift_product_ids' => [99], 'gift_qty' => 1]);
        $ctx = new CartContext([
            new CartItem(1, 'Coffee', 500.0, 1, []),
            new CartItem(99, 'Free Mug', 250.0, 1, []),
        ]);
        // subtotal = 750 < 1000

        self::assertNull((new GiftWithPurchaseStrategy())->apply($rule, $ctx));
    }

    public function testGiftNotInCartNoDiscount(): void
    {
        $rule = $this->rule(['threshold' => 1000, 'gift_product_ids' => [99], 'gift_qty' => 1]);
        $ctx = new CartContext([
            new CartItem(1, 'Coffee', 1500.0, 1, []),
        ]);
        // subtotal = 1500 ≥ 1000 but no gift item

        self::assertNull((new GiftWithPurchaseStrategy())->apply($rule, $ctx));
    }

    public function testPicksMostExpensiveGiftWhenMultipleEligible(): void
    {
        $rule = $this->rule(['threshold' => 1000, 'gift_product_ids' => [99, 100], 'gift_qty' => 1]);
        $ctx = new CartContext([
            new CartItem(1,  'Coffee', 1500.0, 1, []),
            new CartItem(99, 'Cheap Gift', 50.0, 1, []),
            new CartItem(100, 'Premium Gift', 300.0, 1, []),
        ]);

        $result = (new GiftWithPurchaseStrategy())->apply($rule, $ctx);
        self::assertNotNull($result);
        self::assertSame(300.0, $result->getAmount());
        self::assertSame([100], $result->getAffectedProductIds());
    }

    public function testGiftQtyMoreThanOne(): void
    {
        $rule = $this->rule(['threshold' => 2000, 'gift_product_ids' => [99], 'gift_qty' => 2]);
        $ctx = new CartContext([
            new CartItem(1,  'Coffee', 2500.0, 1, []),
            new CartItem(99, 'Mug',    100.0,  3, []),  // 3 in cart, take 2
        ]);

        $result = (new GiftWithPurchaseStrategy())->apply($rule, $ctx);
        self::assertSame(200.0, $result->getAmount());
    }

    public function testGiftQtyExceedsAvailableTakesWhatExists(): void
    {
        $rule = $this->rule(['threshold' => 1000, 'gift_product_ids' => [99], 'gift_qty' => 5]);
        $ctx = new CartContext([
            new CartItem(1,  'Coffee', 1500.0, 1, []),
            new CartItem(99, 'Mug',    100.0,  2, []),
        ]);

        $result = (new GiftWithPurchaseStrategy())->apply($rule, $ctx);
        self::assertSame(200.0, $result->getAmount());
    }

    public function testEmptyCartReturnsNull(): void
    {
        $rule = $this->rule(['threshold' => 1000, 'gift_product_ids' => [99]]);
        self::assertNull((new GiftWithPurchaseStrategy())->apply($rule, new CartContext([])));
    }

    public function testZeroThresholdReturnsNull(): void
    {
        $rule = $this->rule(['threshold' => 0, 'gift_product_ids' => [99]]);
        $ctx = new CartContext([new CartItem(99, 'Mug', 100.0, 1, [])]);
        self::assertNull((new GiftWithPurchaseStrategy())->apply($rule, $ctx));
    }

    public function testNoGiftIdsReturnsNull(): void
    {
        $rule = $this->rule(['threshold' => 1000, 'gift_product_ids' => []]);
        $ctx = new CartContext([new CartItem(1, 'Coffee', 1500.0, 1, [])]);
        self::assertNull((new GiftWithPurchaseStrategy())->apply($rule, $ctx));
    }

    private function rule(array $config): Rule
    {
        return new Rule(['id' => 1, 'title' => 't', 'type' => 'gift_with_purchase', 'config' => $config]);
    }
}
