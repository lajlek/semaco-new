<?php

declare(strict_types=1);

namespace App\B2B\Repository;

use App\B2B\Entity\PriceLevel;
use App\B2B\Entity\ProductPrice;
use App\Entity\Product\ProductVariant;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

final class ProductPriceRepository extends EntityRepository implements ProductPriceRepositoryInterface
{
    public function findForVariantLevelCurrency(
        ProductVariant $variant,
        PriceLevel $priceLevel,
        string $currencyCode,
    ): ?ProductPrice {
        /** @var ProductPrice|null $result */
        $result = $this->findOneBy([
            'variant' => $variant,
            'priceLevel' => $priceLevel,
            'currencyCode' => $currencyCode,
        ]);

        return $result;
    }

    /**
     * @param list<string> $variantCodes
     *
     * @return list<ProductPrice>
     */
    public function findForLevelAndVariantCodes(
        PriceLevel $priceLevel,
        string $currencyCode,
        array $variantCodes,
    ): array {
        if ([] === $variantCodes) {
            return [];
        }

        /** @var list<ProductPrice> $result */
        $result = $this->createQueryBuilder('pp')
            ->innerJoin('pp.variant', 'v')
            ->andWhere('pp.priceLevel = :priceLevel')
            ->andWhere('pp.currencyCode = :currency')
            ->andWhere('v.code IN (:codes)')
            ->setParameter('priceLevel', $priceLevel)
            ->setParameter('currency', $currencyCode)
            ->setParameter('codes', $variantCodes)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
