<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Product;

use App\Erp\Adapter\Helios\Infrastructure\HeliosConnectionFactory;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRateLimiter;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRetryTrait;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRow;
use App\Erp\Port\Catalog\Dto\ProductSnapshot;
use App\Erp\Port\Catalog\ProductSyncInterface;
use Doctrine\DBAL\ArrayParameterType;

final readonly class HeliosProductSyncAdapter implements ProductSyncInterface
{
    use HeliosRetryTrait;

    private const BATCH_SIZE = 250;

    private const BATCH_SLEEP_MS = 100;

    private const COLUMNS = 'id, idpredloha, regcis, skupzbo, nazev1, titulek, _Semaco_VariantaNazev, internetpopis1, internetpopis2,
        html1, html2, _SapertaSeoKeyWords, _SapertaSeoPopisek, hidden, blokovano, druhskladu, baleniv, minimum_odberatel,
        znacka, znackanaz, hmotnost, vyska, hloubka, BarCode, poslednizmena, DatumPosledniZmenaEshop';

    public function __construct(
        private HeliosConnectionFactory $connections,
        private HeliosRateLimiter $rateLimiter,
        private HeliosProductRowMapper $mapper,
    ) {
    }

    public function pullChangedSince(\DateTimeInterface $since): iterable
    {
        $groupTypes = $this->loadGroupTypes();
        $offset = 0;

        while (true) {
            $this->rateLimiter->consume();
            $masters = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
                sprintf(
                    'SELECT %s, %s AS incremental_modified_at
                     FROM hvw_SapertaTabSapKmenZbozi
                     OUTER APPLY (
                         SELECT MAX(CASE WHEN child.DatumPosledniZmenaEshop > child.poslednizmena OR child.poslednizmena IS NULL
                                         THEN child.DatumPosledniZmenaEshop ELSE child.poslednizmena END) AS modified_at
                         FROM hvw_SapertaTabSapKmenZbozi child
                         WHERE child.idpredloha = hvw_SapertaTabSapKmenZbozi.id
                     ) variant_change
                     WHERE idpredloha IS NULL
                       AND (poslednizmena > :since OR DatumPosledniZmenaEshop > :since OR variant_change.modified_at > :since)
                     ORDER BY incremental_modified_at ASC, id ASC
                     OFFSET %d ROWS FETCH NEXT %d ROWS ONLY',
                    self::COLUMNS,
                    self::modifiedAtSql(),
                    $offset,
                    self::BATCH_SIZE,
                ),
                ['since' => HeliosRow::formatSince($since)],
            ));

            if ($masters === []) {
                return;
            }

            yield from $this->mapMasters($masters, $groupTypes);

            if (count($masters) < self::BATCH_SIZE) {
                return;
            }

            $offset += self::BATCH_SIZE;
            usleep(self::BATCH_SLEEP_MS * 1000);
        }
    }

    public function pullByIds(array $externalIdentifiers): iterable
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $externalIdentifiers), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }

        $groupTypes = $this->loadGroupTypes();
        foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
            $this->rateLimiter->consume();
            $masters = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
                sprintf('SELECT %s FROM hvw_SapertaTabSapKmenZbozi WHERE idpredloha IS NULL AND id IN (:ids) ORDER BY id ASC', self::COLUMNS),
                ['ids' => $chunk],
                ['ids' => ArrayParameterType::INTEGER],
            ));

            yield from $this->mapMasters($masters, $groupTypes);
        }
    }

    public function pullAllExternalIdentifiers(): iterable
    {
        $this->rateLimiter->consume();
        $ids = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchFirstColumn(
            'SELECT id FROM hvw_SapertaTabSapKmenZbozi WHERE idpredloha IS NULL ORDER BY id ASC',
        ));

        foreach ($ids as $id) {
            if (is_scalar($id)) {
                yield (string) $id;
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $masters
     * @param array<array-key, string> $groupTypes
     *
     * @return iterable<ProductSnapshot>
     */
    private function mapMasters(array $masters, array $groupTypes): iterable
    {
        $masterIds = array_values(array_filter(array_map(static fn (array $row): ?int => HeliosRow::int($row, 'id'), $masters)));
        $children = $this->loadChildren($masterIds);

        $allIds = $masterIds;
        foreach ($children as $rows) {
            foreach ($rows as $row) {
                $allIds[] = (HeliosRow::int($row, 'id') ?? 0);
            }
        }

        $properties = $this->loadProperties($allIds);
        $taxonLinks = $this->loadTaxonLinks($masterIds);

        foreach ($masters as $master) {
            if (!self::isStockItem($master)) {
                continue;
            }

            $id = (HeliosRow::int($master, 'id') ?? 0);
            $variantRows = [$master];
            foreach ($children[$id] ?? [] as $child) {
                if (self::isStockItem($child)) {
                    $variantRows[] = $child;
                }
            }

            yield $this->mapper->mapProduct(
                $master,
                $variantRows,
                $properties,
                $groupTypes,
                $taxonLinks[$id] ?? [],
            );
        }
    }

    /** @param array<string, mixed> $row */
    private static function isStockItem(array $row): bool
    {
        return HeliosRow::int($row, 'druhskladu') !== 0;
    }

    /**
     * @param list<int> $masterIds
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function loadChildren(array $masterIds): array
    {
        if ($masterIds === []) {
            return [];
        }

        $this->rateLimiter->consume();
        $rows = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
            sprintf('SELECT %s FROM hvw_SapertaTabSapKmenZbozi WHERE idpredloha IN (:ids) ORDER BY id ASC', self::COLUMNS),
            ['ids' => $masterIds],
            ['ids' => ArrayParameterType::INTEGER],
        ));

        $index = [];
        foreach ($rows as $row) {
            $index[(HeliosRow::int($row, 'idpredloha') ?? 0)][] = $row;
        }

        return $index;
    }

    /**
     * @param list<int> $itemIds
     *
     * @return array<int, list<array{group: string, dictionary: mixed, value: string}>>
     */
    private function loadProperties(array $itemIds): array
    {
        $index = [];
        foreach (array_chunk($itemIds, 1000) as $chunk) {
            $this->rateLimiter->consume();
            $rows = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
                'SELECT l_id, p_id, idciselnikuvlast, DVAtr1856042231 AS value
                 FROM hvw_SapertaTabSapVlastnosti
                 WHERE l_id IN (:ids) AND DVAtr1856042231 IS NOT NULL AND DVAtr1856042231 <> \'\'
                 ORDER BY l_id ASC, p_id ASC, dvatrporadi ASC',
                ['ids' => $chunk],
                ['ids' => ArrayParameterType::INTEGER],
            ));

            foreach ($rows as $row) {
                $value = HeliosRow::string($row, 'value');
                $group = HeliosRow::string($row, 'p_id');
                if ($value === null || $group === null) {
                    continue;
                }

                $index[(HeliosRow::int($row, 'l_id') ?? 0)][] = ['group' => $group, 'dictionary' => $row['idciselnikuvlast'] ?? null, 'value' => $value];
            }
        }

        return $index;
    }

    /**
     * @param list<int> $masterIds
     *
     * @return array<int, list<array{taxon: string, position: int, main: bool}>>
     */
    private function loadTaxonLinks(array $masterIds): array
    {
        if ($masterIds === []) {
            return [];
        }

        $this->rateLimiter->consume();
        $rows = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
            'SELECT idkmen, idsortiment, dvatrporadieshop, dvatrpred
             FROM hvw_SapertaTabSapKatKmenZbozi
             WHERE idkmen IN (:ids) AND idsortiment IS NOT NULL
             ORDER BY idkmen ASC, id ASC',
            ['ids' => $masterIds],
            ['ids' => ArrayParameterType::INTEGER],
        ));

        $index = [];
        foreach ($rows as $row) {
            $index[(HeliosRow::int($row, 'idkmen') ?? 0)][] = [
                'taxon' => (string) (HeliosRow::int($row, 'idsortiment') ?? 0),
                'position' => HeliosRow::int($row, 'dvatrporadieshop') ?? 0,
                'main' => HeliosRow::bool($row, 'dvatrpred'),
            ];
        }

        return $index;
    }

    /** @return array<array-key, string> group id => Helios type (C|N) */
    private function loadGroupTypes(): array
    {
        $this->rateLimiter->consume();
        $rows = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
            'SELECT id, typ FROM hvw_924vlastnosti',
        ));

        $types = [];
        foreach ($rows as $row) {
            $types[HeliosRow::string($row, 'id') ?? ''] = HeliosRow::string($row, 'typ') ?? 'C';
        }

        return $types;
    }

    private static function modifiedAtSql(): string
    {
        return 'CASE
            WHEN variant_change.modified_at IS NOT NULL
                 AND variant_change.modified_at > COALESCE(
                     CASE WHEN DatumPosledniZmenaEshop > poslednizmena OR poslednizmena IS NULL THEN DatumPosledniZmenaEshop ELSE poslednizmena END,
                     \'1970-01-01\')
            THEN variant_change.modified_at
            ELSE CASE WHEN DatumPosledniZmenaEshop > poslednizmena OR poslednizmena IS NULL THEN DatumPosledniZmenaEshop ELSE poslednizmena END
        END';
    }
}
