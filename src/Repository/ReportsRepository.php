<?php
declare(strict_types=1);

namespace PowerDiscount\Repository;

use PowerDiscount\Persistence\DatabaseAdapter;

final class ReportsRepository
{
    private const TABLE = 'pd_order_discounts';

    private DatabaseAdapter $db;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
    }

    /**
     * @return array<int, array{rule_id:int,rule_title:string,rule_type:string,count:int,total_amount:float}>
     */
    public function getRuleStats(): array
    {
        $rows = $this->db->findWhere($this->db->table(self::TABLE), []);
        $byRuleId = [];

        foreach ($rows as $row) {
            $ruleId = (int) ($row['rule_id'] ?? 0);
            if ($ruleId <= 0) {
                continue;
            }
            if (!isset($byRuleId[$ruleId])) {
                $byRuleId[$ruleId] = [
                    'rule_id'      => $ruleId,
                    'rule_title'   => (string) ($row['rule_title'] ?? ''),
                    'rule_type'    => (string) ($row['rule_type'] ?? ''),
                    'count'        => 0,
                    'total_amount' => 0.0,
                    '_max_id'      => 0,
                ];
            }
            $byRuleId[$ruleId]['count']++;
            $byRuleId[$ruleId]['total_amount'] += (float) ($row['discount_amount'] ?? 0);

            // Track the most recent (highest id) row's title as the canonical title.
            $rowId = (int) ($row['id'] ?? 0);
            if ($rowId >= $byRuleId[$ruleId]['_max_id']) {
                $byRuleId[$ruleId]['_max_id'] = $rowId;
                $byRuleId[$ruleId]['rule_title'] = (string) ($row['rule_title'] ?? '');
                $byRuleId[$ruleId]['rule_type'] = (string) ($row['rule_type'] ?? '');
            }
        }

        $stats = array_values(array_map(static function (array $entry): array {
            unset($entry['_max_id']);
            return $entry;
        }, $byRuleId));

        usort($stats, static function (array $a, array $b): int {
            return $b['total_amount'] <=> $a['total_amount'];
        });

        return $stats;
    }

    /**
     * Compute all reports in one DB scan. Preferred entry point.
     *
     * @return array{stats:array<int,array<string,mixed>>, total_discount:float, total_orders:int}
     */
    public function getSummary(): array
    {
        $rows = $this->db->findWhere($this->db->table(self::TABLE), []);
        $byRuleId = [];
        $totalDiscount = 0.0;
        $orderIds = [];

        foreach ($rows as $row) {
            $ruleId = (int) ($row['rule_id'] ?? 0);
            $rowId = (int) ($row['id'] ?? 0);
            $amount = (float) ($row['discount_amount'] ?? 0);
            $orderId = (int) ($row['order_id'] ?? 0);

            $totalDiscount += $amount;
            if ($orderId > 0) {
                $orderIds[$orderId] = true;
            }

            if ($ruleId <= 0) {
                continue;
            }
            if (!isset($byRuleId[$ruleId])) {
                $byRuleId[$ruleId] = [
                    'rule_id'      => $ruleId,
                    'rule_title'   => (string) ($row['rule_title'] ?? ''),
                    'rule_type'    => (string) ($row['rule_type'] ?? ''),
                    'count'        => 0,
                    'total_amount' => 0.0,
                    '_max_id'      => 0,
                ];
            }
            $byRuleId[$ruleId]['count']++;
            $byRuleId[$ruleId]['total_amount'] += $amount;

            if ($rowId > $byRuleId[$ruleId]['_max_id']) {
                $byRuleId[$ruleId]['_max_id'] = $rowId;
                $byRuleId[$ruleId]['rule_title'] = (string) ($row['rule_title'] ?? '');
                $byRuleId[$ruleId]['rule_type'] = (string) ($row['rule_type'] ?? '');
            }
        }

        $stats = array_values(array_map(static function (array $entry): array {
            unset($entry['_max_id']);
            return $entry;
        }, $byRuleId));

        usort($stats, static function (array $a, array $b): int {
            return $b['total_amount'] <=> $a['total_amount'];
        });

        return [
            'stats'          => $stats,
            'total_discount' => $totalDiscount,
            'total_orders'   => count($orderIds),
        ];
    }

    public function getTotalDiscount(): float
    {
        $rows = $this->db->findWhere($this->db->table(self::TABLE), []);
        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float) ($row['discount_amount'] ?? 0);
        }
        return $sum;
    }

    public function getTotalOrdersAffected(): int
    {
        $rows = $this->db->findWhere($this->db->table(self::TABLE), []);
        $orderIds = [];
        foreach ($rows as $row) {
            $orderIds[(int) ($row['order_id'] ?? 0)] = true;
        }
        unset($orderIds[0]);
        return count($orderIds);
    }
}
