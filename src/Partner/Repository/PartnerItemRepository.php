<?php

declare(strict_types=1);

namespace App\Partner\Repository;

use App\Partner\Entity\PartnerItem;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class PartnerItemRepository extends EntityRepository
{
    /** @return list<PartnerItem> */
    public function findForPreparation(string $partnerCode): array
    {
        /** @var list<PartnerItem> $items */
        $items = $this->createQueryBuilder('item')
            ->addSelect('variant', 'product', 'productTranslation', 'mainTaxon')
            ->leftJoin('item.matchedVariant', 'variant')
            ->leftJoin('variant.product', 'product')
            ->leftJoin('product.translations', 'productTranslation')
            ->leftJoin('product.mainTaxon', 'mainTaxon')
            ->andWhere('item.partnerCode = :partnerCode')
            ->setParameter('partnerCode', $partnerCode)
            ->orderBy('item.orderCode', 'ASC')
            ->getQuery()
            ->getResult();

        return $items;
    }
}
