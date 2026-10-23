<?php

declare(strict_types=1);

namespace App\Cms\Controller;

use App\Cms\Repository\CmsPageRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final readonly class CmsPageController
{
    public function __construct(
        private CmsPageRepositoryInterface $cmsPageRepository,
        private Environment $twig,
    ) {
    }

    #[Route('/{_locale}/page/{slug}', name: 'app_shop_cms_page_show', requirements: ['_locale' => '[a-z]{2}_[A-Z]{2}', 'slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(string $slug): Response
    {
        $page = $this->cmsPageRepository->findEnabledBySlug($slug);
        if ($page === null) {
            throw new NotFoundHttpException();
        }

        return new Response($this->twig->render('shop/cms/show.html.twig', ['page' => $page]));
    }
}
