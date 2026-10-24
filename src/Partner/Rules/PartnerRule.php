<?php

declare(strict_types=1);

namespace App\Partner\Rules;

final readonly class PartnerRule
{
    /**
     * @param array<string, string> $options source parameter => Semaco option code
     * @param array<string, string> $extract source parameter => regex with named groups (group name = derived parameter)
     * @param list<string> $allowedHosts hosts the crawler may fetch
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

    public function isAllowedUrl(string $url): bool
    {
        $scheme = parse_url($url, \PHP_URL_SCHEME);
        $host = parse_url($url, \PHP_URL_HOST);

        return $scheme === 'https' && is_string($host) && in_array(strtolower($host), $this->allowedHosts, true);
    }
}
