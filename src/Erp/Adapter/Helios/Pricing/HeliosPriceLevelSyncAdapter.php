<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Pricing;

use App\Erp\Adapter\Helios\Infrastructure\HeliosConnectionFactory;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRateLimiter;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRetryTrait;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRow;
use App\Erp\Port\Pricing\Dto\PriceLevelSnapshot;
use App\Erp\Port\Pricing\PriceLevelSyncInterface;

final readonly class HeliosPriceLevelSyncAdapter implements PriceLevelSyncInterface
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
            'SELECT cenovauroven, MIN(nazev) AS name
             FROM hvw_SapertaTabSapCenoveUrovne
             WHERE cenovauroven IS NOT NULL AND nazev IS NOT NULL
             GROUP BY cenovauroven
             ORDER BY cenovauroven ASC',
        ));

        foreach ($rows as $row) {
            $id = HeliosRow::string($row, 'cenovauroven');
            $name = HeliosRow::string($row, 'name');
            if ($id !== null && $name !== null) {
                yield new PriceLevelSnapshot($id, $name);
            }
        }
    }
}
