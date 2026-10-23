<?php

declare(strict_types=1);

namespace App\Erp\Port\Pricing;

use App\Erp\Port\Pricing\Dto\PriceLevelSnapshot;

interface PriceLevelSyncInterface
{
    /** @return iterable<PriceLevelSnapshot> */
    public function pullAll(): iterable;
}
