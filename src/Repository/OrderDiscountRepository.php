<?php
declare(strict_types=1);

namespace PowerDiscount\Repository;

use PowerDiscount\Domain\DiscountResult;
use PowerDiscount\Persistence\DatabaseAdapter;
use PowerDiscount\Persistence\JsonSerializer;

final class OrderDiscountRepository
{
    private const TABLE = 'pd_order_discounts';

    private DatabaseAdapter $db;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
    }

    /**
     * @param DiscountResult[] $results
     * @param array<int|string, string> $ruleTitlesById
     */
    public function record(int $orderId, array $results, array $ruleTitlesById): void
    {
        $now = gmdate('Y-m-d H:i:s');
        foreach ($results as $result) {
            if (!$result->hasDiscount()) {
                continue;
            }
            $this->db->insert($this->db->table(self::TABLE), [
                'order_id'        => $orderId,
                'rule_id'         => $result->getRuleId(),
                'rule_title'      => (string) ($ruleTitlesById[$result->getRuleId()] ?? ''),
                'rule_type'       => $result->getRuleType(),
                'discount_amount' => $result->getAmount(),
                'scope'           => $result->getScope(),
                'meta'            => JsonSerializer::encode($result->getMeta()),
                'created_at'      => $now,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByOrderId(int $orderId): array
    {
        return $this->db->selectAll('SELECT_ALL_FROM:' . $this->db->table(self::TABLE), [
            static function (array $row) use ($orderId): bool {
                return (int) $row['order_id'] === $orderId;
            },
        ]);
    }
}
