<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Filter;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Filter\TagsFilter;

final class TagsFilterTest extends TestCase
{
    public function testType(): void
    {
        self::assertSame('tags', (new TagsFilter())->type());
    }

    public function testInList(): void
    {
        $f = new TagsFilter();
        $item = new CartItem(1, 'A', 10.0, 1, [], [5, 6]);
        self::assertTrue($f->matches(['method' => 'in', 'ids' => [5]], $item));
        self::assertFalse($f->matches(['method' => 'in', 'ids' => [99]], $item));
    }

    public function testNotInList(): void
    {
        $f = new TagsFilter();
        $item = new CartItem(1, 'A', 10.0, 1, [], [5]);
        self::assertTrue($f->matches(['method' => 'not_in', 'ids' => [99]], $item));
        self::assertFalse($f->matches(['method' => 'not_in', 'ids' => [5]], $item));
    }

    public function testEmptyIdsNotInReturnsFalse(): void
    {
        $f = new TagsFilter();
        self::assertFalse($f->matches(['method' => 'not_in', 'ids' => []], new CartItem(1, 'A', 10.0, 1, [], [5])));
    }
}
