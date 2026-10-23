<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\SalesRepresentative;

use App\Erp\Adapter\Helios\Infrastructure\HeliosConnectionFactory;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRateLimiter;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRetryTrait;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRow;
use App\Erp\Port\Customer\Dto\SalesRepresentativeSnapshot;
use App\Erp\Port\Customer\SalesRepresentativeSyncInterface;

final readonly class HeliosSalesRepresentativeSyncAdapter implements SalesRepresentativeSyncInterface
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
            'SELECT id, cislo, jmeno, prijmeni, email, mobil FROM hvw_SapertaTabSapCisZam
             WHERE id IN (SELECT DISTINCT odpos FROM hvw_SapertaTabSapCisOrg WHERE fakturacni = 1 AND odpos IS NOT NULL)
             ORDER BY id ASC',
        ));

        foreach ($rows as $row) {
            $id = HeliosRow::string($row, 'id');
            if ($id === null) {
                continue;
            }

            $email = HeliosRow::string($row, 'email');

            yield new SalesRepresentativeSnapshot(
                externalIdentifier: $id,
                code: HeliosRow::string($row, 'cislo'),
                firstName: HeliosRow::string($row, 'jmeno'),
                lastName: HeliosRow::string($row, 'prijmeni'),
                email: $email === null ? null : mb_strtolower($email),
                phoneNumber: HeliosRow::string($row, 'mobil'),
            );
        }
    }
}
