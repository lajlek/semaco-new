<?php

declare(strict_types=1);

namespace App\Checkout\Price;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class OrderItemHasPrice extends Constraint
{
    public string $message = 'app.order_item.price_on_request';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
