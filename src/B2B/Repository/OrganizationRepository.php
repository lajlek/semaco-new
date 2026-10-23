<?php

declare(strict_types=1);

namespace App\B2B\Repository;

use App\B2B\Entity\Organization;
use App\Entity\Customer\Customer;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

final class OrganizationRepository extends EntityRepository implements OrganizationRepositoryInterface
{
    public function findOneByExternalIdentifier(string $externalIdentifier): ?Organization
    {
        /** @var Organization|null $result */
        $result = $this->findOneBy(['externalIdentifier' => $externalIdentifier]);

        return $result;
    }

    public function countCustomers(Organization $organization): int
    {
        $count = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Customer::class, 'c')
            ->where('c.organization = :organization')
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $count;
    }
}
