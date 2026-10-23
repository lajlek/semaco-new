<?php

declare(strict_types=1);

namespace App\Erp\Port\Inventory;

use App\Erp\Port\Inventory\Dto\StockLevel;

interface InventoryProviderInterface
{
    /** @return iterable<StockLevel> */
    public function pullChangedSince(\DateTimeInterface $since): iterable;

    /**
     * @param list<string> $variantExternalIdentifiers
     *
     * @return iterable<StockLevel>
     */
    public function pullForVariants(array $variantExternalIdentifiers): iterable;
}
