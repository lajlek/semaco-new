<?php

declare(strict_types=1);

namespace App\Wishlist;

use App\Entity\User\ShopUser;
use App\Wishlist\Entity\Wishlist;
use App\Wishlist\Repository\WishlistRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ResetInterface;

final class WishlistContext implements ResetInterface
{
    public const COOKIE_NAME = 'semaco_wishlist';

    private ?Wishlist $resolved = null;

    private ?Request $resolvedFor = null;

    public function __construct(
        private readonly Security $security,
        private readonly WishlistRepository $wishlistRepository,
    ) {
    }

    public function resolve(Request $request): Wishlist
    {
        if ($this->resolved !== null && $this->resolvedFor === $request) {
            return $this->resolved;
        }

        $this->resolvedFor = $request;

        return $this->resolved = $this->find($request);
    }

    public function attachCookie(Wishlist $wishlist, Request $request, Response $response): void
    {
        if ($wishlist->getShopUser() !== null || $wishlist->getId() === null) {
            return;
        }

        if ($request->cookies->get(self::COOKIE_NAME) === $wishlist->getToken()) {
            return;
        }

        $response->headers->setCookie(Cookie::create(self::COOKIE_NAME, $wishlist->getToken(), new \DateTimeImmutable('+1 year'))
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX));
    }

    public function reset(): void
    {
        $this->resolved = null;
        $this->resolvedFor = null;
    }

    private function find(Request $request): Wishlist
    {
        $user = $this->security->getUser();
        if ($user instanceof ShopUser) {
            $wishlist = $this->wishlistRepository->findOneByShopUser($user);
            if ($wishlist === null) {
                $wishlist = new Wishlist();
                $wishlist->setShopUser($user);
            }

            return $wishlist;
        }

        $token = $request->cookies->get(self::COOKIE_NAME);
        if (is_string($token) && $token !== '') {
            $wishlist = $this->wishlistRepository->findOneGuestByToken($token);
            if ($wishlist !== null) {
                return $wishlist;
            }
        }

        return new Wishlist();
    }
}
