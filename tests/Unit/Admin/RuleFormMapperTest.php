<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Admin\RuleFormMapper;
use PowerDiscount\Domain\RuleStatus;

final class RuleFormMapperTest extends TestCase
{
    public function testSimpleMinimal(): void
    {
        $rule = RuleFormMapper::fromFormData([
            'title' => 'Test',
            'type'  => 'simple',
            'config_by_type' => [
                'simple' => ['method' => 'percentage', 'value' => 10],
            ],
        ]);

        self::assertSame('Test', $rule->getTitle());
        self::assertSame('simple', $rule->getType());
        self::assertSame(RuleStatus::ENABLED, $rule->getStatus());
        self::assertSame(10, $rule->getPriority());
        self::assertFalse($rule->isExclusive());
        self::assertSame(['method' => 'percentage', 'value' => 10.0], $rule->getConfig());
        self::assertSame([], $rule->getFilters());
        self::assertSame([], $rule->getConditions());
        self::assertNull($rule->getNotes());
    }

    public function testIgnoresOtherTypesConfig(): void
    {
        // When type=simple, any config_by_type[bulk] data must be discarded.
        $rule = RuleFormMapper::fromFormData([
            'title' => 'X',
            'type'  => 'simple',
            'config_by_type' => [
                'simple' => ['method' => 'percentage', 'value' => 5],
                'bulk'   => ['ranges' => [['from' => 1, 'method' => 'percentage', 'value' => 20]]],
            ],
        ]);
        self::assertSame(['method' => 'percentage', 'value' => 5.0], $rule->getConfig());
    }

    public function testBulkConfig(): void
    {
        $rule = RuleFormMapper::fromFormData([
            'title' => 'B', 'type' => 'bulk',
            'config_by_type' => [
                'bulk' => [
                    'count_scope' => 'cumulative',
                    'ranges' => [
                        ['from' => 1, 'to' => 4, 'method' => 'percentage', 'value' => 5],
                        ['from' => 5, 'to' => '', 'method' => 'percentage', 'value' => 10],
                    ],
                ],
            ],
        ]);
        $config = $rule->getConfig();
        self::assertSame('cumulative', $config['count_scope']);
        self::assertCount(2, $config['ranges']);
        self::assertSame(1, $config['ranges'][0]['from']);
        self::assertSame(4, $config['ranges'][0]['to']);
        self::assertNull($config['ranges'][1]['to']);
    }

    public function testSetConfig(): void
    {
        $rule = RuleFormMapper::fromFormData([
            'title' => 'S', 'type' => 'set',
            'config_by_type' => [
                'set' => ['bundle_size' => 2, 'method' => 'set_flat_off', 'value' => 100, 'repeat' => 1],
            ],
        ]);
        $c = $rule->getConfig();
        self::assertSame(2, $c['bundle_size']);
        self::assertSame('set_flat_off', $c['method']);
        self::assertTrue($c['repeat']);
    }

    public function testFiltersNormalised(): void
    {
        $rule = RuleFormMapper::fromFormData([
            'title' => 'X', 'type' => 'simple',
            'config_by_type' => ['simple' => ['method' => 'percentage', 'value' => 10]],
            'filters' => [
                'items' => [
                    ['type' => 'categories', 'method' => 'in', 'ids' => [12, '13', 'abc']],
                    ['type' => 'all_products'],
                    ['type' => '', 'method' => 'in'], // dropped
                ],
            ],
        ]);
        $filters = $rule->getFilters();
        self::assertCount(2, $filters['items']);
        self::assertSame([12, 13], $filters['items'][0]['ids']);
        self::assertSame('all_products', $filters['items'][1]['type']);
    }

