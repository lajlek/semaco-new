<?php

declare(strict_types=1);

namespace App\B2B\Twig;

use App\B2B\SalesRepresentative\SalesRepresentativeContext;
use App\Entity\Customer\Customer;
use Twig\Attribute\AsTwigFunction;

final readonly class SalesRepresentativeExtension
{
    public function __construct(private SalesRepresentativeContext $context)
    {
    }

    /** @return array{organizations: list<\App\B2B\Entity\Organization>, activeOrganization: ?\App\B2B\Entity\Organization, customers: list<Customer>, activeCustomer: ?Customer}|null */
    #[AsTwigFunction('semaco_sales_representative_switcher')]
    public function switcher(): ?array
    {
        if ($this->context->getRepresentative() === null) {
            return null;
        }

        $activeOrganization = $this->context->getActiveOrganization();
        $customers = $activeOrganization === null ? [] : array_values($activeOrganization->getCustomers()->toArray());

        return [
            'organizations' => $this->context->getOrganizations(),
            'activeOrganization' => $activeOrganization,
            'customers' => $customers,
            'activeCustomer' => $this->context->getActiveCustomer(),
        ];
    }
}
