<?php

declare(strict_types=1);

namespace App\Redirect\Service;

use App\Redirect\Repository\RedirectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RedirectResolver
{
    public function __construct(
        private RedirectRepositoryInterface $redirectRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function resolve(Request $request): ?Response
    {
        $redirect = $this->redirectRepository->findEnabledBySource(rawurldecode($request->getPathInfo()));
        if ($redirect === null || $redirect->getDestination() === '') {
            return null;
        }

        $redirect->markAccessed();
        $this->entityManager->flush();

        $destination = $redirect->getDestination();
        $query = $request->getQueryString();
        if ($query !== null && !str_contains($destination, '?')) {
            $destination .= '?' . $query;
        }

        return new RedirectResponse($destination, $redirect->isPermanent() ? Response::HTTP_MOVED_PERMANENTLY : Response::HTTP_FOUND);
    }
}
