<?php
declare(strict_types=1);

namespace PowerDiscount\Repository;

use PowerDiscount\Domain\Rule;
use PowerDiscount\Domain\RuleStatus;
use PowerDiscount\Integration\RuleCacheBuster;
use PowerDiscount\Persistence\DatabaseAdapter;
use PowerDiscount\Persistence\JsonSerializer;

final class RuleRepository
{
    private const TABLE = 'pd_rules';

    private DatabaseAdapter $db;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
    }

    public function insert(Rule $rule): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $row = $this->toRow($rule);
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        $id = $this->db->insert($this->table(), $row);
        RuleCacheBuster::bump();
        return $id;
    }

    public function update(Rule $rule): int
    {
        $row = $this->toRow($rule);
        unset($row['used_count']); // counter is mutated only via incrementUsedCount
        $row['updated_at'] = gmdate('Y-m-d H:i:s');
        $affected = $this->db->update($this->table(), $row, ['id' => $rule->getId()]);
        RuleCacheBuster::bump();
        return $affected;
    }

    public function delete(int $id): int
    {
        $affected = $this->db->delete($this->table(), ['id' => $id]);
        RuleCacheBuster::bump();
        return $affected;
    }

    public function findById(int $id): ?Rule
    {
        $row = $this->db->findById($this->table(), $id);
        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * @return Rule[] ordered by priority ASC, id ASC
     */
    public function getActiveRules(): array
    {
        $rows = $this->db->findWhere(
            $this->table(),
            ['status' => RuleStatus::ENABLED],
            ['priority' => 'ASC', 'id' => 'ASC']
        );
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @return Rule[] all rules ordered by priority ASC, id ASC (regardless of status)
     */
    public function findAll(): array
    {
        $rows = $this->db->findWhere(
            $this->table(),
            [],
            ['priority' => 'ASC', 'id' => 'ASC']
        );
        return array_map([$this, 'hydrate'], $rows);
    }

    public function incrementUsedCount(int $id): void
    {
        $this->db->incrementColumn($this->table(), 'used_count', ['id' => $id]);
    }

    private function table(): string
    {
        return $this->db->table(self::TABLE);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Rule $rule): array
    {
        return [
            'title'       => $rule->getTitle(),
            'type'        => $rule->getType(),
            'status'      => $rule->getStatus(),
            'priority'    => $rule->getPriority(),
            'exclusive'   => $rule->isExclusive() ? 1 : 0,
            'starts_at'   => $rule->getStartsAt(),
            'ends_at'     => $rule->getEndsAt(),
            'usage_limit' => $rule->getUsageLimit(),
            'used_count'  => $rule->getUsedCount(),
            'filters'     => JsonSerializer::encode($rule->getFilters()),
            'conditions'  => JsonSerializer::encode($rule->getConditions()),
            'config'      => JsonSerializer::encode($rule->getConfig()),
            'label'       => $rule->getLabel(),
            'notes'       => $rule->getNotes(),
            'schedule_meta' => JsonSerializer::encode($rule->getScheduleMeta()),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Rule
    {
        return new Rule([
            'id'          => (int) ($row['id'] ?? 0),
            'title'       => (string) ($row['title'] ?? ''),
            'type'        => (string) ($row['type'] ?? ''),
            'status'      => (int) ($row['status'] ?? RuleStatus::ENABLED),
            'priority'    => (int) ($row['priority'] ?? 10),
            'exclusive'   => (bool) ($row['exclusive'] ?? false),
            'starts_at'   => $row['starts_at'] ?? null,
            'ends_at'     => $row['ends_at'] ?? null,
            'usage_limit' => $row['usage_limit'] ?? null,
            'used_count'  => (int) ($row['used_count'] ?? 0),
            'filters'     => JsonSerializer::decode((string) ($row['filters'] ?? '')),
            'conditions'  => JsonSerializer::decode((string) ($row['conditions'] ?? '')),
            'config'      => JsonSerializer::decode((string) ($row['config'] ?? '')),
            'label'       => $row['label'] ?? null,
            'notes'       => $row['notes'] ?? null,
            'schedule_meta' => JsonSerializer::decode((string) ($row['schedule_meta'] ?? '')),
        ]);
    }

    /**
     * Reassign priority values 1..N based on the given ordered rule IDs.
     *
     * @param int[] $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        $position = 1;
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if ($id <= 0) continue;
            $this->db->update(
                $this->table(),
                ['priority' => $position, 'updated_at' => gmdate('Y-m-d H:i:s')],
                ['id' => $id]
            );
            $position++;
        }
        RuleCacheBuster::bump();
    }

    public function getMaxPriority(): int
    {
        $rules = $this->findAll();
        $max = 0;
        foreach ($rules as $rule) {
            if ($rule->getPriority() > $max) {
                $max = $rule->getPriority();
            }
        }
        return $max;
    }
}
