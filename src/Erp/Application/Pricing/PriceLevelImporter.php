<?php

declare(strict_types=1);

namespace App\Erp\Application\Pricing;

use App\B2B\Entity\PriceLevel;
use App\B2B\Repository\PriceLevelRepositoryInterface;
use App\Erp\Application\ImportResult;
use App\Erp\Port\Pricing\PriceLevelSyncInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PriceLevelImporter
{
    public function __construct(
        private PriceLevelSyncInterface $priceLevelSync,
        private PriceLevelRepositoryInterface $priceLevelRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function import(): ImportResult
    {
        $result = new ImportResult('price_levels');

        foreach ($this->priceLevelSync->pullAll() as $snapshot) {
            $level = $this->priceLevelRepository->findOneByExternalIdentifier($snapshot->externalIdentifier);
            if ($level === null) {
                $level = new PriceLevel($snapshot->externalIdentifier, $snapshot->name, $snapshot->externalIdentifier);
                $this->entityManager->persist($level);
                $result->created();
            } else {
                $level->setName($snapshot->name);
                $result->updated();
            }
        }

        $this->entityManager->flush();

        return $result;
    }
}
