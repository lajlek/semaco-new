<?php

declare(strict_types=1);

namespace App\Migration\Step;

use App\Erp\Application\ImportResult;
use App\Migration\OldShop\OldShopConnectionFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 195)]
final readonly class ProductCreatedAtMigrationStep implements MigrationStepInterface
{
    public function __construct(
        private OldShopConnectionFactory $oldShop,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function name(): string
    {
        return 'product-created-at';
    }

    public function run(bool $dryRun): ImportResult
    {
        $result = new ImportResult('product-created-at');
        $connection = $this->entityManager->getConnection();

        $old = $this->oldShop->getConnection()->fetchAllKeyValue(
            'SELECT externalIdentifier, created_at FROM sylius_product WHERE externalIdentifier IS NOT NULL AND created_at IS NOT NULL',
        );
        $current = $connection->fetchAllKeyValue(
            'SELECT external_identifier, created_at FROM sylius_product WHERE external_identifier IS NOT NULL',
        );

        $changes = [];
        foreach ($current as $ext => $createdAt) {
            $previous = $old[$ext] ?? null;
            if (!is_string($previous) || $previous === $createdAt) {
                $result->skipped();

                continue;
            }
            $changes[(string) $ext] = $previous;
        }
        $result->updated(count($changes));
        if ($dryRun || $changes === []) {
            return $result;
        }

        $connection->transactional(static function (Connection $connection) use ($changes): void {
            foreach ($changes as $ext => $createdAt) {
                $connection->update('sylius_product', ['created_at' => $createdAt], ['external_identifier' => $ext]);
            }
        });

        return $result;
    }
}
