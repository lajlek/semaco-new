<?php

declare(strict_types=1);

namespace App\Partner\Rules;

final readonly class PartnerRule
{
    /**
     * @param array<string, string> $options source parameter => Semaco option code
     * @param array<string, string> $extract source parameter => regex with named groups (group name = derived parameter)
     * @param list<string> $allowedHosts hosts the crawler may fetch
     * @param string|null $sitemapUrl sitemap listing the partner's product pages
     * @param string|null $listingUrlPattern regex a sitemap URL must match to be a product page
     * @param string|null $titlePattern regex a page title must match for its items to be taken
     */
    public function __construct(
        public string $partnerCode,
        public string $label,
        public string $brandName,
        public ?string $familyPattern,
        public array $options,
        public array $extract,
        public string $variantNameTemplate,
        public array $allowedHosts,
        public ?string $sitemapUrl = null,
        public ?string $listingUrlPattern = null,
        public ?string $titlePattern = null,
    ) {
    }

    public function familyKey(string $orderCode, ?string $familyCode): ?string
    {
        if ($this->familyPattern === null) {
            return $familyCode;
        }

        return preg_match($this->familyPattern, $orderCode, $match) === 1 ? trim($match[1]) : $familyCode;
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return array<string, string> source parameter => value, including derived ones
     */
    public function sourceValues(array $parameters): array
    {
        foreach ($this->extract as $parameter => $pattern) {
            if (!isset($parameters[$parameter]) || preg_match($pattern, $parameters[$parameter], $match) !== 1) {
                continue;
            }
            foreach ($match as $group => $value) {
                if (is_string($group) && $value !== '') {
                    $parameters[$group] ??= $value;
                }
            }
        }

        return $parameters;
    }

    public static function comparableUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if ($parts === false) {
            return trim($url);
        }

        parse_str($parts['query'] ?? '', $query);
        if (($query['page'] ?? null) === '1') {
            unset($query['page']);
        }
        ksort($query);

        return sprintf(
            '%s://%s%s%s',
            strtolower($parts['scheme'] ?? 'https'),
            strtolower($parts['host'] ?? ''),
            rtrim($parts['path'] ?? '', '/'),
            $query === [] ? '' : '?' . http_build_query($query),
        );
    }

    public function isListingUrl(string $url): bool
    {
        return $this->listingUrlPattern === null || preg_match($this->listingUrlPattern, $url) === 1;
    }

    public function allowsTitle(?string $title): bool
    {
        return $this->titlePattern === null || ($title !== null && preg_match($this->titlePattern, $title) === 1);
    }

    public function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts) &&
            ($parts['scheme'] ?? null) === 'https' &&
            in_array($parts['port'] ?? 443, [443], true) &&
            !isset($parts['user']) &&
            in_array(strtolower($parts['host'] ?? ''), $this->allowedHosts, true);
    }
}
