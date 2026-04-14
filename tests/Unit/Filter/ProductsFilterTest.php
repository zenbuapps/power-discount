<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Filter;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Filter\ProductsFilter;

final class ProductsFilterTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('products', (new ProductsFilter())->type());
    }

    public function testInList(): void
    {
        $f = new ProductsFilter();
        self::assertTrue($f->matches(['method' => 'in', 'ids' => [1, 2]], new CartItem(1, 'A', 10.0, 1, [])));
        self::assertFalse($f->matches(['method' => 'in', 'ids' => [1, 2]], new CartItem(3, 'C', 10.0, 1, [])));
    }

    public function testNotInList(): void
    {
        $f = new ProductsFilter();
        self::assertTrue($f->matches(['method' => 'not_in', 'ids' => [99]], new CartItem(1, 'A', 10.0, 1, [])));
        self::assertFalse($f->matches(['method' => 'not_in', 'ids' => [99]], new CartItem(99, 'X', 10.0, 1, [])));
    }

    public function testEmptyIdsInNeverMatches(): void
    {
        $f = new ProductsFilter();
        self::assertFalse($f->matches(['method' => 'in', 'ids' => []], new CartItem(1, 'A', 10.0, 1, [])));
    }

    public function testEmptyIdsNotInReturnsFalse(): void
    {
        $f = new ProductsFilter();
        self::assertFalse($f->matches(['method' => 'not_in', 'ids' => []], new CartItem(1, 'A', 10.0, 1, [])));
    }
}
