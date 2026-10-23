<?php

declare(strict_types=1);

namespace App\Tests\Checkout;

use App\B2B\Context\ActiveOrganizationResolverInterface;
use App\B2B\Entity\Organization;
use App\Checkout\Address\BillingAddressPrefiller;
use App\Entity\Addressing\Address;
use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use PHPUnit\Framework\TestCase;

final class BillingAddressPrefillerTest extends TestCase
{
    public function testFillsEmptyBillingAddressFromOrganizationAndCustomer(): void
    {
        $order = new Order();
        $customer = new Customer();
        $customer->setFirstName('Jan');
        $customer->setLastName('Novák');
        $customer->setPhoneNumber('+420 600 000 000');
        $order->setCustomer($customer);

        $this->prefiller($this->organization())->prefill($order);

        $billing = $order->getBillingAddress();
        self::assertInstanceOf(Address::class, $billing);
        self::assertSame('Firma s.r.o.', $billing->getCompany());
        self::assertSame('Jan', $billing->getFirstName());
        self::assertSame('+420 600 000 000', $billing->getPhoneNumber());
        self::assertSame('Hlavní 1', $billing->getStreet());
        self::assertSame('CZ', $billing->getCountryCode());
        self::assertSame('12345678', $billing->getIdentificationNumber());
        self::assertSame('CZ12345678', $billing->getVatNumber());
    }

    public function testKeepsFilledFields(): void
    {
        $order = new Order();
        $billing = new Address();
        $billing->setStreet('Jiná 2');
        $order->setBillingAddress($billing);

        $this->prefiller($this->organization())->prefill($order);

        self::assertSame('Jiná 2', $billing->getStreet());
        self::assertSame('Praha', $billing->getCity());
    }

    public function testWithoutOrganizationNothingChanges(): void
    {
        $order = new Order();

        $this->prefiller(null)->prefill($order);

        self::assertNull($order->getBillingAddress());
    }

    private function organization(): Organization
    {
        $address = new Address();
        $address->setCompany('Firma s.r.o.');
        $address->setFirstName('Recepce');
        $address->setLastName('Firmy');
        $address->setStreet('Hlavní 1');
        $address->setCity('Praha');
        $address->setPostcode('11000');
        $address->setCountryCode('CZ');

        $organization = new Organization('1', 'Firma s.r.o.');
        $organization->setIco('12345678');
        $organization->setDic('CZ12345678');
        $organization->setDefaultAddress($address);

        return $organization;
    }

    private function prefiller(?Organization $organization): BillingAddressPrefiller
    {
        $resolver = new class($organization) implements ActiveOrganizationResolverInterface {
            public function __construct(private readonly ?Organization $organization)
            {
            }

            public function resolve(): ?Organization
            {
                return $this->organization;
            }
        };

        return new BillingAddressPrefiller($resolver);
    }
}
