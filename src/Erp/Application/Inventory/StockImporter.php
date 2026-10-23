<?php

declare(strict_types=1);

namespace App\Erp\Application\Inventory;

use App\Erp\Application\ImportResult;
use App\Erp\Port\Inventory\Dto\StockLevel;
use App\Erp\Port\Inventory\InventoryProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class StockImporter
{
    public function __construct(
        private InventoryProviderInterface $inventoryProvider,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function importChangedSince(\DateTimeInterface $since, ?\DateTimeImmutable &$maxModifiedAt = null): ImportResult
    {
        return $this->importLevels($this->inventoryProvider->pullChangedSince($since), $maxModifiedAt);
    }

    /** @param list<string> $variantExternalIdentifiers */
    public function importForVariants(array $variantExternalIdentifiers): ImportResult
    {
        return $this->importLevels($this->inventoryProvider->pullForVariants($variantExternalIdentifiers));
    }

    /** @param iterable<StockLevel> $levels */
    private function importLevels(iterable $levels, ?\DateTimeImmutable &$maxModifiedAt = null): ImportResult
    {
        $result = new ImportResult('stock');
        $update = $this->entityManager->createQuery(
            'UPDATE App\Entity\Product\ProductVariant v SET v.onHand = :onHand WHERE v.externalIdentifier = :id',
        );

        foreach ($levels as $level) {
            if ($maxModifiedAt === null || $level->modifiedAt > $maxModifiedAt) {
                $maxModifiedAt = $level->modifiedAt;
            }

            $affected = $update->execute(['onHand' => $level->onHand, 'id' => $level->variantExternalIdentifier]);
            $affected > 0 ? $result->updated() : $result->skipped();
        }

        return $result;
    }
}