    public function testConditionsNormalised(): void
    {
        $rule = RuleFormMapper::fromFormData([
            'title' => 'X', 'type' => 'simple',
            'config_by_type' => ['simple' => ['method' => 'percentage', 'value' => 10]],
            'conditions' => [
                'logic' => 'or',
                'items' => [
                    ['type' => 'cart_subtotal', 'operator' => '>=', 'value' => 500],
                    ['type' => 'user_role', 'roles' => ['customer', 'subscriber']],
                ],
            ],
        ]);
        $conditions = $rule->getConditions();
        self::assertSame('or', $conditions['logic']);
        self::assertCount(2, $conditions['items']);
        self::assertSame(500.0, $conditions['items'][0]['value']);
        self::assertSame(['customer', 'subscriber'], $conditions['items'][1]['roles']);
    }

    public function testRejectsMissingTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/規則名稱/u');
        RuleFormMapper::fromFormData([
            'title' => '', 'type' => 'simple',
            'config_by_type' => ['simple' => ['method' => 'percentage', 'value' => 10]],
        ]);
    }

    public function testRejectsInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/折扣類型/u');
        RuleFormMapper::fromFormData(['title' => 'X', 'type' => 'bogus']);
    }

    public function testRejectsSimpleWithoutMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/折扣方式/u');
        RuleFormMapper::fromFormData([
            'title' => 'X', 'type' => 'simple',
            'config_by_type' => ['simple' => ['value' => 10]],
        ]);
    }

    public function testRejectsSimpleWithZeroValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RuleFormMapper::fromFormData([
            'title' => 'X', 'type' => 'simple',
            'config_by_type' => ['simple' => ['method' => 'percentage', 'value' => 0]],
        ]);
    }

    public function testRejectsBulkWithNoRanges(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/級別/u');
        RuleFormMapper::fromFormData([
            'title' => 'X', 'type' => 'bulk',
            'config_by_type' => ['bulk' => ['count_scope' => 'cumulative', 'ranges' => []]],
        ]);
    }

    public function testRejectsSetWithBundleSizeLessThanTwo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RuleFormMapper::fromFormData([
            'title' => 'X', 'type' => 'set',
            'config_by_type' => ['set' => ['bundle_size' => 1, 'method' => 'set_price', 'value' => 50]],
        ]);
    }

    public function testRejectsCrossCategoryWithOneGroup(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RuleFormMapper::fromFormData([
            'title' => 'X', 'type' => 'cross_category',
            'config_by_type' => [
                'cross_category' => [
                    'groups' => [['name' => 'A', 'category_ids' => [1], 'min_qty' => 1]],
                    'reward' => ['method' => 'percentage', 'value' => 10],
                ],
            ],
        ]);
    }

    public function testRejectsBadDateFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/開始時間/u');
        RuleFormMapper::fromFormData([
            'title' => 'X', 'type' => 'simple',
            'config_by_type' => ['simple' => ['method' => 'percentage', 'value' => 10]],
            'starts_at' => 'tomorrow',
        ]);
    }

    public function testAcceptsValidDateRangeAndUsageLimit(): void
    {
        $rule = RuleFormMapper::fromFormData([
            'title' => 'X', 'type' => 'simple',
            'config_by_type' => ['simple' => ['method' => 'percentage', 'value' => 10]],
            'starts_at'   => '2026-04-15 00:00:00',
            'ends_at'     => '2026-04-30 23:59:59',
            'usage_limit' => '100',
        ]);
        self::assertSame('2026-04-15 00:00:00', $rule->getStartsAt());
        self::assertSame('2026-04-30 23:59:59', $rule->getEndsAt());
        self::assertSame(100, $rule->getUsageLimit());
    }

    public function testNotesIsAlwaysNull(): void
    {
        // notes is a dev-internal field; never accepted from POST.
        $rule = RuleFormMapper::fromFormData([
            'title' => 'X', 'type' => 'simple',
            'config_by_type' => ['simple' => ['method' => 'percentage', 'value' => 10]],
            'notes' => 'should be ignored',
        ]);
        self::assertNull($rule->getNotes());
    }
}
