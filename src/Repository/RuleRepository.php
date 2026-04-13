<?php
declare(strict_types=1);

namespace PowerDiscount\Repository;

use PowerDiscount\Domain\Rule;
use PowerDiscount\Domain\RuleStatus;
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
        return $this->db->insert($this->table(), $row);
    }

    public function update(Rule $rule): int
    {
        $row = $this->toRow($rule);
        $row['updated_at'] = gmdate('Y-m-d H:i:s');
        return $this->db->update($this->table(), $row, ['id' => $rule->getId()]);
    }

    public function delete(int $id): int
    {
        return $this->db->delete($this->table(), ['id' => $id]);
    }

    public function findById(int $id): ?Rule
    {
        $rows = $this->db->selectAll('SELECT_ALL_FROM:' . $this->table(), [
            static function (array $row) use ($id): bool {
                return (int) $row['id'] === $id;
            },
        ]);
        if ($rows === []) {
            return null;
        }
        return $this->hydrate($rows[0]);
    }

    /**
     * @return Rule[] ordered by priority ASC, id ASC
     */
    public function getActiveRules(): array
    {
        $rows = $this->db->selectAll('SELECT_ALL_FROM:' . $this->table(), [
            static function (array $row): bool {
                return (int) $row['status'] === RuleStatus::ENABLED;
            },
        ]);
        usort($rows, static function (array $a, array $b): int {
            $prio = ((int) $a['priority']) <=> ((int) $b['priority']);
            if ($prio !== 0) {
                return $prio;
            }
            return ((int) $a['id']) <=> ((int) $b['id']);
        });
        return array_map([$this, 'hydrate'], $rows);
    }

    public function incrementUsedCount(int $id): void
    {
        $rule = $this->findById($id);
        if ($rule === null) {
            return;
        }
        $this->db->update(
            $this->table(),
            ['used_count' => $rule->getUsedCount() + 1],
            ['id' => $id]
        );
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
            'starts_at'   => null,
            'ends_at'     => null,
            'usage_limit' => $rule->getUsageLimit(),
            'used_count'  => $rule->getUsedCount(),
            'filters'     => JsonSerializer::encode($rule->getFilters()),
            'conditions'  => JsonSerializer::encode($rule->getConditions()),
            'config'      => JsonSerializer::encode($rule->getConfig()),
            'label'       => $rule->getLabel(),
            'notes'       => $rule->getNotes(),
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
        ]);
    }
}
