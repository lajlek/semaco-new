<?php

declare(strict_types=1);

namespace App\Partner\Fetch;

final readonly class RobotsPolicy
{
    /**
     * @param list<string> $disallow
     * @param list<string> $allow
     */
    public function __construct(
        private array $disallow,
        private array $allow,
        public ?float $crawlDelay,
    ) {
    }

    public static function parse(string $robotsTxt): self
    {
        $groups = [];
        $agents = [];
        $inRules = false;
        foreach (preg_split('/\R/', $robotsTxt) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);
            if ($field === 'user-agent') {
                if ($inRules) {
                    $agents = [];
                    $inRules = false;
                }
                $agents[] = strtolower($value);

                continue;
            }

            $inRules = true;
            foreach ($agents as $agent) {
                $groups[$agent][] = [$field, $value];
            }
        }

        $disallow = [];
        $allow = [];
        $delay = null;
        foreach ($groups['*'] ?? [] as [$field, $value]) {
            match ($field) {
                'disallow' => $value !== '' ? $disallow[] = $value : null,
                'allow' => $allow[] = $value,
                'crawl-delay' => $delay = is_numeric($value) ? (float) $value : $delay,
                default => null,
            };
        }

        return new self($disallow, $allow, $delay);
    }

    public function isAllowed(string $url): bool
    {
        $parts = parse_url($url);
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

        $allowLength = -1;
        foreach ($this->allow as $rule) {
            if (self::matches($rule, $path)) {
                $allowLength = max($allowLength, strlen($rule));
            }
        }

        foreach ($this->disallow as $rule) {
            if (self::matches($rule, $path) && strlen($rule) > $allowLength) {
                return false;
            }
        }

        return true;
    }

    private static function matches(string $rule, string $path): bool
    {
        $anchored = str_ends_with($rule, '$');
        $pattern = '~^' . str_replace('\*', '.*', preg_quote(rtrim($rule, '$'), '~')) . ($anchored ? '$' : '') . '~';

        return preg_match($pattern, $path) === 1;
    }
}
