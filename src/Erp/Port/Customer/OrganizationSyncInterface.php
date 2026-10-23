<?php

declare(strict_types=1);

namespace App\Erp\Port\Customer;

use App\Erp\Port\Customer\Dto\OrganizationSnapshot;

interface OrganizationSyncInterface
{
    /** @return iterable<OrganizationSnapshot> */
    public function pullChangedSince(\DateTimeInterface $since): iterable;

    /**
     * @param list<string> $externalIdentifiers
     *
     * @return iterable<OrganizationSnapshot>
     */
    public function pullByIds(array $externalIdentifiers): iterable;
}
