<?php

declare(strict_types=1);

namespace App\Redirect\Repository;

use App\Redirect\Entity\Redirect;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

interface RedirectRepositoryInterface extends RepositoryInterface
{
    public function findEnabledBySource(string $source): ?Redirect;
}
