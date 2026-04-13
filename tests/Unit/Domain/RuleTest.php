<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Domain\Rule;
use PowerDiscount\Domain\RuleStatus;

final class RuleTest extends TestCase
{
    public function testConstructAndGetters(): void
    {
        $rule = new Rule([
            'id' => 42,
            'title' => '任選 2 件 $90',
            'type' => 'set',
            'status' => RuleStatus::ENABLED,
            'priority' => 5,
            'exclusive' => false,
            'config' => ['bundle_size' => 2, 'method' => 'set_price', 'value' => 90],
            'label' => '組合優惠',
        ]);

        self::assertSame(42, $rule->getId());
        self::assertSame('任選 2 件 $90', $rule->getTitle());
        self::assertSame('set', $rule->getType());
        self::assertTrue($rule->isEnabled());
        self::assertFalse($rule->isExclusive());
        self::assertSame(5, $rule->getPriority());
        self::assertSame('組合優惠', $rule->getLabel());
        self::assertSame(90, $rule->getConfig()['value']);
    }

    public function testDefaults(): void
    {
        $rule = new Rule(['title' => 'x', 'type' => 'simple']);

        self::assertSame(0, $rule->getId());
        self::assertSame(RuleStatus::ENABLED, $rule->getStatus());
        self::assertSame(10, $rule->getPriority());
        self::assertFalse($rule->isExclusive());
        self::assertSame([], $rule->getConfig());
        self::assertNull($rule->getLabel());
    }

    public function testIsActiveAtRespectsDateRange(): void
    {
        $rule = new Rule([
            'title' => 't', 'type' => 'simple',
            'starts_at' => '2026-04-01 00:00:00',
            'ends_at'   => '2026-04-30 23:59:59',
        ]);

        self::assertFalse($rule->isActiveAt(strtotime('2026-03-31 23:59:59')));
        self::assertTrue($rule->isActiveAt(strtotime('2026-04-15 12:00:00')));
        self::assertFalse($rule->isActiveAt(strtotime('2026-05-01 00:00:00')));
    }

    public function testIsActiveAtWithNoDateBounds(): void
    {
        $rule = new Rule(['title' => 't', 'type' => 'simple']);
        self::assertTrue($rule->isActiveAt(time()));
    }

    public function testUsageLimitExhausted(): void
    {
        $unlimited = new Rule(['title' => 't', 'type' => 'simple']);
        self::assertFalse($unlimited->isUsageLimitExhausted());

        $capped = new Rule(['title' => 't', 'type' => 'simple', 'usage_limit' => 100, 'used_count' => 100]);
        self::assertTrue($capped->isUsageLimitExhausted());

        $under = new Rule(['title' => 't', 'type' => 'simple', 'usage_limit' => 100, 'used_count' => 99]);
        self::assertFalse($under->isUsageLimitExhausted());
    }
}
