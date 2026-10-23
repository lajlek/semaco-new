<?php

declare(strict_types=1);

namespace App\Wishlist;

use App\Entity\Product\ProductVariant;
use App\Entity\User\ShopUser;
use App\Wishlist\Entity\Wishlist;
use App\Wishlist\Repository\WishlistRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class WishlistManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WishlistRepository $wishlistRepository,
    ) {
    }

    public function add(Wishlist $wishlist, ProductVariant $variant): bool
    {
        if (!$wishlist->addVariant($variant)) {
            return false;
        }

        if ($wishlist->getId() === null) {
            $this->entityManager->persist($wishlist);
        }
        $this->entityManager->flush();

        return true;
    }

    public function remove(Wishlist $wishlist, ProductVariant $variant): bool
    {
        if (!$wishlist->removeVariant($variant)) {
            return false;
        }

        $this->entityManager->flush();

        return true;
    }

    public function mergeGuestWishlist(string $token, ShopUser $shopUser): int
    {
        $guest = $this->wishlistRepository->findOneGuestByToken($token);
        if ($guest === null) {
            return 0;
        }

        $target = $this->wishlistRepository->findOneByShopUser($shopUser);
        if ($target === null) {
            $guest->setShopUser($shopUser);
            $this->entityManager->flush();

            return $guest->count();
        }

        $merged = 0;
        foreach ($guest->getItems() as $item) {
            if ($target->addVariant($item->getVariant())) {
                ++$merged;
            }
        }

        $this->entityManager->remove($guest);
        $this->entityManager->flush();

        return $merged;
    }
}
