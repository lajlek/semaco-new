<?php

declare(strict_types=1);

namespace App\Checkout\Quantity;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class OrderItemQuantityRules extends Constraint
{
    public string $message = 'app.order_item.quantity_rules';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
