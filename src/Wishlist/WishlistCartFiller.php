<?php

declare(strict_types=1);

namespace App\Wishlist;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\SyliusCartEvents;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class WishlistCartFiller
{
    /**
     * @param FactoryInterface<OrderItemInterface> $orderItemFactory
     */
    public function __construct(
        private CartContextInterface $cartContext,
        #[Autowire(service: 'sylius.factory.order_item')]
        private FactoryInterface $orderItemFactory,
        private OrderItemQuantityModifierInterface $quantityModifier,
        private AddToCartCommandFactoryInterface $addToCartCommandFactory,
        private EventDispatcherInterface $eventDispatcher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public static function normalizeQuantity(ProductVariant $variant, int $quantity): int
    {
        if ($quantity <= 0) {
            return 0;
        }

        $product = $variant->getProduct();
        $step = $product instanceof Product ? max(1, $product->getQuantityStep()) : 1;
        $minimum = $product instanceof Product ? max($product->getMinimumQuantity(), $step) : 1;
        $quantity = max($quantity, $minimum);

        return (int) (ceil($quantity / $step) * $step);
    }

    /**
     * @param array<int, array{variant: ProductVariant, quantity: int}> $lines
     */
    public function addToCart(array $lines): int
    {
        $cart = $this->cartContext->getCart();
        if (!$cart instanceof OrderInterface) {
            return 0;
        }

        $added = 0;
        foreach ($lines as $line) {
            $quantity = self::normalizeQuantity($line['variant'], $line['quantity']);
            if ($quantity === 0) {
                continue;
            }

            $item = $this->orderItemFactory->createNew();
            $item->setVariant($line['variant']);
            $this->quantityModifier->modify($item, $quantity);

            $this->eventDispatcher->dispatch(
                new GenericEvent($this->addToCartCommandFactory->createWithCartAndCartItem($cart, $item)),
                SyliusCartEvents::CART_ITEM_ADD,
            );
            ++$added;
        }

        if ($added > 0) {
            $this->entityManager->persist($cart);
            $this->entityManager->flush();
        }

        return $added;
    }
}
