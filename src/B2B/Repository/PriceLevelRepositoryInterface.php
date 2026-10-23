<?php

declare(strict_types=1);

namespace App\B2B\Repository;

use App\B2B\Entity\PriceLevel;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

interface PriceLevelRepositoryInterface extends RepositoryInterface
{
    public function findOneByCode(string $code): ?PriceLevel;

    public function findOneByExternalIdentifier(string $externalIdentifier): ?PriceLevel;
}
