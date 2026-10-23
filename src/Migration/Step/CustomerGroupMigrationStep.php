<?php

declare(strict_types=1);

namespace App\Migration\Step;

use App\Erp\Application\ImportResult;
use App\Migration\OldShop\OldShopConnectionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsTaggedItem(priority: 90)]
final readonly class CustomerGroupMigrationStep implements MigrationStepInterface
{
    /**
     * @param RepositoryInterface<CustomerGroupInterface> $customerGroupRepository
     * @param FactoryInterface<CustomerGroupInterface> $customerGroupFactory
     */
    public function __construct(
        private OldShopConnectionFactory $oldShop,
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'sylius.repository.customer_group')]
        private RepositoryInterface $customerGroupRepository,
        #[Autowire(service: 'sylius.factory.customer_group')]
        private FactoryInterface $customerGroupFactory,
    ) {
    }

    public function name(): string
    {
        return 'customer-groups';
    }

    public function run(bool $dryRun): ImportResult
    {
        $result = new ImportResult('customer-groups');

        /** @var list<array{code: string, name: string}> $rows */
        $rows = $this->oldShop->getConnection()->fetchAllAssociative('SELECT code, name FROM sylius_customer_group');

        foreach ($rows as $row) {
            $group = $this->customerGroupRepository->findOneBy(['code' => $row['code']]);
            if (!$group instanceof CustomerGroupInterface) {
                $group = $this->customerGroupFactory->createNew();
                $group->setCode($row['code']);
                $this->entityManager->persist($group);
                $result->created();
            } else {
                $result->updated();
            }

            $group->setName($row['name']);
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }
}
