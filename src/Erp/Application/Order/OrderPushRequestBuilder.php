<?php

declare(strict_types=1);

namespace App\Erp\Application\Order;

use App\B2B\Entity\Organization;
use App\Entity\Addressing\Address;
use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use App\Entity\Product\ProductVariant;
use App\Erp\Port\Order\Dto\OrderLine;
use App\Erp\Port\Order\Dto\OrderParty;
use App\Erp\Port\Order\Dto\OrderPushRequest;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class OrderPushRequestBuilder
{
    /**
     * @param array<array-key, string> $paymentMethods shop payment method code => Helios forma úhrady
     * @param array<array-key, string|null> $shippingMethods shop shipping method code => Helios forma dopravy
     */
    public function __construct(
        #[Autowire('%app.erp.helios_shop_id%')]
        private string $shopExternalIdentifier,
        #[Autowire('%app.erp.order_price_type%')]
        private string $priceType,
        #[Autowire('%app.erp.order_vat_rate%')]
        private float $vatRate,
        #[Autowire('%app.erp.payment_methods%')]
        private array $paymentMethods,
        #[Autowire('%app.erp.shipping_methods%')]
        private array $shippingMethods,
    ) {
    }

    public function build(Order $order): OrderPushRequest
    {
        $customer = $order->getCustomer();
        $customer = $customer instanceof Customer ? $customer : null;
        $organization = $order->getOrganization() ?? $customer?->getOrganization();
        $contact = $order->getContactCustomer() ?? $customer;

        $lines = [];
        foreach ($order->getItems() as $item) {
            \assert($item instanceof OrderItemInterface);
            $variant = $item->getVariant();
            $lines[] = new OrderLine(
                type: OrderLine::TYPE_GOODS,
                variantExternalIdentifier: $variant instanceof ProductVariant ? $variant->getExternalIdentifier() : null,
                quantity: $item->getQuantity(),
                total: $item->getTotal() / 100,
                priceType: $this->priceType,
                vatRate: $this->vatRate,
            );
        }

        foreach ($order->getShipments() as $shipment) {
            $lines[] = new OrderLine(
                type: OrderLine::TYPE_SHIPPING,
                variantExternalIdentifier: null,
                quantity: 1,
                total: $shipment->getAdjustmentsTotal() / 100,
                priceType: 'N',
                vatRate: $this->vatRate,
            );
        }

        $paymentCode = $order->getLastPayment()?->getMethod()?->getCode();
        $shippingCode = $order->getShipments()->first() ?: null;
        $shippingCode = $shippingCode?->getMethod()?->getCode();

        $isGuest = $organization === null;

        return new OrderPushRequest(
            orderNumber: (string) $order->getNumber(),
            completedAt: \DateTimeImmutable::createFromInterface($order->getCheckoutCompletedAt() ?? new \DateTimeImmutable()),
            shopExternalIdentifier: $this->shopExternalIdentifier,
            currencyCode: (string) $order->getCurrencyCode(),
            total: $order->getTotal() / 100,
            notes: $this->notes($order, $customer),
            organizationExternalIdentifier: $organization?->getExternalIdentifier(),
            deliveryAddressExternalIdentifier: $organization === null ? null : $this->deliveryAddressIdentifier($organization, $order->getShippingAddress()),
            contactPersonExternalIdentifier: $contact?->getExternalIdentifier(),
            paymentMethod: $paymentCode === null ? null : ($this->paymentMethods[$paymentCode] ?? null),
            shippingMethod: $shippingCode === null ? null : ($this->shippingMethods[$shippingCode] ?? null),
            guestBilling: $isGuest ? $this->party($order->getBillingAddress(), $customer) : null,
            guestShipping: $isGuest ? $this->party($order->getShippingAddress(), $customer) : null,
            lines: $lines,
        );
    }

    private function notes(Order $order, ?Customer $customer): ?string
    {
        $notes = $order->getNotes();
        if ($customer === null || $customer->getUser() === null) {
            return $notes;
        }

        return trim($customer->getFullName() . "\n" . (string) $notes) ?: null;
    }

    private function deliveryAddressIdentifier(Organization $organization, ?AddressInterface $shippingAddress): ?string
    {
        if ($shippingAddress === null) {
            return null;
        }

        if ($shippingAddress instanceof Address && $shippingAddress->getExternalIdentifier() !== null && ctype_digit($shippingAddress->getExternalIdentifier())) {
            return $shippingAddress->getExternalIdentifier();
        }

        foreach ($organization->getAddresses() as $candidate) {
            if (
                $candidate->getExternalIdentifier() !== null &&
                self::same($candidate->getStreet(), $shippingAddress->getStreet()) &&
                self::same($candidate->getCity(), $shippingAddress->getCity()) &&
                self::same($candidate->getPostcode(), $shippingAddress->getPostcode()) &&
                self::same($candidate->getCompany(), $shippingAddress->getCompany())
            ) {
                return $candidate->getExternalIdentifier();
            }
        }

        return null;
    }

    private function party(?AddressInterface $address, ?Customer $customer): ?OrderParty
    {
        if ($address === null) {
            return null;
        }

        return new OrderParty(
            company: $address->getCompany(),
            firstName: $address->getFirstName(),
            lastName: $address->getLastName(),
            street: $address->getStreet(),
            city: $address->getCity(),
            postcode: $address->getPostcode(),
            countryCode: $address->getCountryCode(),
            phone: $address->getPhoneNumber() ?? $customer?->getPhoneNumber(),
            email: $customer?->getEmail(),
            ico: $address instanceof Address ? $address->getIdentificationNumber() : null,
            dic: $address instanceof Address ? $address->getVatNumber() : null,
        );
    }

    private static function same(?string $a, ?string $b): bool
    {
        $normalize = static fn (?string $value): string => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $value)));

        return $normalize($a) === $normalize($b);
    }
}
