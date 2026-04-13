<?php
declare(strict_types=1);

namespace PowerDiscount\Condition;

use PowerDiscount\Domain\CartContext;

final class Evaluator
{
    private ConditionRegistry $registry;

    public function __construct(ConditionRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param array<string, mixed> $conditions  { logic: 'and'|'or', items: [...] }
     */
    public function evaluate(array $conditions, CartContext $context): bool
    {
        $items = $conditions['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return true;
        }

        $logic = strtolower((string) ($conditions['logic'] ?? 'and'));
        if ($logic !== 'or') {
            $logic = 'and';
        }

        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['type'])) {
                $result = false;
            } else {
                $condition = $this->registry->resolve((string) $item['type']);
                $result = $condition === null ? false : $condition->evaluate($item, $context);
            }

            if ($logic === 'and' && !$result) {
                return false;
            }
            if ($logic === 'or' && $result) {
                return true;
            }
        }

        return $logic === 'and';
    }
}
