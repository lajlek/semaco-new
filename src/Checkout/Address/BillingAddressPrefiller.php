<?php

declare(strict_types=1);

namespace App\Checkout\Address;

use App\B2B\Context\ActiveOrganizationResolverInterface;
use App\Entity\Addressing\Address;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

final readonly class BillingAddressPrefiller
{
    public function __construct(private ActiveOrganizationResolverInterface $organizationResolver)
    {
    }

    public function prefill(OrderInterface $order): void
    {
        $organization = $this->organizationResolver->resolve();
        $source = $organization?->getDefaultAddress();
        if ($organization === null || !$source instanceof AddressInterface) {
            return;
        }

        $billing = $order->getBillingAddress();
        if ($billing === null) {
            $billing = new Address();
            $order->setBillingAddress($billing);
        }

        $customer = $order->getCustomer();
        $values = [
            'company' => $source->getCompany(),
            'firstName' => $customer?->getFirstName() ?: $source->getFirstName(),
            'lastName' => $customer?->getLastName() ?: $source->getLastName(),
            'phoneNumber' => $customer?->getPhoneNumber() ?: $source->getPhoneNumber(),
            'street' => $source->getStreet(),
            'countryCode' => $source->getCountryCode(),
            'city' => $source->getCity(),
            'postcode' => $source->getPostcode(),
        ];
        if ($billing instanceof Address) {
            $values['identificationNumber'] = $source instanceof Address && $source->getIdentificationNumber() !== null ? $source->getIdentificationNumber() : $organization->getIco();
            $values['vatNumber'] = $source instanceof Address && $source->getVatNumber() !== null ? $source->getVatNumber() : $organization->getDic();
        }

        $accessor = PropertyAccess::createPropertyAccessor();
        foreach ($values as $property => $value) {
            $current = $accessor->getValue($billing, $property);
            if (($current === null || $current === '') && $value !== null && $value !== '') {
                $accessor->setValue($billing, $property, $value);
            }
        }
    }
}
