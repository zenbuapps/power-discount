<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Filter;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Filter\AttributesFilter;

final class AttributesFilterTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('attributes', (new AttributesFilter())->type());
    }

    public function testMatchesAttribute(): void
    {
        $f = new AttributesFilter();
        $item = new CartItem(1, 'A', 10.0, 1, [], [], ['color' => ['red', 'blue']]);
        self::assertTrue($f->matches(['attribute' => 'color', 'values' => ['red'], 'method' => 'in'], $item));
        self::assertTrue($f->matches(['attribute' => 'color', 'values' => ['green', 'blue'], 'method' => 'in'], $item));
        self::assertFalse($f->matches(['attribute' => 'color', 'values' => ['green'], 'method' => 'in'], $item));
    }

    public function testAttributeMissingFromItem(): void
    {
        $f = new AttributesFilter();
        $item = new CartItem(1, 'A', 10.0, 1, []);
        self::assertFalse($f->matches(['attribute' => 'color', 'values' => ['red'], 'method' => 'in'], $item));
    }

    public function testNotInAttribute(): void
    {
        $f = new AttributesFilter();
        $item = new CartItem(1, 'A', 10.0, 1, [], [], ['size' => ['L']]);
        self::assertTrue($f->matches(['attribute' => 'size', 'values' => ['XL'], 'method' => 'not_in'], $item));
        self::assertFalse($f->matches(['attribute' => 'size', 'values' => ['L'], 'method' => 'not_in'], $item));
    }

    public function testMissingConfig(): void
    {
        $f = new AttributesFilter();
        $item = new CartItem(1, 'A', 10.0, 1, [], [], ['color' => ['red']]);
        self::assertFalse($f->matches([], $item));
    }
}
