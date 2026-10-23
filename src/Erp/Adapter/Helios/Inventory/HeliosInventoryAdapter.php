<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Inventory;

use App\Erp\Adapter\Helios\Infrastructure\HeliosConnectionFactory;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRateLimiter;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRetryTrait;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRow;
use App\Erp\Port\Inventory\Dto\StockLevel;
use App\Erp\Port\Inventory\InventoryProviderInterface;
use Doctrine\DBAL\ArrayParameterType;

final readonly class HeliosInventoryAdapter implements InventoryProviderInterface
{
    use HeliosRetryTrait;

    private const BATCH_SIZE = 1000;

    public function __construct(
        private HeliosConnectionFactory $connections,
        private HeliosRateLimiter $rateLimiter,
    ) {
    }

    public function pullChangedSince(\DateTimeInterface $since): iterable
    {
        $offset = 0;
        while (true) {
            $this->rateLimiter->consume();
            $rows = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
                sprintf(
                    'SELECT id, idkmenzbozi, dostupnostm, poslednizmena FROM hvw_SapertaTabSapDostEshop
                     WHERE poslednizmena > :since
                     ORDER BY poslednizmena ASC, id ASC
                     OFFSET %d ROWS FETCH NEXT %d ROWS ONLY',
                    $offset,
                    self::BATCH_SIZE,
                ),
                ['since' => HeliosRow::formatSince($since)],
            ));

            if ($rows === []) {
                return;
            }

            yield from $this->mapRows($rows);

            if (count($rows) < self::BATCH_SIZE) {
                return;
            }

            $offset += self::BATCH_SIZE;
        }
    }

    public function pullForVariants(array $variantExternalIdentifiers): iterable
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $variantExternalIdentifiers), static fn (int $id): bool => $id > 0)));
        foreach (array_chunk($ids, 500) as $chunk) {
            $this->rateLimiter->consume();
            $rows = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
                'SELECT id, idkmenzbozi, dostupnostm, poslednizmena FROM hvw_SapertaTabSapDostEshop WHERE idkmenzbozi IN (:ids) ORDER BY id ASC',
                ['ids' => $chunk],
                ['ids' => ArrayParameterType::INTEGER],
            ));

            yield from $this->mapRows($rows);
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return iterable<StockLevel>
     */
    private function mapRows(array $rows): iterable
    {
        foreach ($rows as $row) {
            $variantId = HeliosRow::string($row, 'idkmenzbozi');
            if ($variantId === null) {
                continue;
            }

            yield new StockLevel(
                variantExternalIdentifier: $variantId,
                onHand: max(0, (int) floor(HeliosRow::float($row, 'dostupnostm') ?? 0.0)),
                modifiedAt: HeliosRow::dateTime($row, 'poslednizmena'),
            );
        }
    }
}
