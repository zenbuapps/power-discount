<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\AddonItem;
use PowerDiscount\Domain\AddonRule;

final class AddonRuleTest extends TestCase
{
    private function makeRule(array $overrides = []): AddonRule
    {
        return new AddonRule(array_merge([
            'id'                     => 1,
            'title'                  => '咖啡豆加價購濾紙',
            'status'                 => 1,
            'priority'               => 10,
            'addon_items'            => [
                ['product_id' => 101, 'special_price' => 90],
                ['product_id' => 102, 'special_price' => 150],
            ],
            'target_product_ids'     => [12, 34, 56],
            'exclude_from_discounts' => false,
        ], $overrides));
    }

    public function testConstructAndGetters(): void
    {
        $rule = $this->makeRule();
        self::assertSame(1, $rule->getId());
        self::assertSame('咖啡豆加價購濾紙', $rule->getTitle());
        self::assertTrue($rule->isEnabled());
        self::assertSame(10, $rule->getPriority());
        self::assertFalse($rule->isExcludeFromDiscounts());
        self::assertCount(2, $rule->getAddonItems());
        self::assertSame([12, 34, 56], $rule->getTargetProductIds());
    }

    public function testAddonItemsAreValueObjects(): void
    {
        $rule = $this->makeRule();
        $items = $rule->getAddonItems();
        self::assertInstanceOf(AddonItem::class, $items[0]);
        self::assertSame(101, $items[0]->getProductId());
        self::assertSame(90.0, $items[0]->getSpecialPrice());
    }

    public function testMatchesTarget(): void
    {
        $rule = $this->makeRule();
        self::assertTrue($rule->matchesTarget(12));
        self::assertTrue($rule->matchesTarget(34));
        self::assertFalse($rule->matchesTarget(99));
    }

    public function testGetSpecialPriceFor(): void
    {
        $rule = $this->makeRule();
        self::assertSame(90.0, $rule->getSpecialPriceFor(101));
        self::assertSame(150.0, $rule->getSpecialPriceFor(102));
        self::assertNull($rule->getSpecialPriceFor(999));
    }

    public function testContainsAddon(): void
    {
        $rule = $this->makeRule();
        self::assertTrue($rule->containsAddon(101));
        self::assertFalse($rule->containsAddon(999));
    }

    public function testExcludeFromDiscountsFlag(): void
    {
        $rule = $this->makeRule(['exclude_from_discounts' => true]);
        self::assertTrue($rule->isExcludeFromDiscounts());
    }

    public function testDisabledStatus(): void
    {
        $rule = $this->makeRule(['status' => 0]);
        self::assertFalse($rule->isEnabled());
    }

    public function testConstructorSkipsMalformedAddonItems(): void
    {
        $rule = new AddonRule([
            'title' => 't',
            'addon_items' => [
                ['product_id' => 0, 'special_price' => 10],    // invalid: product_id must be > 0
                'not-an-array',                                  // invalid: not array
                ['product_id' => 5, 'special_price' => 20],    // valid
                ['product_id' => 10, 'special_price' => -1],   // invalid: negative price
                ['product_id' => 11, 'special_price' => 50],   // valid
            ],
        ]);
        $items = $rule->getAddonItems();
        self::assertCount(2, $items);
        self::assertSame(5, $items[0]->getProductId());
        self::assertSame(11, $items[1]->getProductId());
    }

    public function testConstructorFiltersTargetProductIds(): void
    {
        $rule = new AddonRule([
            'title' => 't',
            'addon_items' => [],
            'target_product_ids' => [0, -1, 'abc', 7, 12, null],
        ]);
        self::assertSame([7, 12], $rule->getTargetProductIds());
    }
}
