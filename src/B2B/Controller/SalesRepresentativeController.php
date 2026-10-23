<?php

declare(strict_types=1);

namespace App\B2B\Controller;

use App\B2B\Entity\Organization;
use App\B2B\SalesRepresentative\SalesRepresentativeContext;
use App\Entity\Customer\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SalesRepresentativeController
{
    public function __construct(
        private SalesRepresentativeContext $context,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/set-organization/{id}', name: 'app_set_organization_action', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function setOrganization(Request $request, int $id): RedirectResponse
    {
        $organization = $this->entityManager->find(Organization::class, $id);
        if (!$organization instanceof Organization) {
            throw new NotFoundHttpException();
        }

        if (!$this->context->activateOrganization($organization)) {
            throw new AccessDeniedHttpException();
        }

        return $this->back($request);
    }

    #[Route('/set-organization-customer/{id}', name: 'app_set_organization_customer_action', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function setOrganizationCustomer(Request $request, int $id): RedirectResponse
    {
        $customer = $this->entityManager->find(Customer::class, $id);
        if (!$customer instanceof Customer) {
            throw new NotFoundHttpException();
        }

        if (!$this->context->activateCustomer($customer)) {
            throw new AccessDeniedHttpException();
        }

        return $this->back($request);
    }

    private function back(Request $request): RedirectResponse
    {
        $return = $request->query->getString('return');
        if ($return !== '' && str_starts_with($return, '/') && !str_starts_with($return, '//')) {
            return new RedirectResponse($return);
        }

        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_homepage'));
    }
}
