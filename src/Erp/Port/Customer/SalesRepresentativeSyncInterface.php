<?php

declare(strict_types=1);

namespace App\Erp\Port\Customer;

use App\Erp\Port\Customer\Dto\SalesRepresentativeSnapshot;

interface SalesRepresentativeSyncInterface
{
    /** @return iterable<SalesRepresentativeSnapshot> */
    public function pullAll(): iterable;
}
