<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Filter;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Filter\AllProductsFilter;
use PowerDiscount\Filter\CategoriesFilter;
use PowerDiscount\Filter\FilterRegistry;
use PowerDiscount\Filter\Matcher;

final class MatcherTest extends TestCase
{
    public function testEmptyFiltersPassesEverything(): void
    {
        $matcher = new Matcher($this->registry());
        $items = [
            new CartItem(1, 'A', 100.0, 1, [10]),
            new CartItem(2, 'B', 100.0, 1, [20]),
        ];
        $ctx = new CartContext($items);

        $matched = $matcher->matches([], $ctx);
        self::assertCount(2, $matched);
    }

    public function testAllProductsPassesEverything(): void
    {
        $matcher = new Matcher($this->registry());
        $ctx = new CartContext([
            new CartItem(1, 'A', 100.0, 1, [10]),
        ]);

        $matched = $matcher->matches([
            'items' => [['type' => 'all_products']],
        ], $ctx);
        self::assertCount(1, $matched);
    }

    public function testCategoriesFilter(): void
    {
        $matcher = new Matcher($this->registry());
        $ctx = new CartContext([
            new CartItem(1, 'A', 100.0, 1, [10]),
            new CartItem(2, 'B', 100.0, 1, [20]),
            new CartItem(3, 'C', 100.0, 1, [30]),
        ]);

        $matched = $matcher->matches([
            'items' => [['type' => 'categories', 'method' => 'in', 'ids' => [10, 20]]],
        ], $ctx);
        self::assertCount(2, $matched);
    }

    public function testMultipleFiltersAreAnded(): void
    {
        $matcher = new Matcher($this->registry());
        $ctx = new CartContext([
            new CartItem(1, 'A', 100.0, 1, [10]),
            new CartItem(2, 'B', 100.0, 1, [10, 20]),
        ]);

        $matched = $matcher->matches([
            'items' => [
                ['type' => 'categories', 'method' => 'in', 'ids' => [10]],
                ['type' => 'categories', 'method' => 'not_in', 'ids' => [20]],
            ],
        ], $ctx);
        self::assertCount(1, $matched);
        self::assertSame(1, $matched[0]->getProductId());
    }

    public function testUnknownFilterTypeFailsSafelyToExclude(): void
    {
        $matcher = new Matcher($this->registry());
        $ctx = new CartContext([new CartItem(1, 'A', 100.0, 1, [10])]);

        $matched = $matcher->matches([
            'items' => [['type' => 'ghost']],
        ], $ctx);
        self::assertCount(0, $matched);
    }

    private function registry(): FilterRegistry
    {
        $r = new FilterRegistry();
        $r->register(new AllProductsFilter());
        $r->register(new CategoriesFilter());
        return $r;
    }
}
