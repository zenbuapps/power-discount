<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Strategy\FreeShippingStrategy;

final class FreeShippingStrategyTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('free_shipping', (new FreeShippingStrategy())->type());
    }

    public function testRemoveShippingEmitsShippingScope(): void
    {
        $rule = $this->rule(['method' => 'remove_shipping']);
        $ctx = new CartContext([new CartItem(1, 'A', 500.0, 1, [])]);

        $result = (new FreeShippingStrategy())->apply($rule, $ctx);
        self::assertNotNull($result);
        self::assertSame(DiscountResult::SCOPE_SHIPPING, $result->getScope());
        $meta = $result->getMeta();
        self::assertSame('remove_shipping', $meta['method'] ?? null);
    }

    public function testPercentageOffShippingEmitsMeta(): void
    {
        $rule = $this->rule(['method' => 'percentage_off_shipping', 'value' => 50]);
        $ctx = new CartContext([new CartItem(1, 'A', 500.0, 1, [])]);

        $result = (new FreeShippingStrategy())->apply($rule, $ctx);
        self::assertNotNull($result);
        $meta = $result->getMeta();
        self::assertSame('percentage_off_shipping', $meta['method'] ?? null);
        self::assertSame(50.0, $meta['value'] ?? null);
    }

    public function testEmptyCartReturnsNull(): void
    {
        $rule = $this->rule(['method' => 'remove_shipping']);
        self::assertNull((new FreeShippingStrategy())->apply($rule, new CartContext([])));
    }

    public function testInvalidMethodReturnsNull(): void
    {
        $rule = $this->rule(['method' => 'bogus']);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);
        self::assertNull((new FreeShippingStrategy())->apply($rule, $ctx));
    }

    public function testPercentageBelowZeroReturnsNull(): void
    {
        $rule = $this->rule(['method' => 'percentage_off_shipping', 'value' => -5]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);
        self::assertNull((new FreeShippingStrategy())->apply($rule, $ctx));
    }

    public function testPercentageAboveHundredReturnsNull(): void
    {
        $rule = $this->rule(['method' => 'percentage_off_shipping', 'value' => 150]);
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [])]);
        self::assertNull((new FreeShippingStrategy())->apply($rule, $ctx));
    }

    private function rule(array $config): Rule
    {
        return new Rule(['id' => 1, 'title' => 't', 'type' => 'free_shipping', 'config' => $config]);
    }
}
