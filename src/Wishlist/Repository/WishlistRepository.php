<?php

declare(strict_types=1);

namespace App\Wishlist\Repository;

use App\Entity\User\ShopUser;
use App\Wishlist\Entity\Wishlist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Wishlist>
 */
final class WishlistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wishlist::class);
    }

    public function findOneByShopUser(ShopUser $shopUser): ?Wishlist
    {
        return $this->findOneBy(['shopUser' => $shopUser]);
    }

    public function findOneGuestByToken(string $token): ?Wishlist
    {
        return $this->findOneBy(['token' => $token, 'shopUser' => null]);
    }
}
