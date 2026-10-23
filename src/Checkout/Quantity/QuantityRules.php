<?php

declare(strict_types=1);

namespace App\Checkout\Quantity;

use App\Entity\Product\Product;
use Sylius\Component\Product\Model\ProductVariantInterface;

final readonly class QuantityRules
{
    private function __construct(
        public int $step,
        public int $minimum,
    ) {
    }

    public static function forVariant(?ProductVariantInterface $variant): self
    {
        $product = $variant?->getProduct();
        if (!$product instanceof Product) {
            return new self(1, 1);
        }

        $step = max(1, $product->getQuantityStep());

        return new self($step, max($product->getMinimumQuantity(), $step));
    }

    public function allows(int $quantity): bool
    {
        return $quantity >= $this->minimum && $quantity % $this->step === 0;
    }

    public function normalize(int $quantity): int
    {
        if ($quantity <= 0) {
            return 0;
        }

        return (int) (ceil(max($quantity, $this->minimum) / $this->step) * $this->step);
    }

    public function isRestricted(): bool
    {
        return $this->step > 1 || $this->minimum > 1;
    }
}
