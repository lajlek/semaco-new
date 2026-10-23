<?php

declare(strict_types=1);

namespace App\Tests\Checkout;

use App\B2B\Context\ActiveOrganizationResolverInterface;
use App\B2B\Entity\Organization;
use App\Checkout\Shipping\FreeShippingEligibilityChecker;
use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use App\Entity\Shipping\Shipment;
use App\Entity\Shipping\ShippingMethod;
use PHPUnit\Framework\TestCase;

final class FreeShippingEligibilityCheckerTest extends TestCase
{
    public function testOrganizationWithFreeShippingSeesOnlyFreeShipping(): void
    {
        $shipment = $this->shipment(true);

        self::assertTrue($this->checker()->isEligible($shipment, $this->method('free_shipping')));
        self::assertFalse($this->checker()->isEligible($shipment, $this->method('3')));
    }

    public function testOthersNeverSeeFreeShipping(): void
    {
        foreach ([$this->shipment(false), $this->shipment(null)] as $shipment) {
            self::assertFalse($this->checker()->isEligible($shipment, $this->method('free_shipping')));
            self::assertTrue($this->checker()->isEligible($shipment, $this->method('3')));
        }
    }

    private function checker(): FreeShippingEligibilityChecker
    {
        $resolver = new class() implements ActiveOrganizationResolverInterface {
            public function resolve(): ?Organization
            {
                return null;
            }
        };

        return new FreeShippingEligibilityChecker($resolver, 'free_shipping');
    }

    private function shipment(?bool $freeShipping): Shipment
    {
        $customer = new Customer();
        if ($freeShipping !== null) {
            $organization = new Organization('1', 'Firma');
            $organization->setFreeShipping($freeShipping);
            $customer->setOrganization($organization);
        }

        $order = new Order();
        $order->setCustomer($customer);
        $shipment = new Shipment();
        $order->addShipment($shipment);

        return $shipment;
    }

    private function method(string $code): ShippingMethod
    {
        $method = new ShippingMethod();
        $method->setCode($code);

        return $method;
    }
}
