<?php
declare(strict_types=1);

namespace PowerDiscount\Domain;

final class Rule
{
    private int $id;
    private string $title;
    private string $type;
    private int $status;
    private int $priority;
    private bool $exclusive;
    private ?int $startsAt;
    private ?int $endsAt;
    private ?int $usageLimit;
    private int $usedCount;
    private array $filters;
    private array $conditions;
    private array $config;
    private ?string $label;
    private ?string $notes;

    public function __construct(array $data)
    {
        $this->id         = (int) ($data['id'] ?? 0);
        $this->title      = (string) ($data['title'] ?? '');
        $this->type       = (string) ($data['type'] ?? '');
        $this->status     = (int) ($data['status'] ?? RuleStatus::ENABLED);
        $this->priority   = (int) ($data['priority'] ?? 10);
        $this->exclusive  = (bool) ($data['exclusive'] ?? false);
        $this->startsAt   = self::parseDate($data['starts_at'] ?? null);
        $this->endsAt     = self::parseDate($data['ends_at'] ?? null);
        $this->usageLimit = isset($data['usage_limit']) && $data['usage_limit'] !== null
            ? (int) $data['usage_limit']
            : null;
        $this->usedCount  = (int) ($data['used_count'] ?? 0);
        $this->filters    = (array) ($data['filters'] ?? []);
        $this->conditions = (array) ($data['conditions'] ?? []);
        $this->config     = (array) ($data['config'] ?? []);
        $this->label      = isset($data['label']) && $data['label'] !== '' ? (string) $data['label'] : null;
        $this->notes      = isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null;
    }

    public function getId(): int            { return $this->id; }
    public function getTitle(): string      { return $this->title; }
    public function getType(): string       { return $this->type; }
    public function getStatus(): int        { return $this->status; }
    public function getPriority(): int      { return $this->priority; }
    public function isEnabled(): bool       { return $this->status === RuleStatus::ENABLED; }
    public function isExclusive(): bool     { return $this->exclusive; }
    public function getFilters(): array     { return $this->filters; }
    public function getConditions(): array  { return $this->conditions; }
    public function getConfig(): array      { return $this->config; }
    public function getLabel(): ?string     { return $this->label; }
    public function getNotes(): ?string     { return $this->notes; }
    public function getUsageLimit(): ?int   { return $this->usageLimit; }
    public function getUsedCount(): int     { return $this->usedCount; }

    public function isActiveAt(int $timestamp): bool
    {
        if ($this->startsAt !== null && $timestamp < $this->startsAt) {
            return false;
        }
        if ($this->endsAt !== null && $timestamp > $this->endsAt) {
            return false;
        }
        return true;
    }

    public function isUsageLimitExhausted(): bool
    {
        if ($this->usageLimit === null) {
            return false;
        }
        return $this->usedCount >= $this->usageLimit;
    }

    private static function parseDate($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        $ts = strtotime((string) $value);
        return $ts === false ? null : $ts;
    }
}
