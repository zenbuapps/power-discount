<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Condition;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Condition\ShippingMethodCondition;
use PowerDiscount\Domain\CartContext;

final class ShippingMethodConditionTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('shipping_method', (new ShippingMethodCondition(static function (): ?string { return null; }))->type());
    }

    public function testMatches(): void
    {
        $c = new ShippingMethodCondition(static function (): ?string { return 'seven_eleven'; });
        self::assertTrue($c->evaluate(['methods' => ['seven_eleven', 'family_mart']], new CartContext([])));
        self::assertFalse($c->evaluate(['methods' => ['home_delivery']], new CartContext([])));
    }

    public function testEmptyConfig(): void
    {
        $c = new ShippingMethodCondition(static function (): ?string { return 'x'; });
        self::assertFalse($c->evaluate([], new CartContext([])));
    }
}
