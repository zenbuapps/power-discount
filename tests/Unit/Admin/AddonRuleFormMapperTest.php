<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Admin;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PowerDiscount\Admin\AddonRuleFormMapper;

final class AddonRuleFormMapperTest extends TestCase
{
    private function validPost(array $overrides = []): array
    {
        return array_merge([
            'title' => '咖啡豆加價購濾紙',
            'status' => 1,
            'priority' => 10,
            'addon_items' => [
                ['product_id' => 101, 'special_price' => 90],
                ['product_id' => 102, 'special_price' => 150],
            ],
            'target_product_ids' => [12, 34],
            'exclude_from_discounts' => '',
        ], $overrides);
    }

    public function testFromFormDataBuildsValidRule(): void
    {
        $rule = AddonRuleFormMapper::fromFormData($this->validPost());
        self::assertSame('咖啡豆加價購濾紙', $rule->getTitle());
        self::assertSame(10, $rule->getPriority());
        self::assertTrue($rule->isEnabled());
        self::assertFalse($rule->isExcludeFromDiscounts());
        self::assertCount(2, $rule->getAddonItems());
        self::assertSame([12, 34], $rule->getTargetProductIds());
    }

    public function testExcludeFromDiscountsCheckboxParses(): void
    {
        $rule = AddonRuleFormMapper::fromFormData($this->validPost(['exclude_from_discounts' => '1']));
        self::assertTrue($rule->isExcludeFromDiscounts());
    }

    public function testRejectsMissingTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/規則名稱/u');
        AddonRuleFormMapper::fromFormData($this->validPost(['title' => '']));
    }

    public function testRejectsEmptyAddonItems(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/加價購商品/u');
        AddonRuleFormMapper::fromFormData($this->validPost(['addon_items' => []]));
    }

    public function testRejectsEmptyTargetProductIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/目標商品/u');
        AddonRuleFormMapper::fromFormData($this->validPost(['target_product_ids' => []]));
    }

    public function testFiltersOutAddonItemsWithZeroProductId(): void
    {
        $rule = AddonRuleFormMapper::fromFormData($this->validPost([
            'addon_items' => [
                ['product_id' => 0, 'special_price' => 50],
                ['product_id' => 101, 'special_price' => 90],
            ],
        ]));
        self::assertCount(1, $rule->getAddonItems());
        self::assertSame(101, $rule->getAddonItems()[0]->getProductId());
    }

    public function testRejectsNegativeSpecialPrice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/特價/u');
        AddonRuleFormMapper::fromFormData($this->validPost([
            'addon_items' => [['product_id' => 101, 'special_price' => -1]],
        ]));
    }

    public function testRejectsDuplicateAddonProductId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/重複/u');
        AddonRuleFormMapper::fromFormData($this->validPost([
            'addon_items' => [
                ['product_id' => 101, 'special_price' => 90],
                ['product_id' => 101, 'special_price' => 100],
            ],
        ]));
    }

    public function testFromFormDataLooseAllowsMissingTitle(): void
    {
        // Loose mode bypasses all validation — used for error-redisplay flow
        $rule = AddonRuleFormMapper::fromFormDataLoose(['title' => '', 'addon_items' => [], 'target_product_ids' => []]);
        self::assertSame('', $rule->getTitle());
    }

    public function testFromFormDataLooseKeepsMalformedData(): void
    {
        // Loose mode still silently drops entries with product_id=0 at the Domain layer,
        // but does not throw — so incoming POST round-trips for redisplay.
        $rule = AddonRuleFormMapper::fromFormDataLoose([
            'title' => 'Draft',
            'addon_items' => [
                ['product_id' => 101, 'special_price' => -5], // negative, AddonItem rejects, AddonRule skips
                ['product_id' => 102, 'special_price' => 50],
            ],
            'target_product_ids' => [12],
        ]);
        self::assertSame('Draft', $rule->getTitle());
        // AddonItem rejects product_id=0 or negative price; AddonRule constructor silently skips
        self::assertCount(1, $rule->getAddonItems());
        self::assertSame(102, $rule->getAddonItems()[0]->getProductId());
    }

    public function testParsesIdForUpdate(): void
    {
        $rule = AddonRuleFormMapper::fromFormData($this->validPost(['id' => 42]));
        self::assertSame(42, $rule->getId());
    }
}
