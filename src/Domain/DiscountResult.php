<?php
declare(strict_types=1);

namespace PowerDiscount\Domain;

use InvalidArgumentException;

final class DiscountResult
{
    public const SCOPE_PRODUCT  = 'product';
    public const SCOPE_CART     = 'cart';
    public const SCOPE_SHIPPING = 'shipping';

    private const VALID_SCOPES = [
        self::SCOPE_PRODUCT,
        self::SCOPE_CART,
        self::SCOPE_SHIPPING,
    ];

    private int $ruleId;
    private string $ruleType;
    private string $scope;
    private float $amount;
    /** @var int[] */
    private array $affectedProductIds;
    private ?string $label;
    private array $meta;

    public function __construct(
        int $ruleId,
        string $ruleType,
        string $scope,
        float $amount,
        array $affectedProductIds,
        ?string $label,
        array $meta
    ) {
        if (!in_array($scope, self::VALID_SCOPES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid scope: %s', $scope));
        }
        if ($amount < 0) {
            throw new InvalidArgumentException('DiscountResult amount cannot be negative.');
        }
        $this->ruleId             = $ruleId;
        $this->ruleType           = $ruleType;
        $this->scope              = $scope;
        $this->amount             = $amount;
        $this->affectedProductIds = array_values(array_map('intval', $affectedProductIds));
        $this->label              = $label;
        $this->meta               = $meta;
    }

    public function getRuleId(): int                   { return $this->ruleId; }
    public function getRuleType(): string              { return $this->ruleType; }
    public function getScope(): string                 { return $this->scope; }
    public function getAmount(): float                 { return $this->amount; }
    public function getAffectedProductIds(): array     { return $this->affectedProductIds; }
    public function getLabel(): ?string                { return $this->label; }
    public function getMeta(): array                   { return $this->meta; }

    public function hasDiscount(): bool
    {
        return $this->amount > 0;
    }
}
