<?php
declare(strict_types=1);

namespace PowerDiscount\Domain;

final class AddonRule
{
    private int $id;
    private string $title;
    private int $status;
    private int $priority;
    /** @var AddonItem[] */
    private array $addonItems;
    /** @var int[] */
    private array $targetProductIds;
    private bool $excludeFromDiscounts;

    public function __construct(array $data)
    {
        $this->id       = (int) ($data['id'] ?? 0);
        $this->title    = (string) ($data['title'] ?? '');
        $this->status   = (int) ($data['status'] ?? RuleStatus::ENABLED);
        $this->priority = (int) ($data['priority'] ?? 10);

        $this->addonItems = [];
        foreach ((array) ($data['addon_items'] ?? []) as $raw) {
            if (!is_array($raw)) continue;
            try {
                $this->addonItems[] = AddonItem::fromArray($raw);
            } catch (\InvalidArgumentException $e) {
                // Skip invalid entries silently — form validation enforces at POST time
            }
        }

        $this->targetProductIds = array_values(array_filter(
            array_map('intval', (array) ($data['target_product_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        ));

        $this->excludeFromDiscounts = (bool) ($data['exclude_from_discounts'] ?? false);
    }

    public function getId(): int                   { return $this->id; }
    public function getTitle(): string             { return $this->title; }
    public function getStatus(): int               { return $this->status; }
    public function getPriority(): int             { return $this->priority; }
    public function isEnabled(): bool              { return $this->status === RuleStatus::ENABLED; }
    public function isExcludeFromDiscounts(): bool { return $this->excludeFromDiscounts; }

    /** @return AddonItem[] */
    public function getAddonItems(): array         { return $this->addonItems; }

    /** @return int[] */
    public function getTargetProductIds(): array   { return $this->targetProductIds; }

    public function matchesTarget(int $productId): bool
    {
        return in_array($productId, $this->targetProductIds, true);
    }

    public function containsAddon(int $productId): bool
    {
        foreach ($this->addonItems as $item) {
            if ($item->getProductId() === $productId) {
                return true;
            }
        }
        return false;
    }

    public function getSpecialPriceFor(int $addonProductId): ?float
    {
        foreach ($this->addonItems as $item) {
            if ($item->getProductId() === $addonProductId) {
                return $item->getSpecialPrice();
            }
        }
        return null;
    }
}
