<?php

declare(strict_types=1);

namespace App\Shop\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ProductListTypeController
{
    public const SESSION_KEY = 'product_list_type';

    #[Route('/set-product-list-type/{type}', name: 'app_shop_set_product_list_type', requirements: ['type' => 'list|modern'], methods: ['GET'])]
    public function __invoke(string $type, Request $request): RedirectResponse
    {
        $request->getSession()->set(self::SESSION_KEY, $type);

        return new RedirectResponse(self::localReturnPath($request->query->getString('return'), $request->getHost()) ?? '/');
    }

    public static function localReturnPath(string $url, string $host): ?string
    {
        $parts = parse_url($url);
        if ($url === '' || $parts === false || str_contains($url, '\\') || str_starts_with($url, '//')) {
            return null;
        }
        if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }
        if (isset($parts['host']) !== isset($parts['scheme']) || (isset($parts['host']) && strcasecmp($parts['host'], $host) !== 0)) {
            return null;
        }
        $path = $parts['path'] ?? '';
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
}
