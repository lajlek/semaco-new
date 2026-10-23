<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Taxon;

use App\Erp\Adapter\Helios\Infrastructure\HeliosConnectionFactory;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRateLimiter;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRetryTrait;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRow;
use App\Erp\Port\Catalog\Dto\TaxonSnapshot;
use App\Erp\Port\Catalog\TaxonSyncInterface;

final readonly class HeliosTaxonSyncAdapter implements TaxonSyncInterface
{
    use HeliosRetryTrait;

    public function __construct(
        private HeliosConnectionFactory $connections,
        private HeliosRateLimiter $rateLimiter,
    ) {
    }

    public function pullAll(): iterable
    {
        $this->rateLimiter->consume();
        $rows = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
            'SELECT id, idnadrazene, nazev, hidden, internetpopis2, katalltecky, _SapertaSeoName, _SapertaSeoKeyWords, poslednizmena
             FROM hvw_SapertaTabSapSortiment
             ORDER BY id ASC',
        ));

        $byId = [];
        foreach ($rows as $row) {
            $id = HeliosRow::string($row, 'id');
            if ($id !== null) {
                $byId[$id] = $row;
            }
        }

        $emitted = [];
        foreach (array_keys($byId) as $id) {
            yield from $this->emitWithAncestors((string) $id, $byId, $emitted, []);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $byId
     * @param array<string, true> $emitted
     * @param array<string, true> $path
     *
     * @return iterable<TaxonSnapshot>
     */
    private function emitWithAncestors(string $id, array $byId, array &$emitted, array $path): iterable
    {
        if (isset($emitted[$id]) || isset($path[$id]) || !isset($byId[$id])) {
            return;
        }

        $row = $byId[$id];
        $parentId = HeliosRow::string($row, 'idnadrazene');
        if ($parentId !== null && isset($byId[$parentId])) {
            yield from $this->emitWithAncestors($parentId, $byId, $emitted, $path + [$id => true]);
        } else {
            $parentId = null;
        }

        $emitted[$id] = true;

        yield new TaxonSnapshot(
            externalIdentifier: $id,
            parentExternalIdentifier: $parentId,
            name: HeliosRow::string($row, 'nazev') ?? $id,
            description: HeliosRow::string($row, 'internetpopis2'),
            metaTitle: HeliosRow::string($row, '_SapertaSeoName'),
            metaKeywords: HeliosRow::string($row, '_SapertaSeoKeyWords'),
            position: self::position(HeliosRow::string($row, 'katalltecky')),
            enabled: !HeliosRow::bool($row, 'hidden'),
            modifiedAt: HeliosRow::dateTime($row, 'poslednizmena'),
        );
    }

    private static function position(?string $catalogPath): int
    {
        if ($catalogPath === null) {
            return 0;
        }

        $segments = preg_split('/[^0-9]+/', $catalogPath, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $last = end($segments);

        return $last === false ? 0 : max(0, (int) $last - 1);
    }
}
