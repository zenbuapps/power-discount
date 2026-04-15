<?php
declare(strict_types=1);

namespace PowerDiscount\Domain;

use InvalidArgumentException;

final class AddonItem
{
    private int $productId;
    private float $specialPrice;

    public function __construct(int $productId, float $specialPrice)
    {
        if ($productId <= 0) {
            throw new InvalidArgumentException('AddonItem product_id must be > 0');
        }
        if ($specialPrice < 0) {
            throw new InvalidArgumentException('AddonItem special_price must be >= 0');
        }
        $this->productId = $productId;
        $this->specialPrice = $specialPrice;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['product_id'] ?? 0),
            (float) ($data['special_price'] ?? 0)
        );
    }

    public function getProductId(): int { return $this->productId; }
    public function getSpecialPrice(): float { return $this->specialPrice; }

    /** @return array{product_id: int, special_price: float} */
    public function toArray(): array
    {
        return [
            'product_id'    => $this->productId,
            'special_price' => $this->specialPrice,
        ];
    }
}
