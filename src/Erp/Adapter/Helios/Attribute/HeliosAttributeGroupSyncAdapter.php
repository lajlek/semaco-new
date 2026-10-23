<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Attribute;

use App\Erp\Adapter\Helios\Infrastructure\HeliosConnectionFactory;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRateLimiter;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRetryTrait;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRow;
use App\Erp\Port\Catalog\AttributeGroupSyncInterface;
use App\Erp\Port\Catalog\Dto\AttributeGroupSnapshot;
use App\Erp\Port\Catalog\Dto\AttributeValueSnapshot;

final readonly class HeliosAttributeGroupSyncAdapter implements AttributeGroupSyncInterface
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
        $groups = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
            'SELECT id, nazev, typ, poradi, typfiltru FROM hvw_924vlastnosti ORDER BY poradi ASC, id ASC',
        ));

        $this->rateLimiter->consume();
        $valueRows = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
            'SELECT p_id, idciselnikuvlast, DVAtr1856042231 AS value, MIN(PoradiHodnot) AS value_position
             FROM hvw_SapertaTabSapVlastnosti
             WHERE DVAtr1856042231 IS NOT NULL AND DVAtr1856042231 <> \'\'
             GROUP BY p_id, idciselnikuvlast, DVAtr1856042231
             ORDER BY p_id ASC, MIN(PoradiHodnot) ASC, DVAtr1856042231 ASC',
        ));

        $valuesByGroup = [];
        foreach ($valueRows as $row) {
            $groupId = HeliosRow::string($row, 'p_id');
            $value = HeliosRow::string($row, 'value');
            if ($groupId === null || $value === null) {
                continue;
            }

            $key = HeliosAttributeValueKey::of($row['idciselnikuvlast'] ?? null, $value);
            $valuesByGroup[$groupId][$key] ??= new AttributeValueSnapshot($key, $value);
        }

        foreach ($groups as $row) {
            $id = HeliosRow::string($row, 'id');
            if ($id === null) {
                continue;
            }

            yield new AttributeGroupSnapshot(
                externalIdentifier: $id,
                name: HeliosRow::string($row, 'nazev') ?? $id,
                type: HeliosRow::string($row, 'typ') === 'N' ? AttributeGroupSnapshot::TYPE_INTEGER : AttributeGroupSnapshot::TYPE_SELECT,
                position: HeliosRow::int($row, 'poradi') ?? 0,
                filterType: HeliosRow::int($row, 'typfiltru'),
                values: array_values($valuesByGroup[$id] ?? []),
            );
        }
    }
}
