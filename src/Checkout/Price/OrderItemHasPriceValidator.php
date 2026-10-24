<?php

declare(strict_types=1);

namespace App\Checkout\Price;

use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class OrderItemHasPriceValidator extends ConstraintValidator
{
    public function __construct(
        private readonly VariantPriceChecker $priceChecker,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof OrderItemHasPrice) {
            throw new UnexpectedTypeException($constraint, OrderItemHasPrice::class);
        }

        $path = 'quantity';
        $order = null;
        if ($value instanceof AddToCartCommandInterface) {
            $order = $value->getCart();
            $value = $value->getCartItem();
            $path = '';
        }

        if (!$value instanceof OrderItemInterface || $value->getVariant() === null) {
            return;
        }

        $order ??= $value->getOrder();
        $channel = $order instanceof OrderInterface ? $order->getChannel() : null;
        if ($this->priceChecker->hasPrice($value->getVariant(), $channel instanceof ChannelInterface ? $channel : null)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->atPath($path)
            ->setParameter('{{ product }}', (string) $value->getVariant()->getProduct()?->getName())
            ->addViolation();
    }
}
