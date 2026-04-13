<?php
declare(strict_types=1);

namespace PowerDiscount\Condition;

use Closure;
use PowerDiscount\Domain\CartContext;

final class DateRangeCondition implements ConditionInterface
{
    /** @var Closure(): int */
    private Closure $now;

    public function __construct(?Closure $now = null)
    {
        $this->now = $now ?? static function (): int { return time(); };
    }

    public function type(): string
    {
        return 'date_range';
    }

    public function evaluate(array $config, CartContext $context): bool
    {
        $now = ($this->now)();
        $from = isset($config['from']) && $config['from'] !== '' ? strtotime((string) $config['from']) : null;
        $to   = isset($config['to'])   && $config['to']   !== '' ? strtotime((string) $config['to'])   : null;

        if (isset($config['from']) && $config['from'] !== '' && $from === false) {
            return false;
        }
        if (isset($config['to']) && $config['to'] !== '' && $to === false) {
            return false;
        }

        if ($from !== null && $from !== false && $now < $from) {
            return false;
        }
        if ($to !== null && $to !== false && $now > $to) {
            return false;
        }
        return true;
    }
}
