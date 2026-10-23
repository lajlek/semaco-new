<?php

declare(strict_types=1);

namespace App\B2B\Repository;

use App\B2B\Entity\PriceLevel;
use App\B2B\Entity\ProductPrice;
use App\Entity\Product\ProductVariant;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

interface ProductPriceRepositoryInterface extends RepositoryInterface
{
    public function findForVariantLevelCurrency(
        ProductVariant $variant,
        PriceLevel $priceLevel,
        string $currencyCode,
    ): ?ProductPrice;

    /**
     * @param list<string> $variantCodes
     *
     * @return list<ProductPrice>
     */
    public function findForLevelAndVariantCodes(
        PriceLevel $priceLevel,
        string $currencyCode,
        array $variantCodes,
    ): array;
}
