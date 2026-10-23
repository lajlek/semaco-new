<?php

declare(strict_types=1);

namespace App\Checkout\Quantity;

use Sylius\Bundle\OrderBundle\Form\Type\CartItemType;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class CartItemMinimumQuantityExtension extends AbstractTypeExtension
{
    public function __construct(private readonly OrderItemQuantityModifierInterface $quantityModifier)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [CartItemType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $item = $event->getData();
            if (!$item instanceof OrderItemInterface || $item->getId() !== null || $item->getVariant() === null) {
                return;
            }

            $rules = QuantityRules::forVariant($item->getVariant());
            if ($item->getQuantity() < $rules->minimum) {
                $this->quantityModifier->modify($item, $rules->minimum);
            }
        });
    }
}
