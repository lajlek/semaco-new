<?php

declare(strict_types=1);

namespace App\B2B\Order;

use App\B2B\Context\ActiveOrganizationResolverInterface;
use App\B2B\SalesRepresentative\SalesRepresentativeContext;
use App\Entity\Order\Order;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('sylius.order_processor', ['priority' => 70])]
final readonly class AssignOrganizationOrderProcessor implements OrderProcessorInterface
{
    public function __construct(
        private ActiveOrganizationResolverInterface $organizationResolver,
        private SalesRepresentativeContext $salesRepresentativeContext,
    ) {
    }

    public function process(OrderInterface $order): void
    {
        if (!$order instanceof Order || $order->getState() !== OrderInterface::STATE_CART) {
            return;
        }

        $order->setOrganization($this->organizationResolver->resolve());
        $order->setSalesRepresentative($this->salesRepresentativeContext->getRepresentative());
        $order->setContactCustomer($this->salesRepresentativeContext->getActiveCustomer());
    }
}
