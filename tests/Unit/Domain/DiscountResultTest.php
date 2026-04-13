<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\DiscountResult;

final class DiscountResultTest extends TestCase
{
    public function testConstructAndGetters(): void
    {
        $result = new DiscountResult(
            7,
            'simple',
            'product',
            100.0,
            [1, 2],
            '9 折',
            ['note' => 'x']
        );

        self::assertSame(7, $result->getRuleId());
        self::assertSame('simple', $result->getRuleType());
        self::assertSame('product', $result->getScope());
        self::assertSame(100.0, $result->getAmount());
        self::assertSame([1, 2], $result->getAffectedProductIds());
        self::assertSame('9 折', $result->getLabel());
        self::assertSame(['note' => 'x'], $result->getMeta());
        self::assertTrue($result->hasDiscount());
    }

    public function testRejectsInvalidScope(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DiscountResult(1, 'simple', 'invalid-scope', 10.0, [], null, []);
    }

    public function testRejectsNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DiscountResult(1, 'simple', 'product', -1.0, [], null, []);
    }

    public function testHasDiscountIsFalseForZero(): void
    {
        $r = new DiscountResult(1, 'simple', 'product', 0.0, [], null, []);
        self::assertFalse($r->hasDiscount());
    }
}
