<?php

declare(strict_types=1);

namespace App\Partner\Fetch;

final readonly class SitemapReader
{
    private const MAX_CHILD_SITEMAPS = 20;

    public function __construct(private PolitePageFetcher $fetcher)
    {
    }

    /** @return list<string> */
    public function urls(string $partnerCode, string $sitemapUrl): array
    {
        $body = $this->fetcher->fetchSitemap($sitemapUrl, $partnerCode);
        if (!str_contains($body, '<sitemapindex')) {
            return self::locations($body);
        }

        $urls = [];
        foreach (array_slice(self::locations($body), 0, self::MAX_CHILD_SITEMAPS) as $childUrl) {
            array_push($urls, ...self::locations($this->fetcher->fetchSitemap($childUrl, $partnerCode)));
        }

        return $urls;
    }

    /** @return list<string> */
    private static function locations(string $xml): array
    {
        preg_match_all('#<loc>\s*([^<]+?)\s*</loc>#', $xml, $matches);

        return array_map(static fn (string $url): string => html_entity_decode($url, \ENT_XML1 | \ENT_QUOTES, 'UTF-8'), $matches[1]);
    }
}
