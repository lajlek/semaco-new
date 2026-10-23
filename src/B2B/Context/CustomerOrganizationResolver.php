<?php

declare(strict_types=1);

namespace App\B2B\Context;

use App\B2B\Entity\Organization;
use App\Entity\Customer\Customer;
use Sylius\Component\Customer\Context\CustomerContextInterface;

final readonly class CustomerOrganizationResolver implements ActiveOrganizationResolverInterface
{
    public function __construct(private CustomerContextInterface $customerContext)
    {
    }

    public function resolve(): ?Organization
    {
        $customer = $this->customerContext->getCustomer();

        return $customer instanceof Customer ? $customer->getOrganization() : null;
    }
}
