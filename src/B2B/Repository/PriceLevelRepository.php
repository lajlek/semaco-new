<?php

declare(strict_types=1);

namespace App\B2B\Repository;

use App\B2B\Entity\PriceLevel;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

final class PriceLevelRepository extends EntityRepository implements PriceLevelRepositoryInterface
{
    public function findOneByCode(string $code): ?PriceLevel
    {
        /** @var PriceLevel|null $result */
        $result = $this->findOneBy(['code' => $code]);

        return $result;
    }

    public function findOneByExternalIdentifier(string $externalIdentifier): ?PriceLevel
    {
        /** @var PriceLevel|null $result */
        $result = $this->findOneBy(['externalIdentifier' => $externalIdentifier]);

        return $result;
    }
}
