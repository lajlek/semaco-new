<?php

declare(strict_types=1);

namespace App\Partner\Repository;

use App\Partner\Entity\PartnerItem;
use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class PartnerItemRepository extends EntityRepository
{
    /**
     * @param list<string>|null $familyCodes
     *
     * @return list<PartnerItem>
     */
    public function findForPreparation(string $partnerCode, ?array $familyCodes = null): array
    {
        $queryBuilder = $this->preparationQuery($partnerCode);
        if ($familyCodes !== null) {
            $queryBuilder->andWhere('item.familyCode IN (:familyCodes)')->setParameter('familyCodes', $familyCodes);
        }

        /** @var list<PartnerItem> $items */
        $items = $queryBuilder->getQuery()->getResult();

        return $items;
    }

    /** @return list<PartnerItem> */
    public function findMatchedForPreparation(string $partnerCode): array
    {
        /** @var list<PartnerItem> $items */
        $items = $this->preparationQuery($partnerCode)->andWhere('variant.id IS NOT NULL')->getQuery()->getResult();

        return $items;
    }

    private function preparationQuery(string $partnerCode): QueryBuilder
    {
        return $this->createQueryBuilder('item')
            ->addSelect('variant', 'product', 'productTranslation', 'mainTaxon')
            ->leftJoin('item.matchedVariant', 'variant')
            ->leftJoin('variant.product', 'product')
            ->leftJoin('product.translations', 'productTranslation')
            ->leftJoin('product.mainTaxon', 'mainTaxon')
            ->andWhere('item.partnerCode = :partnerCode')
            ->setParameter('partnerCode', $partnerCode)
            ->orderBy('item.orderCode', 'ASC');
    }
}
