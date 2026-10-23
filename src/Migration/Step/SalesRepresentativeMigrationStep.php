<?php

declare(strict_types=1);

namespace App\Migration\Step;

use App\B2B\Entity\SalesRepresentative;
use App\Erp\Application\ImportResult;
use App\Migration\OldShop\OldShopConnectionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 100)]
final readonly class SalesRepresentativeMigrationStep implements MigrationStepInterface
{
    public function __construct(
        private OldShopConnectionFactory $oldShop,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function name(): string
    {
        return 'sales-representatives';
    }

    public function run(bool $dryRun): ImportResult
    {
        $result = new ImportResult('sales-representatives');
        $repository = $this->entityManager->getRepository(SalesRepresentative::class);

        /** @var list<array{externalIdentifier: string|null, code: string|null, name: string|null, email: string|null, phone_number: string|null}> $rows */
        $rows = $this->oldShop->getConnection()->fetchAllAssociative(
            'SELECT externalIdentifier, code, name, email, phone_number FROM mergocz_sylius_sales_representative',
        );

        foreach ($rows as $row) {
            if ($row['externalIdentifier'] === null || $row['externalIdentifier'] === '') {
                $result->warn(sprintf('Zástupce %s nemá Helios id, přeskočen', (string) $row['name']));
                $result->skipped();

                continue;
            }

            $representative = $repository->findOneBy(['externalIdentifier' => $row['externalIdentifier']]);
            if (!$representative instanceof SalesRepresentative) {
                $representative = new SalesRepresentative($row['externalIdentifier'], (string) $row['name']);
                $this->entityManager->persist($representative);
                $result->created();
            } else {
                $result->updated();
            }

            $representative->setName((string) $row['name']);
            $representative->setCode($row['code']);
            $representative->setEmail($row['email']);
            $representative->setPhoneNumber($row['phone_number']);
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }
}
