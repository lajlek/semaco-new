<?php

declare(strict_types=1);

namespace App\B2B\Repository;

use App\B2B\Entity\Organization;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/** @extends RepositoryInterface<Organization> */
interface OrganizationRepositoryInterface extends RepositoryInterface
{
    public function findOneByExternalIdentifier(string $externalIdentifier): ?Organization;

    public function countCustomers(Organization $organization): int;
}
