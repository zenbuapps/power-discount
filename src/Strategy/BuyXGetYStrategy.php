<?php
declare(strict_types=1);

namespace PowerDiscount\Strategy;

use PowerDiscount\Domain\CartContext;
use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Domain\Rule;

final class BuyXGetYStrategy implements DiscountStrategyInterface
{
    public function type(): string
    {
        return 'buy_x_get_y';
    }

    public function apply(Rule $rule, CartContext $context): ?DiscountResult
    {
        if ($context->isEmpty()) {
            return null;
        }

        $config = $rule->getConfig();
        $trigger = (array) ($config['trigger'] ?? []);
        $reward = (array) ($config['reward'] ?? []);
        $recursive = (bool) ($config['recursive'] ?? false);

        $triggerQty = (int) ($trigger['qty'] ?? 0);
        $rewardQty = (int) ($reward['qty'] ?? 0);
        if ($triggerQty <= 0 || $rewardQty <= 0) {
            return null;
        }

        $triggerSource = (string) ($trigger['source'] ?? 'filter');
        $triggerProductIds = array_map('intval', (array) ($trigger['product_ids'] ?? []));
        $rewardTarget = (string) ($reward['target'] ?? 'same');
        $rewardProductIds = array_map('intval', (array) ($reward['product_ids'] ?? []));
        $rewardMethod = (string) ($reward['method'] ?? 'free');
        $rewardValue = (float) ($reward['value'] ?? 0);

        // Flatten cart into units of (product_id, price), sorted by price desc.
        $allUnits = $this->flattenUnits($context);
        if ($allUnits === []) {
            return null;
        }

        // Identify trigger-eligible units.
        $triggerEligible = $this->filterEligibleTrigger($allUnits, $triggerSource, $triggerProductIds);
        if (count($triggerEligible) < $triggerQty) {
            return null;
        }

        $rounds = $recursive ? PHP_INT_MAX : 1;
        $totalDiscount = 0.0;
        $affected = [];

        // $remaining is a running pool of "units still available". We pull
        // trigger units out, then reward units, and repeat for recursive mode.
        $remaining = $allUnits;
        usort($remaining, static function (array $a, array $b): int {
            return $b['price'] <=> $a['price'];
        });

        for ($round = 0; $round < $rounds; $round++) {
            // Take triggerQty most-expensive trigger-eligible units.
            $takenTriggerKeys = [];
            $triggerProductIdsTaken = [];
            $takenCount = 0;
            foreach ($remaining as $key => $unit) {
                if ($takenCount >= $triggerQty) {
                    break;
                }
                if (!$this->isTriggerEligible($unit, $triggerSource, $triggerProductIds)) {
                    continue;
                }
                $takenTriggerKeys[] = $key;
                $triggerProductIdsTaken[$unit['product_id']] = true;
                $takenCount++;
            }
            if ($takenCount < $triggerQty) {
                break;
            }
            // Remove trigger units from remaining.
            foreach ($takenTriggerKeys as $k) {
                unset($remaining[$k]);
            }
            $remaining = array_values($remaining);

            // Pick reward units (selection depends on reward.target).
            $rewardUnits = $this->pickRewardUnits(
                $remaining,
                $rewardTarget,
                $rewardProductIds,
                array_keys($triggerProductIdsTaken),
                $rewardQty
            );
            if (count($rewardUnits) < $rewardQty) {
                break;
            }

            // Compute discount on reward units.
            foreach ($rewardUnits as $ru) {
                $totalDiscount += $this->rewardDiscount($ru['price'], $rewardMethod, $rewardValue);
                $affected[$ru['product_id']] = true;
                // Remove that reward from remaining to prevent re-use.
                foreach ($remaining as $rk => $runit) {
                    if ($runit['uid'] === $ru['uid']) {
                        unset($remaining[$rk]);
                        break;
                    }
                }
            }
            $remaining = array_values($remaining);
        }

        if ($totalDiscount <= 0) {
            return null;
        }

        return new DiscountResult(
            $rule->getId(),
            $rule->getType(),
            DiscountResult::SCOPE_PRODUCT,
            $totalDiscount,
            array_keys($affected),
            $rule->getLabel(),
            ['reward_target' => $rewardTarget, 'reward_method' => $rewardMethod]
        );
    }

    /**
     * @return array<int, array{uid:int,product_id:int,price:float}>
     */
    private function flattenUnits(CartContext $context): array
    {
        $units = [];
        $uid = 0;
        foreach ($context->getItems() as $item) {
            for ($i = 0; $i < $item->getQuantity(); $i++) {
                $units[] = [
                    'uid'        => $uid++,
                    'product_id' => $item->getProductId(),
                    'price'      => $item->getPrice(),
                ];
            }
        }
        return $units;
    }

    /**
     * @param array<int, array{product_id:int,price:float}> $units
     * @return array<int, array{product_id:int,price:float}>
     */
    private function filterEligibleTrigger(array $units, string $source, array $productIds): array
    {
        return array_values(array_filter(
            $units,
            function (array $u) use ($source, $productIds): bool {
                return $this->isTriggerEligible($u, $source, $productIds);
            }
        ));
    }

    /**
     * @param array{product_id:int,price:float} $unit
     */
    private function isTriggerEligible(array $unit, string $source, array $productIds): bool
    {
        if ($source === 'specific') {
            return in_array($unit['product_id'], $productIds, true);
        }
        return true; // 'filter' source: rule's own filter already narrowed the context
    }

    /**
     * @param array<int, array{product_id:int,price:float}> $remaining
     * @param array<int, int> $triggerProductIdsTaken  product_ids of units consumed as triggers this round
     * @return array<int, array{product_id:int,price:float}>
     */
    private function pickRewardUnits(
        array $remaining,
        string $target,
        array $rewardProductIds,
        array $triggerProductIdsTaken,
        int $rewardQty
    ): array {
        if ($remaining === []) {
            return [];
        }

        if ($target === 'specific') {
            $candidates = array_values(array_filter(
                $remaining,
                static function (array $u) use ($rewardProductIds): bool {
                    return in_array($u['product_id'], $rewardProductIds, true);
                }
            ));
            // Highest-priced reward first (max customer savings when free).
            usort($candidates, static function (array $a, array $b): int {
                return $b['price'] <=> $a['price'];
            });
            return array_slice($candidates, 0, $rewardQty);
        }

        if ($target === 'cheapest_in_cart') {
            $candidates = $remaining;
            usort($candidates, static function (array $a, array $b): int {
                return $a['price'] <=> $b['price'];
            });
            return array_slice($candidates, 0, $rewardQty);
        }

        // 'same': reward must share a product_id with one of the trigger units just consumed.
        $triggerIdSet = array_flip(array_map('intval', $triggerProductIdsTaken));
        $candidates = array_values(array_filter(
            $remaining,
            static function (array $u) use ($triggerIdSet): bool {
                return isset($triggerIdSet[$u['product_id']]);
            }
        ));
        usort($candidates, static function (array $a, array $b): int {
            return $b['price'] <=> $a['price'];
        });
        return array_slice($candidates, 0, $rewardQty);
    }

    private function rewardDiscount(float $unitPrice, string $method, float $value): float
    {
        switch ($method) {
            case 'free':
                return $unitPrice;
            case 'percentage':
                $capped = max(0.0, min(100.0, $value));
                return $unitPrice * ($capped / 100);
            case 'flat':
                return min($unitPrice, $value);
        }
        return 0.0;
    }
}
