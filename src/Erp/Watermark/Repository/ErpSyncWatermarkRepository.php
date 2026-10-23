<?php

declare(strict_types=1);

namespace App\Erp\Watermark\Repository;

use App\Erp\Watermark\Entity\ErpSyncWatermark;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

final class ErpSyncWatermarkRepository extends EntityRepository implements ErpSyncWatermarkRepositoryInterface
{
    public function findOneByDomain(string $domain): ?ErpSyncWatermark
    {
        /** @var ErpSyncWatermark|null $result */
        $result = $this->findOneBy(['domain' => $domain]);

        return $result;
    }

    public function findOrCreateByDomain(string $domain): ErpSyncWatermark
    {
        $existing = $this->findOneByDomain($domain);
        if ($existing !== null) {
            return $existing;
        }

        $watermark = new ErpSyncWatermark($domain);
        $this->getEntityManager()->persist($watermark);

        return $watermark;
    }
}
