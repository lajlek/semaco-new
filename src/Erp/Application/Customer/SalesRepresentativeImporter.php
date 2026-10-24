<?php

declare(strict_types=1);

namespace App\Erp\Application\Customer;

use App\B2B\Entity\SalesRepresentative;
use App\Entity\Customer\Customer;
use App\Entity\Customer\CustomerGroup;
use App\Erp\Application\ImportResult;
use App\Erp\Port\Customer\SalesRepresentativeSyncInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\User\Canonicalizer\CanonicalizerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class SalesRepresentativeImporter
{
    public function __construct(
        private SalesRepresentativeSyncInterface $salesRepresentativeSync,
        private EntityManagerInterface $entityManager,
        private CanonicalizerInterface $canonicalizer,
        #[Autowire('%app.b2b.sales_representative_group%')]
        private string $customerGroupCode,
    ) {
    }

    public function import(): ImportResult
    {
        $result = new ImportResult('sales_representatives');
        $group = $this->entityManager->getRepository(CustomerGroup::class)->findOneBy(['code' => $this->customerGroupCode]);

        foreach ($this->salesRepresentativeSync->pullAll() as $snapshot) {
            $representative = $this->entityManager->getRepository(SalesRepresentative::class)->findOneBy(['externalIdentifier' => $snapshot->externalIdentifier]);
            if (!$representative instanceof SalesRepresentative) {
                $representative = new SalesRepresentative($snapshot->externalIdentifier, $snapshot->getName());
                $this->entityManager->persist($representative);
                $result->created();
            } else {
                $result->updated();
            }

            $representative->setName($snapshot->getName());
            $representative->setCode($snapshot->code);
            $representative->setEmail($snapshot->email);
            $representative->setPhoneNumber($snapshot->phoneNumber);

            if ($snapshot->email === null || filter_var($snapshot->email, \FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $customer = $this->entityManager->getRepository(Customer::class)->findOneBy(['emailCanonical' => $this->canonicalizer->canonicalize($snapshot->email)]);
            if (!$customer instanceof Customer || $customer->getUser() === null) {
                $result->warn(sprintf('Obchodník %s nemá účet na webu', $snapshot->getName()));

                continue;
            }

            $customer->setSalesRepresentative($representative);
            if ($group instanceof CustomerGroupInterface) {
                $customer->setGroup($group);
            }
        }

        $this->entityManager->flush();

        return $result;
    }
}
