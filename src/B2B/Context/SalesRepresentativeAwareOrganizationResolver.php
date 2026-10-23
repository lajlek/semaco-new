<?php

declare(strict_types=1);

namespace App\B2B\Context;

use App\B2B\Entity\Organization;
use App\B2B\SalesRepresentative\SalesRepresentativeContext;

final readonly class SalesRepresentativeAwareOrganizationResolver implements ActiveOrganizationResolverInterface
{
    public function __construct(
        private SalesRepresentativeContext $salesRepresentativeContext,
        private CustomerOrganizationResolver $customerOrganizationResolver,
    ) {
    }

    public function resolve(): ?Organization
    {
        if ($this->salesRepresentativeContext->getRepresentative() !== null) {
            return $this->salesRepresentativeContext->getActiveOrganization();
        }

        return $this->customerOrganizationResolver->resolve();
    }
}
