<?php

declare(strict_types=1);

namespace App\Cms\Repository;

use App\Cms\Entity\CmsPage;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

final class CmsPageRepository extends EntityRepository implements CmsPageRepositoryInterface
{
    public function findEnabledBySlug(string $slug): ?CmsPage
    {
        $page = $this->findOneBy(['slug' => $slug, 'enabled' => true]);

        return $page instanceof CmsPage ? $page : null;
    }

    public function findEnabledOrdered(): array
    {
        /** @var list<CmsPage> $pages */
        $pages = $this->findBy(['enabled' => true], ['position' => 'ASC', 'title' => 'ASC']);

        return $pages;
    }
}
