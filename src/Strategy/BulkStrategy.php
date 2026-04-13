<?php
declare(strict_types=1);

namespace PowerDiscount\Strategy;

use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\CartItem;
use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Domain\Rule;

final class BulkStrategy implements DiscountStrategyInterface
{
    public function type(): string
    {
        return 'bulk';
    }

    public function apply(Rule $rule, CartContext $context): ?DiscountResult
    {
        if ($context->isEmpty()) {
            return null;
        }

        $config = $rule->getConfig();
        $scope  = (string) ($config['count_scope'] ?? 'cumulative');
        $ranges = (array) ($config['ranges'] ?? []);

        if ($ranges === []) {
            return null;
        }

        $totalDiscount = 0.0;
        $affected = [];

        if ($scope === 'per_item') {
            foreach ($context->getItems() as $item) {
                $range = $this->findRange($ranges, $item->getQuantity());
                if ($range === null) {
                    continue;
                }
                $d = $this->calculateForItem($range, $item, $item->getQuantity());
                if ($d > 0) {
                    $totalDiscount += $d;
                    $affected[] = $item->getProductId();
                }
            }
        } else {
            // cumulative
            $totalQty = $context->getTotalQuantity();
            $range = $this->findRange($ranges, $totalQty);
            if ($range !== null) {
                foreach ($context->getItems() as $item) {
                    $d = $this->calculateForItem($range, $item, $item->getQuantity());
                    if ($d > 0) {
                        $totalDiscount += $d;
                        $affected[] = $item->getProductId();
                    }
                }
            }
        }

        if ($totalDiscount <= 0) {
            return null;
        }

        return new DiscountResult(
            $rule->getId(),
            $rule->getType(),
            DiscountResult::SCOPE_PRODUCT,
            $totalDiscount,
            $affected,
            $rule->getLabel(),
            []
        );
    }

    /** @return array{from:int,to:?int,method:string,value:float}|null */
    private function findRange(array $ranges, int $qty): ?array
    {
        foreach ($ranges as $r) {
            $from = (int) ($r['from'] ?? 0);
            $to   = isset($r['to']) && $r['to'] !== null ? (int) $r['to'] : null;
            if ($qty >= $from && ($to === null || $qty <= $to)) {
                $method = (string) ($r['method'] ?? '');
                $value  = (float) ($r['value'] ?? 0);
                if ($value <= 0 || !in_array($method, ['percentage', 'flat'], true)) {
                    return null;
                }
                return ['from' => $from, 'to' => $to, 'method' => $method, 'value' => $value];
            }
        }
        return null;
    }

    private function calculateForItem(array $range, CartItem $item, int $qtyCount): float
    {
        $price = $item->getPrice();
        if ($range['method'] === 'percentage') {
            return $price * ($range['value'] / 100) * $qtyCount;
        }
        // flat per unit, capped at price
        return min($price, $range['value']) * $qtyCount;
    }
}
