<?php

declare(strict_types=1);

namespace App\Cms\Repository;

use App\Cms\Entity\CmsPage;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

interface CmsPageRepositoryInterface extends RepositoryInterface
{
    public function findEnabledBySlug(string $slug): ?CmsPage;

    /** @return list<CmsPage> */
    public function findEnabledOrdered(): array;
}
