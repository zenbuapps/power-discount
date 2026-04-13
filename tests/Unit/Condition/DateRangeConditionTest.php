<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Condition;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Condition\DateRangeCondition;
use PowerDiscount\Domain\CartContext;

final class DateRangeConditionTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('date_range', (new DateRangeCondition())->type());
    }

    public function testWithinRange(): void
    {
        $now = static function (): int { return strtotime('2026-04-15 12:00:00 UTC'); };
        $c = new DateRangeCondition($now);

        self::assertTrue($c->evaluate([
            'from' => '2026-04-01 00:00:00',
            'to'   => '2026-04-30 23:59:59',
        ], new CartContext([])));
    }

    public function testBeforeRange(): void
    {
        $now = static function (): int { return strtotime('2026-03-15 00:00:00 UTC'); };
        $c = new DateRangeCondition($now);

        self::assertFalse($c->evaluate([
            'from' => '2026-04-01 00:00:00',
            'to'   => '2026-04-30 23:59:59',
        ], new CartContext([])));
    }

    public function testAfterRange(): void
    {
        $now = static function (): int { return strtotime('2026-05-15 00:00:00 UTC'); };
        $c = new DateRangeCondition($now);

        self::assertFalse($c->evaluate([
            'from' => '2026-04-01 00:00:00',
            'to'   => '2026-04-30 23:59:59',
        ], new CartContext([])));
    }

    public function testOpenEndedStart(): void
    {
        $now = static function (): int { return strtotime('2026-04-15 12:00:00 UTC'); };
        $c = new DateRangeCondition($now);

        self::assertTrue($c->evaluate([
            'to' => '2026-04-30 23:59:59',
        ], new CartContext([])));
    }

    public function testOpenEndedEnd(): void
    {
        $now = static function (): int { return strtotime('2026-04-15 12:00:00 UTC'); };
        $c = new DateRangeCondition($now);

        self::assertTrue($c->evaluate([
            'from' => '2026-04-01 00:00:00',
        ], new CartContext([])));
    }

    public function testEmptyConfigIsAlwaysTrue(): void
    {
        $c = new DateRangeCondition(static function (): int { return 0; });
        self::assertTrue($c->evaluate([], new CartContext([])));
    }

    public function testInvalidDateStringReturnsFalse(): void
    {
        $now = static function (): int { return strtotime('2026-04-15 12:00:00 UTC'); };
        $c = new DateRangeCondition($now);

        self::assertFalse($c->evaluate(['from' => 'not-a-date', 'to' => 'still-not'], new CartContext([])));
    }
}
