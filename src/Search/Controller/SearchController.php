<?php

declare(strict_types=1);

namespace App\Search\Controller;

use App\Entity\Product\Product;
use App\Search\Service\ProductSearch;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class SearchController
{
    private const MIN_QUERY_LENGTH = 2;

    public function __construct(
        private ProductSearch $productSearch,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
    ) {
    }

    #[Route('/{_locale}/search', name: 'app_shop_search', requirements: ['_locale' => '[a-z]{2}_[A-Z]{2}'], methods: ['GET'])]
    public function search(Request $request): Response
    {
        $query = $this->query($request);
        $results = null;
        if (mb_strlen($query) >= self::MIN_QUERY_LENGTH) {
            $results = $this->productSearch->search($query, $this->channel(), $this->localeContext->getLocaleCode(), $request->query->getInt('page', 1), 24);
        }

        return new Response($this->twig->render('shop/search/index.html.twig', [
            'query' => $query,
            'results' => $results,
        ]));
    }

    #[Route('/{_locale}/auto-complete/product', name: 'app_shop_search_autocomplete', requirements: ['_locale' => '[a-z]{2}_[A-Z]{2}'], methods: ['GET'])]
    #[Route('/{_locale}/auto-complete/product-all', name: 'app_shop_search_autocomplete_all', requirements: ['_locale' => '[a-z]{2}_[A-Z]{2}'], methods: ['GET'])]
    public function autocomplete(Request $request): JsonResponse
    {
        $query = $this->query($request);
        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return new JsonResponse(['items' => []]);
        }

        $locale = $this->localeContext->getLocaleCode();
        $items = array_map(
            fn (Product $product): array => [
                'name' => $product->getName(),
                'slug' => $product->getSlug(),
                'url' => $this->urlGenerator->generate('sylius_shop_product_show', ['slug' => $product->getSlug(), '_locale' => $locale]),
                'image' => $this->imagePath($product),
            ],
            $this->productSearch->suggest($query, $this->channel(), $locale, 10),
        );

        return new JsonResponse(['items' => $items]);
    }

    private function query(Request $request): string
    {
        $legacy = $request->query->all('bitbag_elasticsearch_search');
        $legacyQuery = is_array($legacy['box'] ?? null) && is_string($legacy['box']['query'] ?? null) ? $legacy['box']['query'] : '';

        return ProductSearch::normalize($request->query->getString('query', $request->query->getString('q', $legacyQuery)));
    }

    private function imagePath(Product $product): ?string
    {
        $image = $product->getImagesByType('main')->first() ?: $product->getImages()->first();

        return $image instanceof ProductImageInterface ? $image->getPath() : null;
    }

    private function channel(): ChannelInterface
    {
        $channel = $this->channelContext->getChannel();
        \assert($channel instanceof ChannelInterface);

        return $channel;
    }
}
