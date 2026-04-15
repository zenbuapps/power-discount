<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\AddonItem;

final class AddonItemTest extends TestCase
{
    public function testConstructAndGetters(): void
    {
        $item = new AddonItem(101, 90.0);
        self::assertSame(101, $item->getProductId());
        self::assertSame(90.0, $item->getSpecialPrice());
    }

    public function testFromArray(): void
    {
        $item = AddonItem::fromArray(['product_id' => 5, 'special_price' => 120]);
        self::assertSame(5, $item->getProductId());
        self::assertSame(120.0, $item->getSpecialPrice());
    }

    public function testToArray(): void
    {
        $item = new AddonItem(42, 75.5);
        self::assertSame(['product_id' => 42, 'special_price' => 75.5], $item->toArray());
    }

    public function testRejectsInvalidProductId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AddonItem(0, 10.0);
    }

    public function testRejectsNegativeSpecialPrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AddonItem(1, -1.0);
    }
}
