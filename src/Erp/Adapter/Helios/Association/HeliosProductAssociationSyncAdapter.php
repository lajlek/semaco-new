<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Association;

use App\Erp\Adapter\Helios\Infrastructure\HeliosConnectionFactory;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRateLimiter;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRetryTrait;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRow;
use App\Erp\Port\Catalog\Dto\ProductAssociationSnapshot;
use App\Erp\Port\Catalog\ProductAssociationSyncInterface;

final readonly class HeliosProductAssociationSyncAdapter implements ProductAssociationSyncInterface
{
    use HeliosRetryTrait;

    private const VIEWS = [
        self::TYPE_ACCESSORY => 'hvw_SapertaTabSapPrislusenstvi',
        self::TYPE_RELATED => 'hvw_SapertaTabSapSouvisejici',
        self::TYPE_ALTERNATE => 'hvw_SapertaTabSapAlternativni',
    ];

    public function __construct(
        private HeliosConnectionFactory $connections,
        private HeliosRateLimiter $rateLimiter,
    ) {
    }

    public function pullAll(): iterable
    {
        foreach (self::VIEWS as $typeCode => $view) {
            $this->rateLimiter->consume();
            $rows = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
                sprintf('SELECT l_id, p_id FROM %s ORDER BY l_id ASC, id ASC', $view),
            ));

            $targets = [];
            foreach ($rows as $row) {
                $owner = HeliosRow::string($row, 'l_id');
                $target = HeliosRow::string($row, 'p_id');
                if ($owner !== null && $target !== null && !in_array($target, $targets[$owner] ?? [], true)) {
                    $targets[$owner][] = $target;
                }
            }

            foreach ($targets as $owner => $ownerTargets) {
                yield new ProductAssociationSnapshot((string) $owner, $typeCode, $ownerTargets);
            }
        }
    }
}
