<?php

declare(strict_types=1);

namespace App\Wishlist\Twig;

use App\Entity\Product\ProductVariant;
use App\Wishlist\Entity\Wishlist;
use App\Wishlist\WishlistContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class WishlistExtension extends AbstractExtension
{
    public function __construct(
        private readonly WishlistContext $wishlistContext,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('semaco_wishlist', $this->getWishlist(...)),
            new TwigFunction('semaco_wishlist_has', $this->has(...)),
        ];
    }

    public function getWishlist(): ?Wishlist
    {
        $request = $this->requestStack->getMainRequest();

        return $request === null ? null : $this->wishlistContext->resolve($request);
    }

    public function has(ProductVariant $variant): bool
    {
        return $this->getWishlist()?->hasVariant($variant) ?? false;
    }
}
