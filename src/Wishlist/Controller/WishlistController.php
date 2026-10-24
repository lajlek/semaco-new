<?php

declare(strict_types=1);

namespace App\Wishlist\Controller;

use App\Entity\Product\ProductVariant;
use App\Wishlist\WishlistCartFiller;
use App\Wishlist\WishlistContext;
use App\Wishlist\WishlistManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WishlistController extends AbstractController
{
    public const CSRF_TOKEN_ID = 'wishlist';

    public function __construct(
        private readonly WishlistContext $wishlistContext,
        private readonly WishlistManager $wishlistManager,
        private readonly WishlistCartFiller $cartFiller,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/wishlist', name: 'app_shop_wishlist_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $response = $this->render('shop/wishlist/index.html.twig', [
            'wishlist' => $this->wishlistContext->resolve($request),
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    #[Route('/wishlist/add/variant/{variantId}', name: 'app_shop_wishlist_add_variant', requirements: ['variantId' => '\d+'], methods: ['GET'])]
    public function confirmAdd(int $variantId): Response
    {
        $response = $this->render('shop/wishlist/confirm.html.twig', ['variant' => $this->findVariant($variantId)]);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    #[Route('/wishlist/add/variant/{variantId}', name: 'app_shop_wishlist_add_variant_submit', requirements: ['variantId' => '\d+'], methods: ['POST'])]
    public function add(Request $request, int $variantId): Response
    {
        return $this->toggle($request, $variantId, true);
    }

    #[Route('/wishlist/remove/variant/{variantId}', name: 'app_shop_wishlist_remove_variant', requirements: ['variantId' => '\d+'], methods: ['POST'])]
    public function remove(Request $request, int $variantId): Response
    {
        return $this->toggle($request, $variantId, false);
    }

    #[Route('/wishlist/add-to-cart', name: 'app_shop_wishlist_add_to_cart', methods: ['POST'])]
    public function addToCart(Request $request): Response
    {
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $wishlist = $this->wishlistContext->resolve($request);
        $quantities = $request->request->all('quantity');
        $lines = [];
        foreach ($wishlist->getItems() as $item) {
            $variantId = $item->getVariant()->getId();
            $quantity = $quantities[$variantId] ?? 0;
            if (is_numeric($quantity) && (int) $quantity > 0) {
                $lines[] = ['variant' => $item->getVariant(), 'quantity' => (int) $quantity];
            }
        }

        if ($lines === []) {
            $this->addFlash('error', 'app.wishlist.nothing_to_add');

            return $this->redirectToRoute('app_shop_wishlist_index');
        }

        $added = $this->cartFiller->addToCart($lines);
        if ($added < count($lines)) {
            $this->addFlash('warning', 'app.wishlist.price_on_request_skipped');
        }
        if ($added === 0) {
            return $this->redirectToRoute('app_shop_wishlist_index');
        }

        $this->addFlash('success', 'app.wishlist.added_to_cart');

        return $this->redirectToRoute('sylius_shop_cart_summary');
    }

    private function toggle(Request $request, int $variantId, bool $add): Response
    {
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $variant = $this->findVariant($variantId);
        $wishlist = $this->wishlistContext->resolve($request);
        $changed = $add ? $this->wishlistManager->add($wishlist, $variant) : $this->wishlistManager->remove($wishlist, $variant);

        if ($request->getPreferredFormat() === 'json' || $request->isXmlHttpRequest()) {
            $response = new JsonResponse([
                'inWishlist' => $wishlist->hasVariant($variant),
                'count' => $wishlist->count(),
            ]);
        } else {
            if ($changed) {
                $this->addFlash('success', $add ? 'app.wishlist.added' : 'app.wishlist.removed');
            }
            $response = $this->redirectToRoute('app_shop_wishlist_index');
        }

        $this->wishlistContext->attachCookie($wishlist, $request, $response);

        return $response;
    }

    private function findVariant(int $variantId): ProductVariant
    {
        $variant = $this->entityManager->find(ProductVariant::class, $variantId);
        if (!$variant instanceof ProductVariant || !$variant->isEnabled() || $variant->getProduct()?->isEnabled() !== true) {
            throw $this->createNotFoundException();
        }

        return $variant;
    }
}
