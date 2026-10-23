<?php

declare(strict_types=1);

namespace App\B2B\Context;

use App\B2B\Entity\Organization;

interface ActiveOrganizationResolverInterface
{
    public function resolve(): ?Organization;
}
