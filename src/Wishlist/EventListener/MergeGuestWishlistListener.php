<?php

declare(strict_types=1);

namespace App\Wishlist\EventListener;

use App\Entity\User\ShopUser;
use App\Wishlist\WishlistContext;
use App\Wishlist\WishlistManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
final readonly class MergeGuestWishlistListener
{
    public function __construct(
        private WishlistManager $wishlistManager,
        private WishlistContext $wishlistContext,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $token = $event->getRequest()->cookies->get(WishlistContext::COOKIE_NAME);
        if (!$user instanceof ShopUser || !is_string($token) || $token === '') {
            return;
        }

        $this->wishlistManager->mergeGuestWishlist($token, $user);
        $this->wishlistContext->reset();
        $event->getResponse()?->headers->clearCookie(WishlistContext::COOKIE_NAME);
    }
}
