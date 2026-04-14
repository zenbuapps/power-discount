<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Filter;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Filter\OnSaleFilter;

final class OnSaleFilterTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('on_sale', (new OnSaleFilter())->type());
    }

    public function testMatchesItemOnSale(): void
    {
        $f = new OnSaleFilter();
        $onSale = new CartItem(1, 'A', 50.0, 1, [], [], [], true);
        $notOnSale = new CartItem(2, 'B', 100.0, 1, [], [], [], false);

        self::assertTrue($f->matches([], $onSale));
        self::assertFalse($f->matches([], $notOnSale));
    }
}
