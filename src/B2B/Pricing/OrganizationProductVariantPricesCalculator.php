<?php

declare(strict_types=1);

namespace App\B2B\Pricing;

use App\Entity\Product\ProductVariant;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final readonly class OrganizationProductVariantPricesCalculator implements ProductVariantPricesCalculatorInterface
{
    public function __construct(
        private ProductVariantPricesCalculatorInterface $decorated,
        private OrganizationVariantPriceResolver $priceResolver,
    ) {
    }

    public function calculate(ProductVariantInterface $productVariant, array $context): int
    {
        return $this->organizationPrice($productVariant, $context) ?? $this->decorated->calculate($productVariant, $context);
    }

    public function calculateOriginal(ProductVariantInterface $productVariant, array $context): int
    {
        return $this->organizationPrice($productVariant, $context) ?? $this->decorated->calculateOriginal($productVariant, $context);
    }

    public function calculateLowestPriceBeforeDiscount(ProductVariantInterface $productVariant, array $context): ?int
    {
        return $this->decorated->calculateLowestPriceBeforeDiscount($productVariant, $context);
    }

    /** @param array<string, mixed> $context */
    private function organizationPrice(ProductVariantInterface $productVariant, array $context): ?int
    {
        $channel = $context['channel'] ?? null;
        if (!$productVariant instanceof ProductVariant || !$channel instanceof ChannelInterface) {
            return null;
        }

        return $this->priceResolver->resolve($productVariant, $channel);
    }
}
