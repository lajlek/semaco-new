<?php

declare(strict_types=1);

namespace App\Erp\Port\Catalog;

use App\Erp\Port\Catalog\Dto\TaxonSnapshot;

interface TaxonSyncInterface
{
    /** @return iterable<TaxonSnapshot> parents always precede their children */
    public function pullAll(): iterable;
}
