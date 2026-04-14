<?php
declare(strict_types=1);

namespace PowerDiscount\Condition;

use Closure;
use PowerDiscount\Domain\CartContext;

final class ShippingMethodCondition implements ConditionInterface
{
    /** @var Closure(): ?string */
    private Closure $getActiveMethod;

    public function __construct(Closure $getActiveMethod)
    {
        $this->getActiveMethod = $getActiveMethod;
    }

    public function type(): string
    {
        return 'shipping_method';
    }

    public function evaluate(array $config, CartContext $context): bool
    {
        $methods = (array) ($config['methods'] ?? []);
        if ($methods === []) {
            return false;
        }
        $active = ($this->getActiveMethod)();
        if ($active === null) {
            return false;
        }
        return in_array($active, $methods, true);
    }
}
