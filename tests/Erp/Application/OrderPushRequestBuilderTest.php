<?php

declare(strict_types=1);

namespace App\Tests\Erp\Application;

use App\B2B\Entity\Organization;
use App\Entity\Addressing\Address;
use App\Entity\Customer\Customer;
use App\Entity\Order\Adjustment;
use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Order\OrderItemUnit;
use App\Entity\Payment\Payment;
use App\Entity\Payment\PaymentMethod;
use App\Entity\Product\ProductVariant;
use App\Entity\Shipping\Shipment;
use App\Entity\Shipping\ShippingMethod;
use App\Entity\User\ShopUser;
use App\Erp\Application\Order\OrderPushRequestBuilder;
use App\Erp\Port\Order\Dto\OrderLine;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdjustmentInterface;

final class OrderPushRequestBuilderTest extends TestCase
{
    public function testOrganizationOrderMapsLikeOldExport(): void
    {
        $organization = new Organization('6633', 'Firma');
        $deliveryPoint = $this->address('Provozovna', 'Dlouhá 5', 'Brno', '60200');
        $deliveryPoint->setExternalIdentifier('7001');
        $organization->addAddress($deliveryPoint);

        $customer = new Customer();
        $customer->setFirstName('Jana');
        $customer->setLastName('Nováková');
        $customer->setExternalIdentifier('555');
        $customer->setOrganization($organization);
        $customer->setUser(new ShopUser());

        $order = $this->order($customer, $this->address('Provozovna', 'Dlouhá  5', 'Brno', '60200'));
        $order->setNotes('Prosím rychle');

        $request = $this->builder()->build($order);

        self::assertSame('000123', $request->orderNumber);
        self::assertSame('1', $request->shopExternalIdentifier);
        self::assertSame('6633', $request->organizationExternalIdentifier);
        self::assertSame('7001', $request->deliveryAddressExternalIdentifier);
        self::assertSame('555', $request->contactPersonExternalIdentifier);
        self::assertSame('Platebním příkazem', $request->paymentMethod);
        self::assertSame('per GLS', $request->shippingMethod);
        self::assertSame("Jana Nováková\nProsím rychle", $request->notes);
        self::assertNull($request->guestBilling);
        self::assertCount(2, $request->lines);
        self::assertSame(OrderLine::TYPE_GOODS, $request->lines[0]->type);
        self::assertSame('62882', $request->lines[0]->variantExternalIdentifier);
        self::assertSame(2, $request->lines[0]->quantity);
        self::assertEqualsWithDelta(503.56, $request->lines[0]->total, 0.001);
        self::assertSame('A', $request->lines[0]->priceType);
        self::assertSame(OrderLine::TYPE_SHIPPING, $request->lines[1]->type);
        self::assertEqualsWithDelta(6.5, $request->lines[1]->total, 0.001);
        self::assertSame('N', $request->lines[1]->priceType);
    }

    public function testCustomerWithoutOrganizationIsSentAsGuest(): void
    {
        $customer = new Customer();
        $customer->setEmail('host@example.com');

        $request = $this->builder()->build($this->order($customer, $this->address(null, 'Krátká 1', 'Praha', '11000')));

        self::assertNull($request->organizationExternalIdentifier);
        self::assertSame('host@example.com', $request->guestBilling?->email);
        self::assertSame('Krátká 1', $request->guestShipping?->street);
        self::assertNull($request->notes);
    }

    private function order(Customer $customer, Address $shippingAddress): Order
    {
        $variant = new ProductVariant();
        $variant->setExternalIdentifier('62882');

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(25178);
        new OrderItemUnit($item);
        new OrderItemUnit($item);

        $order = new Order();
        $order->setNumber('000123');
        $order->setCurrencyCode('EUR');
        $order->setCustomer($customer);
        $order->addItem($item);
        $order->setShippingAddress($shippingAddress);
        $order->setBillingAddress($this->address(null, 'Krátká 1', 'Praha', '11000'));

        $shippingMethod = new ShippingMethod();
        $shippingMethod->setCode('3');
        $shipment = new Shipment();
        $shipment->setMethod($shippingMethod);
        $order->addShipment($shipment);
        $adjustment = new Adjustment();
        $adjustment->setType(AdjustmentInterface::SHIPPING_ADJUSTMENT);
        $adjustment->setAmount(650);
        $shipment->addAdjustment($adjustment);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCode('10');
        $payment = new Payment();
        $payment->setMethod($paymentMethod);
        $order->addPayment($payment);

        return $order;
    }

    private function address(?string $company, string $street, string $city, string $postcode): Address
    {
        $address = new Address();
        $address->setCompany($company);
        $address->setStreet($street);
        $address->setCity($city);
        $address->setPostcode($postcode);
        $address->setCountryCode('CZ');

        return $address;
    }

    private function builder(): OrderPushRequestBuilder
    {
        return new OrderPushRequestBuilder('1', 'A', 21.0, ['10' => 'Platebním příkazem', '12' => 'Zálohově'], ['3' => 'per GLS', 'free_shipping' => null]);
    }
}
