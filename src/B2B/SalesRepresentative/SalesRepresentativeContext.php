<?php

declare(strict_types=1);

namespace App\B2B\SalesRepresentative;

use App\B2B\Entity\Organization;
use App\B2B\Entity\SalesRepresentative;
use App\Entity\Customer\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

final class SalesRepresentativeContext
{
    private const SESSION_ORGANIZATION = 'semaco.active_organization';

    private const SESSION_CUSTOMER = 'semaco.active_organization_customer';

    /** @var array<int, list<Organization>> */
    private array $organizationsCache = [];

    public function __construct(
        private readonly CustomerContextInterface $customerContext,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        #[Autowire('%app.b2b.sales_representative_group%')]
        private readonly string $salesGroupCode,
    ) {
    }

    public function getRepresentative(): ?SalesRepresentative
    {
        $customer = $this->customerContext->getCustomer();
        if (!$customer instanceof Customer || $customer->getGroup()?->getCode() !== $this->salesGroupCode) {
            return null;
        }

        return $customer->getSalesRepresentative();
    }

    /** @return list<Organization> */
    public function getOrganizations(): array
    {
        $representative = $this->getRepresentative();
        if ($representative === null) {
            return [];
        }

        $id = (int) $representative->getId();

        /** @var list<Organization> $organizations */
        $organizations = $this->organizationsCache[$id] ??= $this->entityManager->getRepository(Organization::class)->findBy(
            ['salesRepresentativeCode' => $representative->getExternalIdentifier()],
            ['name' => 'ASC'],
        );

        return $organizations;
    }

    public function canControl(Organization $organization): bool
    {
        $representative = $this->getRepresentative();

        return $representative !== null && $organization->getSalesRepresentativeCode() === $representative->getExternalIdentifier();
    }

    public function getActiveOrganization(): ?Organization
    {
        $id = $this->session()?->get(self::SESSION_ORGANIZATION);
        if (!is_int($id)) {
            return null;
        }

        $organization = $this->entityManager->find(Organization::class, $id);

        return $organization instanceof Organization && $this->canControl($organization) ? $organization : null;
    }

    public function getActiveCustomer(): ?Customer
    {
        $organization = $this->getActiveOrganization();
        $id = $this->session()?->get(self::SESSION_CUSTOMER);
        if ($organization === null || !is_int($id)) {
            return null;
        }

        $customer = $this->entityManager->find(Customer::class, $id);

        return $customer instanceof Customer && $customer->getOrganization() === $organization ? $customer : null;
    }

    public function activateOrganization(Organization $organization): bool
    {
        if (!$this->canControl($organization)) {
            return false;
        }

        $this->session()?->set(self::SESSION_ORGANIZATION, $organization->getId());
        $this->session()?->remove(self::SESSION_CUSTOMER);

        return true;
    }

    public function activateCustomer(Customer $customer): bool
    {
        $organization = $this->getActiveOrganization();
        if ($organization === null || $customer->getOrganization() !== $organization) {
            return false;
        }

        $this->session()?->set(self::SESSION_CUSTOMER, $customer->getId());

        return true;
    }

    private function session(): ?\Symfony\Component\HttpFoundation\Session\SessionInterface
    {
        $request = $this->requestStack->getMainRequest();

        return $request !== null && $request->hasSession() ? $request->getSession() : null;
    }
}
