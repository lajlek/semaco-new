<?php

declare(strict_types=1);

namespace App\Checkout\Quantity;

use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class OrderItemQuantityRulesValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof OrderItemQuantityRules) {
            throw new UnexpectedTypeException($constraint, OrderItemQuantityRules::class);
        }

        $path = 'quantity';
        if ($value instanceof AddToCartCommandInterface) {
            $value = $value->getCartItem();
            $path = 'cartItem.quantity';
        }

        if (!$value instanceof OrderItemInterface) {
            return;
        }

        $rules = QuantityRules::forVariant($value->getVariant());
        if ($rules->allows($value->getQuantity())) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->atPath($path)
            ->setParameter('{{ product }}', (string) $value->getVariant()?->getProduct()?->getName())
            ->setParameter('{{ step }}', (string) $rules->step)
            ->setParameter('{{ minimum }}', (string) $rules->minimum)
            ->addViolation();
    }
}
