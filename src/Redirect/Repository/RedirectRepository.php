<?php

declare(strict_types=1);

namespace App\Redirect\Repository;

use App\Redirect\Entity\Redirect;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

final class RedirectRepository extends EntityRepository implements RedirectRepositoryInterface
{
    public function findEnabledBySource(string $source): ?Redirect
    {
        $redirect = $this->findOneBy(['source' => $source, 'enabled' => true]);

        return $redirect instanceof Redirect ? $redirect : null;
    }
}
