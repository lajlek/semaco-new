<?php

declare(strict_types=1);

namespace App\Partner\Service;

use App\Entity\Product\ProductVariant;
use App\Partner\Entity\PartnerItem;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PartnerItemMatcher
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function match(PartnerItem $item): void
    {
        /** @var list<ProductVariant> $variants */
        $variants = $this->entityManager->getRepository(ProductVariant::class)->findBy(['regcis' => $item->getOrderCode()]);
        $enabled = array_values(array_filter($variants, static fn (ProductVariant $variant): bool => $variant->isEnabled()));
        $candidates = $enabled !== [] ? $enabled : $variants;

        match (count($candidates)) {
            0 => $item->match(PartnerItem::MATCH_UNMATCHED, null),
            1 => $item->match(PartnerItem::MATCH_MATCHED, $candidates[0]),
            default => $item->match(PartnerItem::MATCH_AMBIGUOUS, null),
        };
    }
}
