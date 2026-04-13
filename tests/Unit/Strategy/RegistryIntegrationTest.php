<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Strategy\BulkStrategy;
use PowerDiscount\Strategy\CartStrategy;
use PowerDiscount\Strategy\SetStrategy;
use PowerDiscount\Strategy\SimpleStrategy;
use PowerDiscount\Strategy\StrategyRegistry;

final class RegistryIntegrationTest extends TestCase
{
    public function testAllFourStrategiesResolveAndApply(): void
    {
        $registry = new StrategyRegistry();
        $registry->register(new SimpleStrategy());
        $registry->register(new BulkStrategy());
        $registry->register(new CartStrategy());
        $registry->register(new SetStrategy());

        self::assertCount(4, $registry->all());

        $ctx = new CartContext([
            new CartItem(1, 'A', 100.0, 2, []),
            new CartItem(2, 'B', 200.0, 3, []),
        ]);

        // simple 10%
        $simple = $registry->resolve('simple')->apply(
            new Rule(['id' => 1, 'type' => 'simple', 'title' => 's', 'config' => ['method' => 'percentage', 'value' => 10]]),
            $ctx
        );
        self::assertNotNull($simple);

        // bulk cumulative: qty=5 → 10% off
        $bulk = $registry->resolve('bulk')->apply(
            new Rule([
                'id' => 2, 'type' => 'bulk', 'title' => 'b',
                'config' => [
                    'count_scope' => 'cumulative',
                    'ranges' => [
                        ['from' => 5, 'to' => null, 'method' => 'percentage', 'value' => 10],
                    ],
                ],
            ]),
            $ctx
        );
        self::assertNotNull($bulk);

        // cart flat_total
        $cart = $registry->resolve('cart')->apply(
            new Rule(['id' => 3, 'type' => 'cart', 'title' => 'c', 'config' => ['method' => 'flat_total', 'value' => 100]]),
            $ctx
        );
        self::assertNotNull($cart);
        self::assertSame('cart', $cart->getScope());

        // set: bundle 2 @ 300 set_price with repeat (2 bundles of 2 items each from 5 units → at least 2 bundles possible)
        $set = $registry->resolve('set')->apply(
            new Rule([
                'id' => 4, 'type' => 'set', 'title' => 't',
                'config' => ['bundle_size' => 2, 'method' => 'set_price', 'value' => 300, 'repeat' => true],
            ]),
            $ctx
        );
        self::assertNotNull($set);
    }
}
