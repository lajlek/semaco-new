<?php

declare(strict_types=1);

namespace App\Entity\Order;

use App\Checkout\Quantity\OrderItemQuantityRules;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\OrderItem as BaseOrderItem;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order_item')]
#[OrderItemQuantityRules(groups: ['sylius', 'sylius_checkout_complete'])]
class OrderItem extends BaseOrderItem
{
}
