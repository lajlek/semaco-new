<?php

declare(strict_types=1);

namespace App\Erp\Port\Catalog;

use App\Erp\Port\Catalog\Dto\ProductSnapshot;

interface ProductSyncInterface
{
    /** @return iterable<ProductSnapshot> */
    public function pullChangedSince(\DateTimeInterface $since): iterable;

    /**
     * @param list<string> $externalIdentifiers
     *
     * @return iterable<ProductSnapshot>
     */
    public function pullByIds(array $externalIdentifiers): iterable;

    /** @return iterable<string> */
    public function pullAllExternalIdentifiers(): iterable;
}
