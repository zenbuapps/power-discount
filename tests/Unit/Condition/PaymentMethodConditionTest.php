<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Condition;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Condition\PaymentMethodCondition;
use PowerDiscount\Domain\CartContext;

final class PaymentMethodConditionTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('payment_method', (new PaymentMethodCondition(static function (): ?string { return null; }))->type());
    }

    public function testMatches(): void
    {
        $c = new PaymentMethodCondition(static function (): ?string { return 'ecpay_linepay'; });
        self::assertTrue($c->evaluate(['methods' => ['ecpay_linepay', 'cod']], new CartContext([])));
        self::assertFalse($c->evaluate(['methods' => ['cod']], new CartContext([])));
    }

    public function testEmptyConfig(): void
    {
        $c = new PaymentMethodCondition(static function (): ?string { return 'x'; });
        self::assertFalse($c->evaluate([], new CartContext([])));
        self::assertFalse($c->evaluate(['methods' => []], new CartContext([])));
    }

    public function testNullActiveMethod(): void
    {
        $c = new PaymentMethodCondition(static function (): ?string { return null; });
        self::assertFalse($c->evaluate(['methods' => ['any']], new CartContext([])));
    }
}
