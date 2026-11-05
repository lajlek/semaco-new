<?php

declare(strict_types=1);

namespace App\Catalog\BrandMenu;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TaxonsWithProducts extends AbstractExtension implements ResetInterface
{
    /** @var array<int, true>|null */
    private ?array $taxonIds = null;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('app_taxon_has_products', $this->has(...))];
    }

    public function has(TaxonInterface $taxon): bool
    {
        if ($this->taxonIds === null) {
            /** @var list<int|string> $ids */
            $ids = $this->entityManager->getConnection()->fetchFirstColumn(
                'SELECT DISTINCT pt.taxon_id FROM sylius_product_taxon pt JOIN sylius_product p ON p.id = pt.product_id AND p.enabled = 1',
            );
            $this->taxonIds = array_fill_keys(array_map(intval(...), $ids), true);
        }

        return isset($this->taxonIds[(int) $taxon->getId()]);
    }

    public function reset(): void
    {
        $this->taxonIds = null;
    }
}
