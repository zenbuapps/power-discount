<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Condition;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Condition\ConditionInterface;
use PowerDiscount\Condition\ConditionRegistry;
use PowerDiscount\Condition\Evaluator;
use PowerDiscount\Domain\CartContext;

final class EvaluatorTest extends TestCase
{
    public function testEmptyConditionsReturnsTrue(): void
    {
        $eval = new Evaluator(new ConditionRegistry());
        self::assertTrue($eval->evaluate([], new CartContext([])));
    }

    public function testAndLogicAllTrue(): void
    {
        $registry = new ConditionRegistry();
        $registry->register($this->stub('a', true));
        $registry->register($this->stub('b', true));
        $eval = new Evaluator($registry);

        self::assertTrue($eval->evaluate([
            'logic' => 'and',
            'items' => [['type' => 'a'], ['type' => 'b']],
        ], new CartContext([])));
    }

    public function testAndLogicOneFalse(): void
    {
        $registry = new ConditionRegistry();
        $registry->register($this->stub('a', true));
        $registry->register($this->stub('b', false));
        $eval = new Evaluator($registry);

        self::assertFalse($eval->evaluate([
            'logic' => 'and',
            'items' => [['type' => 'a'], ['type' => 'b']],
        ], new CartContext([])));
    }

    public function testOrLogicOneTrue(): void
    {
        $registry = new ConditionRegistry();
        $registry->register($this->stub('a', false));
        $registry->register($this->stub('b', true));
        $eval = new Evaluator($registry);

        self::assertTrue($eval->evaluate([
            'logic' => 'or',
            'items' => [['type' => 'a'], ['type' => 'b']],
        ], new CartContext([])));
    }

    public function testOrLogicAllFalse(): void
    {
        $registry = new ConditionRegistry();
        $registry->register($this->stub('a', false));
        $registry->register($this->stub('b', false));
        $eval = new Evaluator($registry);

        self::assertFalse($eval->evaluate([
            'logic' => 'or',
            'items' => [['type' => 'a'], ['type' => 'b']],
        ], new CartContext([])));
    }

    public function testUnknownTypeFailsSafely(): void
    {
        $registry = new ConditionRegistry();
        $eval = new Evaluator($registry);

        self::assertFalse($eval->evaluate([
            'logic' => 'and',
            'items' => [['type' => 'ghost']],
        ], new CartContext([])));

        self::assertFalse($eval->evaluate([
            'logic' => 'or',
            'items' => [['type' => 'ghost']],
        ], new CartContext([])));
    }

    public function testDefaultLogicIsAnd(): void
    {
        $registry = new ConditionRegistry();
        $registry->register($this->stub('a', true));
        $registry->register($this->stub('b', false));
        $eval = new Evaluator($registry);

        self::assertFalse($eval->evaluate([
            'items' => [['type' => 'a'], ['type' => 'b']],
        ], new CartContext([])));
    }

    private function stub(string $type, bool $result): ConditionInterface
    {
        return new class($type, $result) implements ConditionInterface {
            private string $type;
            private bool $result;
            public function __construct(string $type, bool $result)
            {
                $this->type = $type;
                $this->result = $result;
            }
            public function type(): string { return $this->type; }
            public function evaluate(array $config, CartContext $context): bool
            {
                return $this->result;
            }
        };
    }
}
