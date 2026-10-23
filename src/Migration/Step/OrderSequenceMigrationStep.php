<?php

declare(strict_types=1);

namespace App\Migration\Step;

use App\Entity\Order\OrderSequence;
use App\Erp\Application\ImportResult;
use App\Migration\OldShop\OldShopConnectionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 40)]
final readonly class OrderSequenceMigrationStep implements MigrationStepInterface
{
    public function __construct(
        private OldShopConnectionFactory $oldShop,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function name(): string
    {
        return 'order-sequence';
    }

    public function run(bool $dryRun): ImportResult
    {
        $result = new ImportResult('order-sequence');
        $old = $this->oldShop->getConnection();

        $sequenceIndex = $old->fetchOne('SELECT COALESCE(MAX(idx), 0) FROM sylius_order_sequence');
        $highestNumber = $old->fetchOne('SELECT COALESCE(MAX(CAST(number AS UNSIGNED)), 0) FROM sylius_order WHERE number REGEXP \'^[0-9]+$\'');
        $oldIndex = max(is_numeric($sequenceIndex) ? (int) $sequenceIndex : 0, is_numeric($highestNumber) ? (int) $highestNumber : 0);

        $sequence = $this->entityManager->getRepository(OrderSequence::class)->findOneBy([]);
        if (!$sequence instanceof OrderSequence) {
            $sequence = new OrderSequence();
            $this->entityManager->persist($sequence);
        }

        if ($sequence->getIndex() >= $oldIndex) {
            $result->skipped();

            return $result;
        }

        $result->warn(sprintf('Číslování objednávek: %d → %d (další objednávka %09d)', $sequence->getIndex(), $oldIndex, $oldIndex + 1));
        while ($sequence->getIndex() < $oldIndex) {
            $sequence->incrementIndex();
        }
        $result->updated();

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }
}
