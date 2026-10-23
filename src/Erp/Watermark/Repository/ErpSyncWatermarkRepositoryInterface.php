<?php

declare(strict_types=1);

namespace App\Erp\Watermark\Repository;

use App\Erp\Watermark\Entity\ErpSyncWatermark;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

interface ErpSyncWatermarkRepositoryInterface extends RepositoryInterface
{
    public function findOneByDomain(string $domain): ?ErpSyncWatermark;

    public function findOrCreateByDomain(string $domain): ErpSyncWatermark;
}
