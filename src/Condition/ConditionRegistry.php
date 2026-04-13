<?php
declare(strict_types=1);

namespace PowerDiscount\Condition;

final class ConditionRegistry
{
    /** @var array<string, ConditionInterface> */
    private array $conditions = [];

    public function register(ConditionInterface $condition): void
    {
        $this->conditions[$condition->type()] = $condition;
    }

    public function resolve(string $type): ?ConditionInterface
    {
        return $this->conditions[$type] ?? null;
    }

    /** @return ConditionInterface[] */
    public function all(): array
    {
        return array_values($this->conditions);
    }
}
