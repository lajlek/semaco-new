<?php

declare(strict_types=1);

namespace App\Checkout\Shipping;

use App\B2B\Context\ActiveOrganizationResolverInterface;
use App\B2B\Entity\Organization;
use App\Entity\Customer\Customer;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Shipping\Checker\Eligibility\ShippingMethodEligibilityCheckerInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Model\ShippingSubjectInterface;

final readonly class FreeShippingEligibilityChecker implements ShippingMethodEligibilityCheckerInterface
{
    public function __construct(
        private ActiveOrganizationResolverInterface $activeOrganizationResolver,
        private string $freeShippingMethodCode,
    ) {
    }

    public function isEligible(ShippingSubjectInterface $shippingSubject, ShippingMethodInterface $shippingMethod): bool
    {
        $isFreeShippingMethod = $shippingMethod->getCode() === $this->freeShippingMethodCode;

        return $this->hasFreeShipping($shippingSubject) ? $isFreeShippingMethod : !$isFreeShippingMethod;
    }

    private function hasFreeShipping(ShippingSubjectInterface $shippingSubject): bool
    {
        $organization = null;
        if ($shippingSubject instanceof ShipmentInterface) {
            $order = $shippingSubject->getOrder();
            $customer = $order instanceof OrderInterface ? $order->getCustomer() : null;
            $organization = $customer instanceof Customer ? $customer->getOrganization() : null;
        }

        $organization ??= $this->activeOrganizationResolver->resolve();

        return $organization instanceof Organization && $organization->hasFreeShipping();
    }
}
